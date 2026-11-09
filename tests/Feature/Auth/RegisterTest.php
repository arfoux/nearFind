<?php

namespace Tests\Feature\Auth;

use App\Mail\VerifyEmail;
use App\Models\Profile;
use App\Support\Seal\Sealer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private const COOKIE = 'nearfind_seal';

    private const BOOTSTRAP_EMAIL = 'pengurus@nearfind.test';

    private const KEY = 'AAECAwQFBgcICQoLDA0ODxAREhMUFRYXGBkaGxwdHh8';

    private const PASSWORD = 'rahasia123';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'seal.issuer' => 'example-app',
            'seal.audience' => 'web',
            'seal.current_key_id' => '2026-05',
            'seal.keys' => ['2026-05' => self::KEY],
            'seal.clock_tolerance' => 0,
            'seal.max_token_size' => 16384,
            'seal.session.cookie' => self::COOKIE,
            'seal.session.ttl' => '12h',
            'seal.session.remember_ttl' => '30d',
            'seal.auth.email' => self::BOOTSTRAP_EMAIL,
            'seal.auth.name' => 'nearFind',
            'seal.auth.password_hash' => Hash::make('kata-sandi-pengurus'),
        ]);
    }

    public function test_the_auth_page_opens_the_register_tab_for_guests(): void
    {
        $this->get(route('auth', ['mode' => 'daftar']))
            ->assertOk()
            ->assertSee('data-auth-initial="register"', false)
            ->assertSee('Ulangi kata sandi');
    }

    public function test_guests_are_redirected_away_from_the_auth_page_when_signed_in(): void
    {
        $this->withUnencryptedCookie(self::COOKIE, $this->bootstrapToken())
            ->get(route('auth', ['mode' => 'daftar']))
            ->assertRedirect(route('home'));
    }

    public function test_registration_creates_an_unverified_account_and_mails_the_link(): void
    {
        Mail::fake();

        $this->post(route('auth.register'), [
            'name' => 'Siti Rahma',
            'email' => 'Siti.Rahma@Example.ID',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ])->assertRedirect(route('auth'));

        $profile = Profile::where('email', 'siti.rahma@example.id')->first();
        $this->assertNotNull($profile);
        $this->assertSame('Siti Rahma', $profile->name);
        $this->assertFalse($profile->hasVerifiedEmail());
        $this->assertTrue(Hash::check(self::PASSWORD, (string) $profile->password_hash));

        Mail::assertSent(VerifyEmail::class, function (VerifyEmail $mail) use ($profile): bool {
            return $mail->hasTo('siti.rahma@example.id')
                && $mail->verificationUrl === url('/daftar/verifikasi?token='.$profile->email_verification_token);
        });
    }

    public function test_the_token_is_cleared_by_verification_and_the_account_can_sign_in(): void
    {
        Mail::fake();

        $this->post(route('auth.register'), [
            'name' => 'Siti Rahma',
            'email' => 'siti@example.id',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ]);

        $token = (string) Profile::where('email', 'siti@example.id')->first()->email_verification_token;
        $this->assertNotSame('', $token);

        $this->get(route('register.verify', ['token' => $token]))
            ->assertRedirect(route('auth'));

        $profile = Profile::where('email', 'siti@example.id')->first();
        $this->assertTrue($profile->hasVerifiedEmail());
        $this->assertNull($profile->email_verification_token);

        $this->post(route('auth.login'), ['email' => 'siti@example.id', 'password' => self::PASSWORD])
            ->assertRedirect(route('home'))
            ->assertCookie(self::COOKIE);
    }

    public function test_the_verification_link_cannot_be_replayed(): void
    {
        Mail::fake();

        $this->post(route('auth.register'), [
            'name' => 'Siti Rahma',
            'email' => 'siti@example.id',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ]);

        $token = (string) Profile::where('email', 'siti@example.id')->first()->email_verification_token;

        $this->get(route('register.verify', ['token' => $token]))->assertRedirect(route('auth'));

        // A token that was already consumed is not recognised: a leaked mail
        // cannot re-open or re-activate the row.
        $this->get(route('register.verify', ['token' => $token]))
            ->assertRedirect(route('auth'))
            ->assertSessionHas('status', 'Tautan verifikasi tidak dikenali atau sudah dipakai.');
    }

    public function test_an_unverified_account_cannot_sign_in(): void
    {
        Mail::fake();

        $this->post(route('auth.register'), [
            'name' => 'Siti Rahma',
            'email' => 'siti@example.id',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ]);

        $this->post(route('auth.login'), ['email' => 'siti@example.id', 'password' => self::PASSWORD])
            ->assertSessionHasErrors('email');

        $this->assertGuest('seal');
    }

    public function test_the_bootstrap_credential_still_signs_in(): void
    {
        $this->post(route('auth.login'), [
            'email' => self::BOOTSTRAP_EMAIL,
            'password' => 'kata-sandi-pengurus',
        ])->assertRedirect(route('home'))->assertCookie(self::COOKIE);
    }

    public function test_a_row_that_squats_the_bootstrap_address_cannot_take_it_over(): void
    {
        Mail::fake();

        // Registering that address is allowed on purpose: the compiled register
        // path cannot read the `seal` group to refuse it. The row is inert —
        // login answers the deployment binding first for its own address.
        $this->post(route('auth.register'), [
            'name' => 'Penyusup',
            'email' => self::BOOTSTRAP_EMAIL,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ])->assertRedirect(route('auth'));

        $this->assertSame(1, Profile::where('email', self::BOOTSTRAP_EMAIL)->count());
        Mail::assertSent(VerifyEmail::class, 1);

        // The squatter's password never authenticates...
        $this->post(route('auth.login'), [
            'email' => self::BOOTSTRAP_EMAIL,
            'password' => self::PASSWORD,
        ])->assertSessionHasErrors('email');

        // ...and the deployment credential still does.
        $this->post(route('auth.login'), [
            'email' => self::BOOTSTRAP_EMAIL,
            'password' => 'kata-sandi-pengurus',
        ])->assertRedirect(route('home'))->assertCookie(self::COOKIE);
    }

    public function test_an_unverified_address_reissues_the_link_for_the_same_person(): void
    {
        Mail::fake();

        $this->post(route('auth.register'), [
            'name' => 'Siti Rahma',
            'email' => 'siti@example.id',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ]);

        $first = (string) Profile::where('email', 'siti@example.id')->first()->email_verification_token;

        // Same person, same password: the lost mail must not lock the address
        // out of its own registration.
        $this->post(route('auth.register'), [
            'name' => 'Siti Rahma',
            'email' => 'SITI@example.id',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ])->assertRedirect(route('auth'));

        $this->assertSame(1, Profile::where('email', 'siti@example.id')->count());

        $second = (string) Profile::where('email', 'siti@example.id')->first()->email_verification_token;
        $this->assertNotSame($first, $second);
        Mail::assertSent(VerifyEmail::class, 2);
    }

    public function test_a_taken_email_with_another_password_is_refused(): void
    {
        Mail::fake();

        $this->post(route('auth.register'), [
            'name' => 'Siti Rahma',
            'email' => 'siti@example.id',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ]);

        $this->post(route('auth.register'), [
            'name' => 'Siti Lain',
            'email' => 'SITI@example.id',
            'password' => 'lain-sekali',
            'password_confirmation' => 'lain-sekali',
        ])->assertSessionHasErrors('email');

        $this->assertSame(1, Profile::where('email', 'siti@example.id')->count());
        Mail::assertSent(VerifyEmail::class, 1);
    }

    public function test_a_verified_address_cannot_be_registered_again(): void
    {
        Mail::fake();

        $payload = [
            'name' => 'Siti Rahma',
            'email' => 'siti@example.id',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ];

        $this->post(route('auth.register'), $payload);

        $token = (string) Profile::where('email', 'siti@example.id')->first()->email_verification_token;
        $this->get(route('register.verify', ['token' => $token]));

        $this->post(route('auth.register'), $payload)->assertSessionHasErrors('email');
        $this->assertSame(1, Profile::where('email', 'siti@example.id')->count());
    }

    public function test_the_password_confirmation_is_required(): void
    {
        Mail::fake();

        $this->post(route('auth.register'), [
            'name' => 'Siti Rahma',
            'email' => 'siti@example.id',
            'password' => self::PASSWORD,
            'password_confirmation' => 'beda-sekali',
        ])->assertSessionHasErrors('password');

        $this->assertSame(0, Profile::count());
    }

    private function bootstrapToken(): string
    {
        return app(Sealer::class)->seal(Sealer::PURPOSE_SESSION, [
            'email' => self::BOOTSTRAP_EMAIL,
            'name' => 'nearFind',
        ], Sealer::durationToMilliseconds('12h'));
    }
}

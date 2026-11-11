<?php

namespace Tests\Feature\Auth;

use App\Support\Seal\Sealer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private const COOKIE = 'nearfind_seal';

    private const EMAIL = 'warga@nearfind.test';

    private const NAME = 'Warga nearFind';

    private const PASSWORD = 'nearfind';

    private const KEY = 'AAECAwQFBgcICQoLDA0ODxAREhMUFRYXGBkaGxwdHh8';

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
            'seal.auth.email' => self::EMAIL,
            'seal.auth.name' => self::NAME,
            'seal.auth.password_hash' => Hash::make(self::PASSWORD),
        ]);
    }

    public function test_the_auth_page_renders_both_forms_for_guests(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Masuk atau daftar')
            ->assertSee('Email')
            ->assertSee('Kata sandi')
            ->assertSee('Ulangi kata sandi')
            ->assertSee('data-auth-initial="login"', false);
    }

    public function test_the_register_tab_is_active_from_the_mode_query(): void
    {
        $this->get(route('login', ['mode' => 'daftar']))
            ->assertOk()
            ->assertSee('data-auth-initial="register"', false);
    }

    public function test_the_auth_page_uses_the_flat_canvas_background(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('class="min-h-dvh bg-canvas', false);
        $response->assertDontSee('class="app-background', false);
    }

    public function test_the_profile_tab_points_at_the_profile_page(): void
    {
        $response = $this->withUnencryptedCookie(self::COOKIE, $this->validToken())->get(route('home'));

        $response->assertOk();

        $this->assertSame(1, preg_match('/<a\b[^>]*data-nav-key="profil"[^>]*>/', (string) $response->getContent(), $match));
        $this->assertStringContainsString('href="'.route('profile').'"', $match[0]);
    }

    public function test_guests_are_redirected_to_the_auth_page_from_the_profile_page(): void
    {
        $this->get(route('profile'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_are_redirected_away_from_the_auth_page(): void
    {
        $this->withUnencryptedCookie(self::COOKIE, $this->validToken())
            ->get(route('login'))
            ->assertRedirect(route('home'));
    }

    public function test_a_warga_can_login_with_valid_credentials(): void
    {
        $response = $this->post(route('auth.login'), [
            'email' => '  Warga@NearFind.test ',
            'password' => self::PASSWORD,
        ]);

        $response->assertRedirect(route('home'));
        $response->assertCookie(self::COOKIE);

        $cookie = $response->getCookie(self::COOKIE, false);

        $this->assertNotNull($cookie);
        $this->assertStringStartsWith('stseal.v1.', (string) $cookie->getValue());

        $this->withUnencryptedCookie(self::COOKIE, (string) $cookie->getValue())
            ->get(route('profile'))
            ->assertOk()
            ->assertSee(self::NAME)
            ->assertSee(self::EMAIL);
    }

    public function test_a_warga_cannot_login_with_an_incorrect_password(): void
    {
        $response = $this->from(route('login'))->post(route('auth.login'), [
            'email' => self::EMAIL,
            'password' => 'kata-sandi-salah',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $response->assertCookieMissing(self::COOKIE);

        $this->assertGuest('seal');
    }

    public function test_a_warga_cannot_login_with_an_unknown_email(): void
    {
        $response = $this->from(route('login'))->post(route('auth.login'), [
            'email' => 'orang-lain@nearfind.test',
            'password' => self::PASSWORD,
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $response->assertCookieMissing(self::COOKIE);
    }

    public function test_a_warga_can_logout(): void
    {
        $response = $this->withUnencryptedCookie(self::COOKIE, $this->validToken())
            ->post(route('logout'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');
        $response->assertCookieExpired(self::COOKIE);
    }

    public function test_the_cookie_lives_for_twelve_hours_by_default(): void
    {
        $before = time();

        $response = $this->post(route('auth.login'), [
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
        ]);

        $cookie = $response->getCookie(self::COOKIE, false);

        $this->assertNotNull($cookie);
        $this->assertEqualsWithDelta($before + (12 * 60 * 60), $cookie->getExpiresTime(), 10);
    }

    public function test_remember_me_extends_the_cookie_to_thirty_days(): void
    {
        $before = time();

        $response = $this->post(route('auth.login'), [
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
            'remember' => '1',
        ]);

        $cookie = $response->getCookie(self::COOKIE, false);

        $this->assertNotNull($cookie);
        $this->assertEqualsWithDelta($before + (30 * 24 * 60 * 60), $cookie->getExpiresTime(), 10);
    }

    public function test_login_is_rate_limited_after_five_failed_attempts(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('auth.login'), [
                'email' => self::EMAIL,
                'password' => 'kata-sandi-salah',
            ])->assertSessionHasErrors('email');
        }

        $response = $this->post(route('auth.login'), [
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
        ]);

        $response->assertSessionHasErrors('email');
        $response->assertCookieMissing(self::COOKIE);
    }

    public function test_a_tampered_cookie_does_not_authenticate(): void
    {
        $this->withUnencryptedCookie(self::COOKIE, 'stseal.v1.invalid.invalid.invalid')
            ->get(route('profile'))
            ->assertRedirect(route('login'));
    }

    public function test_the_home_page_reflects_the_authenticated_user(): void
    {
        $this->withUnencryptedCookie(self::COOKIE, $this->validToken())
            ->get(route('home'))
            ->assertOk()
            ->assertSee(self::NAME);
    }

    private function validToken(): string
    {
        return app(Sealer::class)->seal(Sealer::PURPOSE_SESSION, [
            'email' => self::EMAIL,
            'name' => self::NAME,
        ], '1h');
    }
}

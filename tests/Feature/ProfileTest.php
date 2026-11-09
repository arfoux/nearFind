<?php

namespace Tests\Feature;

use App\Models\Need;
use App\Models\Profile;
use App\Models\Report;
use App\Support\Seal\Sealer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private const COOKIE = 'nearfind_seal';

    private const EMAIL = 'warga@nearfind.test';

    private const NAME = 'Warga Uji';

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
            'seal.auth.email' => self::EMAIL,
            'seal.auth.name' => self::NAME,
            'seal.auth.password_hash' => Hash::make('nearfind'),
        ]);
    }

    public function test_guests_are_redirected_to_login_from_the_profile_page(): void
    {
        $this->get(route('profile.index'))->assertRedirect(route('auth'));
    }

    public function test_the_profile_page_only_shows_the_authenticated_users_activity(): void
    {
        $profile = $this->profile();
        $otherProfile = Profile::create([
            'email' => 'lain@nearfind.test',
            'name' => 'Warga Lain',
            'username' => 'warga_lain',
        ]);

        $submittedNeed = Need::create([
            'profile_id' => $profile->id,
            'title' => 'Kebutuhan milik sendiri',
            'category' => 'household',
            'location' => 'Sukajadi',
            'status' => 'aktif',
        ]);

        $supportedNeed = Need::create([
            'profile_id' => $otherProfile->id,
            'title' => 'Kebutuhan yang didukung',
            'category' => 'health',
            'location' => 'Gegerkalong',
            'status' => 'aktif',
        ]);

        Need::create([
            'profile_id' => $otherProfile->id,
            'title' => 'Kebutuhan rahasia warga lain',
            'category' => 'retail',
            'location' => 'Pasteur',
            'status' => 'aktif',
        ]);

        $profile->supportedNeeds()->attach($supportedNeed);

        Report::create([
            'profile_id' => $profile->id,
            'need_id' => $submittedNeed->id,
            'need_title' => 'Kebutuhan milik sendiri',
            'category' => 'household',
            'status' => 'diproses',
        ]);

        $this->asWarga()
            ->get(route('profile.index'))
            ->assertOk()
            ->assertSee(self::NAME)
            ->assertSee(self::EMAIL)
            ->assertSee('@warga_uji')
            ->assertSee('Kebutuhan milik sendiri')
            ->assertSee('Kebutuhan yang didukung')
            ->assertDontSee('Kebutuhan rahasia warga lain')
            ->assertViewHas('stats', [
                'submitted' => 1,
                'supported' => 1,
                'reports' => 1,
            ]);
    }

    public function test_a_warga_can_update_profile_information(): void
    {
        $profile = $this->profile();

        $this->asWarga()
            ->put(route('profile.update'), [
                'name' => 'Nama Baru Warga',
                'username' => 'nama.baru',
                'phone' => '0812 3456 7890',
                'bio' => 'Koordinator ronda RW 05.',
            ])
            ->assertRedirect(route('profile.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('profiles', [
            'id' => $profile->id,
            'name' => 'Nama Baru Warga',
            'username' => 'nama.baru',
            'phone' => '0812 3456 7890',
            'bio' => 'Koordinator ronda RW 05.',
        ]);
    }

    public function test_username_must_be_unique_and_valid(): void
    {
        $this->profile();

        Profile::create([
            'email' => 'lain@nearfind.test',
            'name' => 'Warga Lain',
            'username' => 'sudah_dipakai',
        ]);

        $this->asWarga()
            ->from(route('profile.index'))
            ->put(route('profile.update'), [
                'name' => self::NAME,
                'username' => 'sudah_dipakai',
            ])
            ->assertRedirect(route('profile.index'))
            ->assertSessionHasErrors('username');

        $this->asWarga()
            ->from(route('profile.index'))
            ->put(route('profile.update'), [
                'name' => self::NAME,
                'username' => 'username tidak valid!',
            ])
            ->assertSessionHasErrors('username');
    }

    public function test_a_warga_can_change_their_password(): void
    {
        $profile = $this->profile();
        $profile->update(['password_hash' => Hash::make('password-lama')]);

        $this->asWarga()
            ->from(route('profile.index'))
            ->put(route('profile.password'), [
                'current_password' => 'kata-sandi-salah',
                'password' => 'password-baru',
                'password_confirmation' => 'password-baru',
            ])
            ->assertRedirect(route('profile.index'))
            ->assertSessionHasErrors('current_password');

        $this->asWarga()
            ->put(route('profile.password'), [
                'current_password' => 'password-lama',
                'password' => 'password-baru',
                'password_confirmation' => 'password-baru',
            ])
            ->assertRedirect(route('profile.index'))
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('password-baru', (string) $profile->fresh()->password_hash));
    }

    public function test_a_warga_can_update_notification_preferences(): void
    {
        $profile = $this->profile();

        $this->asWarga()
            ->put(route('profile.notifications'), [
                'need_updates' => '1',
                'weekly_digest' => '1',
            ])
            ->assertRedirect(route('profile.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame([
            'need_updates' => true,
            'support_activity' => false,
            'weekly_digest' => true,
        ], $profile->fresh()->notification_settings);
    }

    public function test_the_profile_page_requires_logout_confirmation(): void
    {
        $this->profile();

        $this->asWarga()
            ->get(route('profile.index'))
            ->assertOk()
            ->assertSee('Yakin ingin logout?')
            ->assertSee('Ubah Password');
    }

    private function profile(): Profile
    {
        return Profile::create([
            'email' => self::EMAIL,
            'name' => self::NAME,
            'username' => 'warga_uji',
        ]);
    }

    private function asWarga(): static
    {
        $token = app(Sealer::class)->seal(Sealer::PURPOSE_SESSION, [
            'email' => self::EMAIL,
            'name' => self::NAME,
        ], '1h');

        return $this->withUnencryptedCookie(self::COOKIE, $token);
    }
}

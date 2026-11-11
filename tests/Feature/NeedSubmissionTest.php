<?php

namespace Tests\Feature;

use App\Models\Need;
use App\Models\Profile;
use App\Support\Seal\Sealer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NeedSubmissionTest extends TestCase
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

    public function test_authenticated_home_renders_the_submission_form_without_the_login_gate(): void
    {
        $this->asWarga()
            ->get(route('home'))
            ->assertOk()
            ->assertViewHas('authenticated', true)
            ->assertSee('id="submit-sheet"', false)
            ->assertSee('Ajukan kebutuhan')
            ->assertDontSee('id="login-gate"', false)
            ->assertDontSee('Masuk untuk melanjutkan');
    }

    public function test_guests_are_redirected_to_the_auth_page_before_submitting_a_need(): void
    {
        $this->post(route('needs.store'), [
            'title' => 'Jasa servis kipas angin',
            'category' => 'repair',
        ])->assertRedirect(route('auth'));

        $this->assertDatabaseCount('needs', 0);
    }

    public function test_a_warga_can_submit_a_need_and_it_lands_in_the_database(): void
    {
        $response = $this->asWarga()->post(route('needs.store'), [
            'title' => 'Jasa servis kipas angin',
            'category' => 'repair',
            'location' => 'Jl. Sukajadi 5',
            'description' => 'Kipas angin warga rusak, butuh teknisi dekat.',
        ]);

        $response->assertRedirect(route('profile'));

        $profile = Profile::where('email', self::EMAIL)->first();

        $this->assertNotNull($profile);
        $this->assertDatabaseHas('needs', [
            'profile_id' => $profile->id,
            'title' => 'Jasa servis kipas angin',
            'marker_label' => 'Jasa servis kipas angin',
            'category' => 'repair',
            'location' => 'Jl. Sukajadi 5',
            'description' => 'Kipas angin warga rusak, butuh teknisi dekat.',
            'status' => 'aktif',
        ]);

        // The new row is what the public pages read, not the config.
        $this->asWarga()
            ->get(route('profile'))
            ->assertOk()
            ->assertSee('Jasa servis kipas angin');
        $this->get(route('needs.index'))->assertOk()->assertSee('Jasa servis kipas angin');
    }

    public function test_a_submitted_need_reuses_the_existing_profile(): void
    {
        $profile = Profile::create([
            'email' => self::EMAIL,
            'name' => self::NAME,
            'username' => 'warga_uji',
        ]);

        $this->asWarga()
            ->post(route('needs.store'), [
                'title' => 'Warung kopi keliling',
                'category' => 'culinary',
            ])
            ->assertRedirect(route('profile'));

        $need = Need::where('title', 'Warung kopi keliling')->first();

        $this->assertNotNull($need);
        $this->assertSame($profile->id, $need->profile_id);
        $this->assertDatabaseCount('profiles', 1);
    }

    public function test_the_category_must_be_one_of_the_configured_ones(): void
    {
        $this->asWarga()
            ->from(route('home'))
            ->post(route('needs.store'), [
                'title' => 'Kebutuhan tanpa kategori',
                'category' => 'tidak-ada',
            ])
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors('category');

        $this->assertDatabaseCount('needs', 0);
    }

    public function test_the_title_is_too_short(): void
    {
        $this->asWarga()
            ->from(route('home'))
            ->post(route('needs.store'), [
                'title' => 'ab',
                'category' => 'repair',
            ])
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors('title');

        $this->assertDatabaseCount('needs', 0);
    }

    private function asWarga(): static
    {
        return $this->withUnencryptedCookie(self::COOKIE, $this->validToken());
    }

    private function validToken(): string
    {
        return app(Sealer::class)->seal(Sealer::PURPOSE_SESSION, [
            'email' => self::EMAIL,
            'name' => self::NAME,
        ], '1h');
    }
}

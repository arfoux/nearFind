<?php

namespace Tests\Feature;

use App\Models\Need;
use App\Models\Profile;
use Database\Seeders\NeedSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NeedSeeder::class);
    }

    public function test_home_page_renders_the_map_and_the_seeded_needs(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('nearFind');
        $response->assertSee('id="map"', false);
        $response->assertSee('Jasa Laundry Kiloan');
        $response->assertSee('Jasa Servis AC');
        $response->assertSee('OpenStreetMap');
        $response->assertSee('mapStyleUrl');
        $response->assertSee('tiles.openfreemap.org');
        $response->assertSee('id="login-gate"', false);
        $response->assertSee('Masuk untuk melanjutkan');
    }

    public function test_the_map_reads_its_needs_from_the_database(): void
    {
        Need::where('title', 'Klinik 24 Jam')->update(['title' => 'Klinik Siaga Malam']);

        $response = $this->get('/');

        $response->assertOk();

        // The map's data island is the payload the client plots; it must carry
        // the edited row and not the seed value.
        preg_match('/<script type="application\/json" id="nearfind-data">(.*?)<\/script>/s', (string) $response->getContent(), $match);
        $data = json_decode($match[1] ?? '[]', true);
        $titles = array_map(static fn (array $need): string => (string) $need['title'], $data['needs'] ?? []);

        $this->assertContains('Klinik Siaga Malam', $titles);
        $this->assertNotContains('Klinik 24 Jam', $titles);
    }

    public function test_explore_and_report_pages_render_and_are_linked_from_the_nav(): void
    {
        $explore = $this->get('/explore');

        $explore->assertOk();
        $explore->assertSee('Kebutuhan di sekitarmu');
        $explore->assertSee('Jasa Laundry Kiloan');
        $explore->assertSee('data-needs-list', false);
        $explore->assertSee('data-list-search', false);
        $explore->assertSee(route('needs.index'), false);
        $explore->assertSee(route('needs.report'), false);

        $report = $this->get('/reports');

        $report->assertOk();
        $report->assertSee('Laporan kebutuhan warga');
        $report->assertSee('Per kategori');
        $report->assertSee('Paling didukung');
        $report->assertSee('Peluang usaha');
        $report->assertSee(route('needs.report'), false);
    }

    public function test_a_guest_asking_for_the_profile_page_is_sent_to_the_auth_page(): void
    {
        $this->get('/profile')
            ->assertRedirect(route('auth'))
            ->assertSessionHas('url.intended', route('profile'));
    }

    public function test_the_profile_tab_points_at_the_profile_endpoint(): void
    {
        $response = $this->get('/');

        $response->assertOk();

        $this->assertSame(1, preg_match('/<a\b[^>]*data-nav-key="profil"[^>]*>/', (string) $response->getContent(), $match));
        $this->assertStringContainsString('href="'.route('profile').'"', $match[0]);
        $this->assertStringNotContainsString('href="'.route('auth').'"', $match[0]);
    }

    public function test_every_tab_shares_the_same_app_shell_nav_track_and_skeleton(): void
    {
        $track = 'pointer-events-auto mx-auto flex w-full max-w-[560px] items-stretch justify-around px-2.5';

        foreach (['/', '/explore', '/reports'] as $path) {
            $response = $this->get($path);

            $response->assertOk();
            $response->assertSee('data-app-shell', false);
            $response->assertSee('data-page-content', false);
            $response->assertSee('data-page-skeleton', false);
            $response->assertSee('data-nav-link', false);
            $response->assertSee($track, false);
        }
    }

    public function test_each_tab_shell_carries_its_own_skeleton_shape(): void
    {
        foreach ([
            '/' => 'peta',
            '/explore' => 'jelajahi',
            '/reports' => 'laporan',
        ] as $path => $pane) {
            $this->get($path)
                ->assertOk()
                ->assertSee('data-skeleton-pane="'.$pane.'"', false);
        }
    }

    public function test_the_seeder_is_idempotent(): void
    {
        $before = Need::count();

        $this->seed(NeedSeeder::class);

        $this->assertSame($before, Need::count());
        $this->assertSame(1, Profile::where('username', 'warga_sukajadi')->count());
    }
}

<?php

namespace Tests\Feature;

use App\Models\Location;
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
        $response->assertSee('tile.openstreetmap.org');
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

    public function test_home_passes_location_rows_and_all_map_styles(): void
    {
        Location::create([
            'name' => 'Warung Bu Sari',
            'category' => 'retail',
            'address' => 'Jl. Cihampelas 12',
            'area' => 'Sukajadi',
            'latitude' => -6.92,
            'longitude' => 107.61,
            'source' => 'warga',
            'author_email' => 'warga@nearfind.test',
        ]);

        $response = $this->get('/');
        $response->assertOk();

        $locations = $response->viewData('locations');

        $this->assertCount(1, $locations);
        $this->assertSame([
            'id',
            'name',
            'category',
            'address',
            'area',
            'latitude',
            'longitude',
            'source',
            'created_at',
        ], array_keys($locations[0]));
        $this->assertSame('Warung Bu Sari', $locations[0]['name']);
        $this->assertSame('warga', $locations[0]['source']);
        $this->assertIsFloat($locations[0]['latitude']);
        $this->assertIsFloat($locations[0]['longitude']);

        $this->assertSame('https://tile.openstreetmap.org/{z}/{x}/{y}.png', $response->viewData('mapDarkStyleUrl'));
        $this->assertSame('https://tile.openstreetmap.org/{z}/{x}/{y}.png', $response->viewData('mapLightStyleUrl'));
        $this->assertSame('https://tile.openstreetmap.org/{z}/{x}/{y}.png', $response->viewData('mapStyleUrl'));
        $this->assertStringNotContainsString('author_email', (string) $response->getContent());

        preg_match('/<script type="application\/json" id="nearfind-data">(.*?)<\/script>/s', (string) $response->getContent(), $match);
        $data = json_decode($match[1] ?? '[]', true);

        $this->assertSame('Warung Bu Sari', $data['locations'][0]['name'] ?? null);
        $this->assertSame('https://tile.openstreetmap.org/{z}/{x}/{y}.png', $data['mapLightStyleUrl'] ?? null);
    }

    public function test_explore_and_report_pages_render_and_are_linked_from_the_nav(): void
    {
        $explore = $this->get('/explore');

        $explore->assertOk();
        $explore->assertSee('Kebutuhan dan fasilitas terdekat di sekitarmu');
        $explore->assertSee('Jasa Laundry Kiloan');
        $explore->assertSee('data-needs-list', false);
        $explore->assertSee('data-list-search', false);
        $explore->assertSee(route('needs.index'), false);
        $explore->assertSee(route('needs.report'), false);

        $report = $this->get('/reports');

        $report->assertOk();
        $report->assertSee('Laporan kebutuhan warga di sekitarmu');
        $report->assertSee('Per kategori');
        $report->assertSee('Paling didukung');
        $report->assertSee('Peluang usaha');
        $report->assertSee(route('needs.report'), false);
    }

    public function test_a_guest_asking_for_the_profile_page_is_sent_to_the_auth_page(): void
    {
        $this->get('/profile')
            ->assertRedirect(route('login'))
            ->assertSessionHas('url.intended', route('profile'));
    }

    public function test_the_profile_tab_points_at_the_profile_endpoint(): void
    {
        $response = $this->get('/');

        $response->assertOk();

        $this->assertSame(1, preg_match('/<a\b[^>]*data-nav-key="profil"[^>]*>/', (string) $response->getContent(), $match));
        $this->assertStringContainsString('href="'.route('profile').'"', $match[0]);
        $this->assertStringNotContainsString('href="'.route('login').'"', $match[0]);
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

    public function test_home_passes_the_exact_poi_config_defaults_to_the_view(): void
    {
        $response = $this->get('/');
        $response->assertOk();

        // Berasal dari config/needs.php tanpa env override di phpunit.xml.
        $this->assertSame([
            'enabled' => true,
            'overpass_endpoint' => 'https://overpass.private.coffee/api/interpreter',
            'nominatim_reverse_endpoint' => 'https://nominatim.openstreetmap.org/reverse',
            'search_radius_m' => 1000,
            'max_results' => 20,
            'request_timeout_ms' => 8000,
            'cache_ms' => 300000,
            'tags' => [
                'amenity' => [
                    'place_of_worship',
                    'school',
                    'clinic',
                    'hospital',
                    'pharmacy',
                    'cafe',
                    'restaurant',
                    'bank',
                    'atm',
                    'post_office',
                    'library',
                    'police',
                ],
                'shop' => ['supermarket', 'convenience', 'bakery'],
                'leisure' => ['park'],
                'tourism' => ['museum'],
            ],
        ], $response->viewData('poi'));
    }

    public function test_the_nearfind_data_island_carries_the_poi_endpoints(): void
    {
        $response = $this->get('/');
        $response->assertOk();

        preg_match('/<script type="application\/json" id="nearfind-data">(.*?)<\/script>/s', (string) $response->getContent(), $match);
        $data = json_decode($match[1] ?? '[]', true);

        // Kedua endpoint dikirim lewat data island untuk dipakai klien.
        $this->assertSame('https://overpass.private.coffee/api/interpreter', $data['poi']['overpass_endpoint'] ?? null);
        $this->assertSame('https://nominatim.openstreetmap.org/reverse', $data['poi']['nominatim_reverse_endpoint'] ?? null);
    }

    public function test_a_poi_endpoint_config_override_reaches_the_view(): void
    {
        // Menimpa kunci config adalah jalur stabil tanpa HTTP mock atau permintaan jaringan.
        $customEndpoint = 'https://overpass.example.test/api/interpreter';
        config()->set('needs.poi.overpass_endpoint', $customEndpoint);

        $response = $this->get('/');
        $response->assertOk();

        $this->assertSame($customEndpoint, $response->viewData('poi')['overpass_endpoint'] ?? null);

        preg_match('/<script type="application\/json" id="nearfind-data">(.*?)<\/script>/s', (string) $response->getContent(), $match);
        $data = json_decode($match[1] ?? '[]', true);
        $this->assertSame($customEndpoint, $data['poi']['overpass_endpoint'] ?? null);
    }

    public function test_home_renders_the_area_detail_panel_hooks(): void
    {
        $response = $this->get('/');
        $response->assertOk();

        $html = (string) $response->getContent();

        foreach ([
            'data-area-panel',
            'data-area-panel-title',
            'data-area-panel-meta',
            'data-area-panel-body',
            'data-area-panel-kicker',
            'data-area-save',
            'data-area-route',
        ] as $hook) {
            $pattern = '/<[a-z][a-z0-9]*\b[^>]*\s'.preg_quote($hook, '/').'(?=[\s>])[^>]*>/i';

            $this->assertSame(1, preg_match_all($pattern, $html), $hook.' must appear once on a single element');
        }
    }

    public function test_the_area_panel_kicker_starts_as_daerah_and_switches_for_places(): void
    {
        $response = $this->get('/');
        $response->assertOk();

        $html = (string) $response->getContent();
        $this->assertSame(1, preg_match('/<p\b[^>]*\sdata-area-panel-kicker(?=[\s>])[^>]*>(.*?)<\/p>/is', $html, $kicker));
        $this->assertSame('Daerah dari OpenStreetMap', trim(strip_tags($kicker[1])));

        // The kicker text is written by client code, so its two states are
        // checked inside the setAreaPanel body that performs the DOM update.
        $script = (string) file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString('[data-area-panel-kicker]', $script);
        $this->assertSame(1, preg_match('/function setAreaPanel\([\s\S]*?\n    \}\n/', $script, $function));
        $this->assertStringContainsString("place.kindKey === 'place'", $function[0]);
        $this->assertStringContainsString('Tempat dari OpenStreetMap', $function[0]);
        $this->assertStringContainsString('Daerah dari OpenStreetMap', $function[0]);
    }

    public function test_the_area_detail_panel_starts_hidden_and_labelled(): void
    {
        $response = $this->get('/');
        $response->assertOk();

        $html = (string) $response->getContent();
        $pattern = '/<[a-z][a-z0-9]*\b[^>]*\sdata-area-panel(?=[\s>])[^>]*>/i';

        $this->assertSame(1, preg_match($pattern, $html, $match));
        $panel = $match[0] ?? '';

        // Blank quoted values so a "hidden" CSS class cannot pass as the native attribute.
        $attributes = preg_replace('/="[^"]*"/', '=""', $panel);
        $this->assertMatchesRegularExpression('/\shidden(?=[\s>]|$)/', $attributes, 'The panel must start with the native hidden attribute');
        $this->assertStringContainsString('aria-hidden="true"', $panel);
        $this->assertStringContainsString('aria-labelledby="area-panel-title"', $panel);

        // A dangling aria-labelledby is worse than none at all.
        $this->assertSame(1, preg_match('/<[a-z][a-z0-9]*\b[^>]*\sid="area-panel-title"(?=[\s>])[^>]*>/i', $html));
    }

    public function test_the_area_panel_actions_keep_the_save_flow_and_safe_route_link(): void
    {
        $response = $this->get('/');
        $response->assertOk();

        $html = (string) $response->getContent();

        $this->assertSame(1, preg_match('/<button\b[^>]*\sdata-area-save(?=[\s>])[^>]*>/i', $html, $save));
        $this->assertStringContainsString('data-action="add-place"', $save[0]);

        $this->assertSame(1, preg_match('/<a\b[^>]*\sdata-area-route(?=[\s>])[^>]*>/i', $html, $route));
        $this->assertStringContainsString('target="_blank"', $route[0]);
        $this->assertStringContainsString('rel="noopener noreferrer"', $route[0]);
    }

    public function test_home_mirrors_allowed_osm_tags_as_location_sheet_categories(): void
    {
        $expected = [
            'worship' => ['Tempat Ibadah', 'pin'],
            'education' => ['Sekolah', 'clipboard'],
            'bank' => ['Bank', 'bag'],
            'atm' => ['ATM', 'bag'],
            'postal' => ['Kantor Pos', 'mail'],
            'library' => ['Perpustakaan', 'clipboard'],
            'police' => ['Kepolisian', 'shield-check'],
            'park' => ['Taman', 'map'],
            'museum' => ['Museum', 'info'],
        ];

        $configured = [];
        foreach (config('needs.categories', []) as $category) {
            $configured[$category['id']] = $category;
        }

        foreach ($expected as $id => [$label, $icon]) {
            $this->assertArrayHasKey($id, $configured, $id.' must be exposed by config/needs.php');
            $this->assertSame($label, $configured[$id]['label']);
            $this->assertSame($icon, $configured[$id]['icon']);
        }

        foreach (['household', 'repair', 'culinary', 'retail', 'health'] as $id) {
            $this->assertArrayHasKey($id, $configured, $id.' must keep its original category');
        }

        $response = $this->get('/');
        $response->assertOk();

        $html = (string) $response->getContent();
        $this->assertSame(1, preg_match('/<select\b[^>]*\sid="location-category"(?=[\s>])[^>]*>(.*?)<\/select>/is', $html, $select));

        preg_match_all('/<option\b[^>]*\svalue="([^"]*)"(?=[\s>])[^>]*>(.*?)<\/option>/is', $select[1], $matches, PREG_SET_ORDER);
        $options = [];
        foreach ($matches as $match) {
            $options[$match[1]] = trim(strip_tags($match[2]));
        }

        foreach ($expected as $id => [$label]) {
            $this->assertArrayHasKey($id, $options, $id.' must be selectable when saving an OSM place');
            $this->assertSame($label, $options[$id]);
        }
    }
}

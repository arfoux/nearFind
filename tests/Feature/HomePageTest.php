<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomePageTest extends TestCase
{
    public function test_home_page_renders_map_and_dummy_needs(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('nearFind');
        $response->assertSee('id="map"', false);
        $response->assertSee('Jasa Laundry Kiloan');
        $response->assertSee('Jasa Servis AC');
        $response->assertSee('Ajukan Kebutuhan');
        $response->assertSee('OpenStreetMap');
        $response->assertSee('mapStyleUrl');
        $response->assertSee('tiles.openfreemap.org');
        $response->assertSee('id="login-gate"', false);
        $response->assertSee('Masuk untuk melanjutkan');
    }

    public function test_explore_and_report_pages_render_and_are_linked_from_the_nav(): void
    {
        $explore = $this->get('/jelajah');

        $explore->assertOk();
        $explore->assertSee('Kebutuhan di sekitarmu');
        $explore->assertSee('Jasa Laundry Kiloan');
        $explore->assertSee('data-needs-list', false);
        $explore->assertSee('data-list-search', false);
        $explore->assertSee(route('needs.index'), false);
        $explore->assertSee(route('needs.report'), false);
        $explore->assertSee('?need=laundry-kiloan', false);
        $explore->assertDontSee('nav-placeholder');
        $explore->assertDontSee('segera hadir');

        $report = $this->get('/laporan');

        $report->assertOk();
        $report->assertSee('Laporan kebutuhan warga');
        $report->assertSee('Per kategori');
        $report->assertSee('Paling didukung');
        $report->assertSee('Peluang usaha');
        $report->assertSee(route('needs.report'), false);
        $report->assertSee('?need=klinik-24-jam', false);

        $this->get('/profil')->assertRedirect(route('auth'));
    }

    public function test_the_profile_tab_points_guests_at_the_auth_page(): void
    {
        $response = $this->get('/');

        $response->assertOk();

        $this->assertSame(1, preg_match('/<a\b[^>]*data-nav-key="profil"[^>]*>/', (string) $response->getContent(), $match));
        $this->assertStringContainsString('href="'.route('auth').'"', $match[0]);
        $this->assertStringNotContainsString(route('profile.index'), $match[0]);
    }

    public function test_every_tab_shares_the_same_app_shell_and_nav_track(): void
    {
        $track = 'pointer-events-auto mx-auto flex w-full max-w-[560px] items-stretch justify-around px-2.5';

        foreach (['/', '/jelajah', '/laporan'] as $path) {
            $response = $this->get($path);

            $response->assertOk();
            $response->assertSee('data-app-shell', false);
            $response->assertSee('data-page-content', false);
            $response->assertSee('data-nav-link', false);
            $response->assertSee($track, false);
        }
    }
}

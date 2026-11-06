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

        $this->get('/profil')->assertRedirect(route('login'));
    }
}

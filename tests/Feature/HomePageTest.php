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

    public function test_only_planned_pages_are_unavailable(): void
    {
        $this->get('/jelajahi')->assertNotFound();
        $this->get('/laporan')->assertNotFound();
        $this->get('/profil')->assertRedirect(route('login'));
    }
}

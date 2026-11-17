<?php

namespace Tests\Feature;

use Database\Seeders\NeedSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoMetadataTest extends TestCase
{
    use RefreshDatabase;

    private const HOME_TITLE = 'nearFind: Peta Kebutuhan dan Fasilitas Terdekat di Sekitarmu';

    private const HOME_DESCRIPTION = 'Temukan kebutuhan warga dan fasilitas umum terdekat di sekitarmu lewat peta OpenStreetMap. Cari tempat ibadah, klinik, sekolah, kafe, bank, ATM, taman, dan peluang usaha lokal dekat sini.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NeedSeeder::class);
    }

    public function test_home_page_publishes_one_complete_set_of_search_metadata(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $html = $response->getContent();

        $this->assertSame(1, substr_count($html, '<title>'));
        $this->assertSame(1, substr_count($html, '<meta name="description"'));
        $this->assertSame(1, substr_count($html, '<link rel="canonical"'));
        $this->assertSame(1, substr_count($html, '<script type="application/ld+json">'));

        $this->assertStringContainsString('<title>'.self::HOME_TITLE.'</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="'.self::HOME_DESCRIPTION.'">', $html);
        $this->assertStringContainsString('<meta name="keywords" content="peta kebutuhan warga, peta fasilitas umum', $html);
        $this->assertStringContainsString('<meta name="robots" content="index,follow">', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://nf.nietzsc.de/">', $html);

        $this->assertStringContainsString('<meta property="og:title" content="'.self::HOME_TITLE.'">', $html);
        $this->assertStringContainsString('<meta property="og:description" content="'.self::HOME_DESCRIPTION.'">', $html);
        $this->assertStringContainsString('<meta property="og:type" content="website">', $html);
        $this->assertStringContainsString('<meta property="og:url" content="https://nf.nietzsc.de/">', $html);
        $this->assertStringContainsString('<meta property="og:site_name" content="nearFind">', $html);
        $this->assertStringContainsString('<meta property="og:locale" content="id_ID">', $html);
        $this->assertStringContainsString('<meta property="og:image" content="https://nf.nietzsc.de/apple-touch-icon.png">', $html);

        $this->assertStringContainsString('<meta name="twitter:card" content="summary">', $html);
        $this->assertStringContainsString('<meta name="twitter:title" content="'.self::HOME_TITLE.'">', $html);
        $this->assertStringContainsString('<meta name="twitter:image" content="https://nf.nietzsc.de/apple-touch-icon.png">', $html);

        $jsonLd = $this->jsonLdFrom($html);

        $this->assertSame('WebPage', $jsonLd['@type']);
        $this->assertSame(self::HOME_TITLE, $jsonLd['name']);
        $this->assertSame(self::HOME_DESCRIPTION, $jsonLd['description']);
        $this->assertSame('https://nf.nietzsc.de/', $jsonLd['url']);
        $this->assertSame('id', $jsonLd['inLanguage']);
        $this->assertSame('WebApplication', $jsonLd['isPartOf']['@type']);
        $this->assertSame('nearFind', $jsonLd['isPartOf']['name']);
        $this->assertSame('https://nf.nietzsc.de/', $jsonLd['isPartOf']['url']);
        $this->assertSame('Temukan kebutuhan warga dan fasilitas umum terdekat di sekitarmu lewat peta OpenStreetMap.', $jsonLd['isPartOf']['description']);
        $this->assertTrue($jsonLd['isPartOf']['isAccessibleForFree']);
        $this->assertContains('peta interaktif', $jsonLd['isPartOf']['featureList']);
        $this->assertContains('pencarian kebutuhan', $jsonLd['isPartOf']['featureList']);
        $this->assertContains('penanda lokasi', $jsonLd['isPartOf']['featureList']);
        $this->assertContains('tempat dari OpenStreetMap', $jsonLd['isPartOf']['featureList']);
    }

    public function test_explore_page_publishes_its_own_title_and_canonical(): void
    {
        $response = $this->get('/explore');

        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('<title>Jelajahi Kebutuhan Warga Terdekat · nearFind</title>', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://nf.nietzsc.de/explore">', $html);
        $this->assertStringContainsString('<meta property="og:url" content="https://nf.nietzsc.de/explore">', $html);
        $this->assertStringContainsString('<meta name="robots" content="index,follow">', $html);

        $this->assertSame('Jelajahi Kebutuhan Warga Terdekat · nearFind', $this->jsonLdFrom($html)['name']);
    }

    public function test_reports_page_publishes_its_own_title_and_canonical(): void
    {
        $response = $this->get('/reports');

        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('<title>Rekap Laporan Kebutuhan Warga Sekitarmu · nearFind</title>', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://nf.nietzsc.de/reports">', $html);
        $this->assertStringContainsString('<meta property="og:url" content="https://nf.nietzsc.de/reports">', $html);
        $this->assertStringContainsString('<meta name="robots" content="index,follow">', $html);

        $this->assertSame('Rekap Laporan Kebutuhan Warga Sekitarmu · nearFind', $this->jsonLdFrom($html)['name']);
    }

    public function test_auth_page_tells_crawlers_not_to_index_it(): void
    {
        $response = $this->get('/auth');

        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('<title>Masuk · nearFind</title>', $html);
        $this->assertStringContainsString('<meta name="robots" content="noindex,follow">', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://nf.nietzsc.de/auth">', $html);
        $this->assertStringNotContainsString('<meta name="robots" content="index,follow">', $html);
    }

    public function test_sitemap_lists_only_the_three_public_pages(): void
    {
        $sitemap = file_get_contents(public_path('sitemap.xml'));

        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $sitemap);
        $this->assertStringContainsString('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $sitemap);
        $this->assertSame(3, substr_count($sitemap, '<loc>'));
        $this->assertStringContainsString('<loc>https://nf.nietzsc.de/</loc>', $sitemap);
        $this->assertStringContainsString('<loc>https://nf.nietzsc.de/explore</loc>', $sitemap);
        $this->assertStringContainsString('<loc>https://nf.nietzsc.de/reports</loc>', $sitemap);
        $this->assertStringNotContainsString('/auth', $sitemap);
        $this->assertStringNotContainsString('/profile', $sitemap);
        $this->assertStringNotContainsString('<lastmod>', $sitemap);
        $this->assertStringNotContainsString('<priority>', $sitemap);
    }

    public function test_robots_txt_points_to_the_static_sitemap(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));

        $this->assertStringContainsString('User-agent: *', $robots);
        $this->assertStringContainsString('Disallow:', $robots);
        $this->assertStringContainsString('Sitemap: https://nf.nietzsc.de/sitemap.xml', $robots);
    }

    private function jsonLdFrom(string $html): array
    {
        $matches = [];
        $count = preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);

        $this->assertSame(1, $count, 'The page must contain one JSON-LD block.');

        return json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
    }
}

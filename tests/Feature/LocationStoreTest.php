<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Support\Seal\Sealer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationStoreTest extends TestCase
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
        ]);
    }

    public function test_a_guest_cannot_save_a_location(): void
    {
        $this->from(route('home'))->post(route('locations.store'), $this->payload())
            ->assertRedirect(route('login'));

        $this->assertSame(0, Location::count());
    }

    public function test_a_warga_can_save_a_location_and_lands_on_its_pin(): void
    {
        $response = $this->asWarga()->post(route('locations.store'), $this->payload());

        $response->assertRedirect(route('home', ['location' => 1]));
        $response->assertSessionHas('status');

        $this->assertSame(1, Location::count());

        $row = Location::firstOrFail();

        $this->assertSame('Warung Bu Sari', $row->name);
        $this->assertSame('retail', $row->category);
        $this->assertSame('Jl. Cihampelas 12', $row->address);
        $this->assertSame('Sukajadi', $row->area);
        $this->assertEqualsWithDelta(-6.92, (float) $row->latitude, 0.0000001);
        $this->assertEqualsWithDelta(107.61, (float) $row->longitude, 0.0000001);
        $this->assertSame('warga', $row->source);
        $this->assertSame(self::EMAIL, $row->author_email);
    }

    public function test_the_json_contract_returns_the_public_row_without_the_author(): void
    {
        $response = $this->asWarga()->post(route('locations.store'), $this->payload(), ['Accept' => 'application/json']);

        $response->assertOk();
        $data = $response->json();

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
        ], array_keys($data['location']));

        $this->assertSame('1', $data['location']['id']);
        $this->assertSame('warga', $data['location']['source']);
        $this->assertIsFloat($data['location']['latitude']);
        $this->assertIsFloat($data['location']['longitude']);
        $this->assertIsString($data['location']['created_at']);
        $this->assertSame(route('home', ['location' => 1]), $data['redirect']);
        $this->assertArrayNotHasKey('author_email', $data['location']);
    }

    public function test_a_nearby_pin_with_the_same_name_reuses_the_row(): void
    {
        $this->asWarga()->post(route('locations.store'), $this->payload());

        // ~7 metres away, with different casing and doubled whitespace.
        $second = $this->asWarga()->post(route('locations.store'), $this->payload([
            'name' => '  warung   bu sari ',
            'latitude' => -6.92006,
            'longitude' => 107.61005,
            'address' => 'Alamat lain',
        ]));

        $second->assertRedirect(route('home', ['location' => 1]));
        $this->assertSame(1, Location::count());
        $this->assertSame('Jl. Cihampelas 12', Location::firstOrFail()->address);
    }

    public function test_the_same_name_far_away_creates_a_new_row(): void
    {
        $this->asWarga()->post(route('locations.store'), $this->payload());

        $response = $this->asWarga()->post(route('locations.store'), $this->payload([
            'latitude' => -6.95,
            'longitude' => 107.65,
        ]));

        $response->assertRedirect(route('home', ['location' => 2]));
        $this->assertSame(2, Location::count());
    }

    public function test_the_category_must_be_a_configured_one(): void
    {
        $this->asWarga()->from(route('home'))->post(route('locations.store'), $this->payload([
            'category' => 'unknown',
        ]))
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors('category');

        $this->assertSame(0, Location::count());
    }

    public function test_a_pin_is_required(): void
    {
        $this->asWarga()->from(route('home'))->post(route('locations.store'), [
            'name' => 'Warung Bu Sari',
            'category' => 'retail',
        ])
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors(['latitude', 'longitude']);

        $this->assertSame(0, Location::count());
    }

    public function test_the_edge_route_saves_the_same_row(): void
    {
        $response = $this->asWarga()->post(route('edge.locations.store'), $this->payload());

        $response->assertRedirect(route('home', ['location' => 1]));
        $this->assertSame(1, Location::count());
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

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Warung Bu Sari',
            'category' => 'retail',
            'address' => 'Jl. Cihampelas 12',
            'area' => 'Sukajadi',
            'latitude' => -6.92,
            'longitude' => 107.61,
        ], $overrides);
    }
}

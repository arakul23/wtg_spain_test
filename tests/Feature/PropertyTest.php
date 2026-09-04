<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\Property;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyTest extends TestCase
{
    use RefreshDatabase;

    private function baseParams(array $overrides = []): array
    {
        return array_merge([
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'guests' => 2,
        ], $overrides);
    }

    public function test_it_requires_check_in_check_out_and_guests(): void
    {
        $response = $this->getJson('/api/properties');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['check_in', 'check_out', 'guests']);
    }

    public function test_it_returns_offer_matching_exact_dates(): void
    {
        $supplier = Supplier::factory()->create();
        $property = Property::factory()->create();

        Offer::factory()->create([
            'supplier_id' => $supplier->id,
            'property_id' => $property->id,
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'max_guests' => 4,
            'available_units' => 2,
            'expires_at' => now()->addMonth(),
        ]);

        $response = $this->getJson('/api/properties?' . http_build_query($this->baseParams()));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', $property->code);
    }

    public function test_it_excludes_offer_with_insufficient_max_guests(): void
    {
        $supplier = Supplier::factory()->create();
        $property = Property::factory()->create();

        Offer::factory()->create([
            'supplier_id' => $supplier->id,
            'property_id' => $property->id,
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'max_guests' => 1, // менше, ніж guests=2
            'available_units' => 2,
            'expires_at' => now()->addMonth(),
        ]);

        $response = $this->getJson('/api/properties?' . http_build_query($this->baseParams()));

        $response->assertStatus(200)->assertJsonCount(0, 'data');
    }

    public function test_it_excludes_offer_with_zero_available_units(): void
    {
        $supplier = Supplier::factory()->create();
        $property = Property::factory()->create();

        Offer::factory()->soldOut()->create([
            'supplier_id' => $supplier->id,
            'property_id' => $property->id,
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'max_guests' => 4,
            'expires_at' => now()->addMonth(),
        ]);

        $response = $this->getJson('/api/properties?' . http_build_query($this->baseParams()));

        $response->assertStatus(200)->assertJsonCount(0, 'data');
    }

    public function test_it_excludes_expired_offer(): void
    {
        $supplier = Supplier::factory()->create();
        $property = Property::factory()->create();

        Offer::factory()->expired()->create([
            'supplier_id' => $supplier->id,
            'property_id' => $property->id,
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'max_guests' => 4,
            'available_units' => 2,
        ]);

        $response = $this->getJson('/api/properties?' . http_build_query($this->baseParams()));

        $response->assertStatus(200)->assertJsonCount(0, 'data');
    }

    public function test_it_filters_by_city_when_provided(): void
    {
        $supplier = Supplier::factory()->create();

        $barcelona = Property::factory()->create(['city' => 'Barcelona']);
        $odesa = Property::factory()->create(['city' => 'Odesa']);

        foreach ([$barcelona, $odesa] as $property) {
            Offer::factory()->create([
                'supplier_id' => $supplier->id,
                'property_id' => $property->id,
                'check_in' => '2026-10-10',
                'check_out' => '2026-10-15',
                'max_guests' => 4,
                'available_units' => 2,
                'expires_at' => now()->addMonth(),
            ]);
        }

        $response = $this->getJson('/api/properties?' . http_build_query(
            $this->baseParams(['city' => 'Barcelona']),
        ));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.city', 'Barcelona');
    }

    public function test_it_returns_all_cities_when_city_not_provided(): void
    {
        $supplier = Supplier::factory()->create();

        foreach (['Barcelona', 'Odesa'] as $city) {
            $property = Property::factory()->create(['city' => $city]);
            Offer::factory()->create([
                'supplier_id' => $supplier->id,
                'property_id' => $property->id,
                'check_in' => '2026-10-10',
                'check_out' => '2026-10-15',
                'max_guests' => 4,
                'available_units' => 2,
                'expires_at' => now()->addMonth(),
            ]);
        }

        $response = $this->getJson('/api/properties?' . http_build_query($this->baseParams()));

        $response->assertStatus(200)->assertJsonCount(2, 'data');
    }

    public function test_it_returns_cheapest_offer_per_property(): void
    {
        $supplierA = Supplier::factory()->create();
        $supplierB = Supplier::factory()->create();
        $property = Property::factory()->create();

        $expensive = Offer::factory()->create([
            'supplier_id' => $supplierA->id,
            'property_id' => $property->id,
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'max_guests' => 4,
            'available_units' => 2,
            'price' => 90000,
            'expires_at' => now()->addMonth(),
        ]);

        $cheap = Offer::factory()->create([
            'supplier_id' => $supplierB->id,
            'property_id' => $property->id,
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'max_guests' => 4,
            'available_units' => 2,
            'price' => 50000,
            'expires_at' => now()->addMonth(),
        ]);

        $response = $this->getJson('/api/properties?' . http_build_query($this->baseParams()));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.best_offer.id', $cheap->id)
            ->assertJsonPath('data.0.best_offer.price', 50000);
    }

    public function test_results_are_sorted_by_price_ascending(): void
    {
        $supplier = Supplier::factory()->create();

        $prices = [80000, 30000, 55000];
        foreach ($prices as $price) {
            $property = Property::factory()->create();
            Offer::factory()->create([
                'supplier_id' => $supplier->id,
                'property_id' => $property->id,
                'check_in' => '2026-10-10',
                'check_out' => '2026-10-15',
                'max_guests' => 4,
                'available_units' => 2,
                'price' => $price,
                'expires_at' => now()->addMonth(),
            ]);
        }

        $response = $this->getJson('/api/properties?' . http_build_query($this->baseParams()));

        $response->assertStatus(200);
        $returnedPrices = array_column(array_column($response->json('data'), 'best_offer'), 'price');

        $this->assertSame([30000, 55000, 80000], $returnedPrices);
    }

    public function test_pagination_returns_per_page_and_next_link(): void
    {
        $supplier = Supplier::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $property = Property::factory()->create();
            Offer::factory()->create([
                'supplier_id' => $supplier->id,
                'property_id' => $property->id,
                'check_in' => '2026-10-10',
                'check_out' => '2026-10-15',
                'max_guests' => 4,
                'available_units' => 2,
                'expires_at' => now()->addMonth(),
            ]);
        }

        $response = $this->getJson('/api/properties?' . http_build_query(
            $this->baseParams(['per_page' => 2, 'page' => 1]),
        ));

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('per_page', 2);

        $this->assertNotNull($response->json('next'));
        $this->assertNull($response->json('prev'));
    }
}
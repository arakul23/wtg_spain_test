<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\Offer;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'client_reference' => 'web-order-9f782b1c',
            'customer_name' => 'John Smith',
            'customer_email' => 'john@example.com',
        ], $overrides);
    }

    public function test_it_creates_reservation_and_returns_201(): void
    {
        $offer = Offer::factory()->create(['available_units' => 2]);

        $response = $this->postJson(
            "/api/offers/{$offer->id}/reservations",
            $this->validPayload(),
        );

        $response->assertStatus(201)
            ->assertJsonPath('data.client_reference', 'web-order-9f782b1c')
            ->assertJsonPath('data.customer_name', 'John Smith')
            ->assertJsonPath('data.customer_email', 'john@example.com')
            ->assertJsonPath('data.offer_id', $offer->id);

        $this->assertDatabaseHas('reservations', [
            'offer_id' => $offer->id,
            'client_reference' => 'web-order-9f782b1c',
        ]);
    }

    public function test_it_decrements_available_units(): void
    {
        $offer = Offer::factory()->create(['available_units' => 2]);

        $this->postJson("/api/offers/{$offer->id}/reservations", $this->validPayload());

        $this->assertSame(1, $offer->fresh()->available_units);
    }

    public function test_it_rejects_missing_required_fields(): void
    {
        $offer = Offer::factory()->create(['available_units' => 2]);

        $response = $this->postJson("/api/offers/{$offer->id}/reservations", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['client_reference', 'customer_name', 'customer_email']);
    }

    public function test_it_rejects_invalid_email(): void
    {
        $offer = Offer::factory()->create(['available_units' => 2]);

        $response = $this->postJson(
            "/api/offers/{$offer->id}/reservations",
            $this->validPayload(['customer_email' => 'not-an-email']),
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['customer_email']);
    }

    public function test_it_rejects_duplicate_client_reference(): void
    {
        $offerOne = Offer::factory()->create(['available_units' => 2]);
        $offerTwo = Offer::factory()->create(['available_units' => 2]);

        Reservation::factory()->create([
            'offer_id' => $offerOne->id,
            'client_reference' => 'web-order-9f782b1c',
        ]);

        $response = $this->postJson(
            "/api/offers/{$offerTwo->id}/reservations",
            $this->validPayload(),
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['client_reference']);
    }

    public function test_it_returns_409_when_no_units_available(): void
    {
        $offer = Offer::factory()->soldOut()->create();

        $response = $this->postJson(
            "/api/offers/{$offer->id}/reservations",
            $this->validPayload(),
        );

        $response->assertStatus(409);

        $this->assertDatabaseMissing('reservations', [
            'client_reference' => 'web-order-9f782b1c',
        ]);
    }

    public function test_it_does_not_decrement_units_when_reservation_fails(): void
    {
        $offer = Offer::factory()->soldOut()->create();

        $this->postJson("/api/offers/{$offer->id}/reservations", $this->validPayload());

        $this->assertSame(0, $offer->fresh()->available_units);
    }

    public function test_it_returns_404_for_unknown_offer(): void
    {
        $response = $this->postJson('/api/offers/999999/reservations', $this->validPayload());

        $response->assertStatus(404);
    }

    public function test_reservation_defaults_to_confirmed_status(): void
    {
        $offer = Offer::factory()->create(['available_units' => 2]);

        $this->postJson("/api/offers/{$offer->id}/reservations", $this->validPayload());

        $reservation = Reservation::first();

        $this->assertSame(ReservationStatus::CONFIRMED, $reservation->status);
    }
}
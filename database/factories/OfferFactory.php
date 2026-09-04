<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Offer;
use App\Models\Property;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    protected $model = Offer::class;

    public function definition(): array
    {
        $checkIn = $this->faker->dateTimeBetween('+1 week', '+2 weeks');
        $checkOut = (clone $checkIn)->modify('+5 days');

        return [
            'supplier_id' => Supplier::factory(),
            'property_id' => Property::factory(),
            'import_id' => null,
            'external_id' => $this->faker->unique()->uuid(),
            'check_in' => $checkIn->format('Y-m-d'),
            'check_out' => $checkOut->format('Y-m-d'),
            'max_guests' => $this->faker->numberBetween(1, 6),
            'price' => $this->faker->numberBetween(10000, 200000),
            'currency' => 'EUR',
            'available_units' => $this->faker->numberBetween(1, 5),
            'expires_at' => now()->addDays(30),
        ];
    }

    /**
     * Оффер без вільних юнітів — зручно для тестів бронювання.
     */
    public function soldOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'available_units' => 0,
        ]);
    }

    /**
     * Прострочений оффер — не повинен потрапляти у видачу пошуку.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }
}
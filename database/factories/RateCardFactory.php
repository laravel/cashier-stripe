<?php

namespace Laravel\Cashier\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Laravel\Cashier\RateCard;

class RateCardFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = RateCard::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Default Rate Card',
            'product_id' => 'prod_'.fake()->bothify('??????????'),
            'pricing_type' => 'flat',
            'currency' => config('cashier.currency', 'usd'),
            'rates' => ['unit_amount' => 100],
            'metadata' => null,
            'is_active' => true,
            'effective_from' => now(),
            'effective_until' => null,
        ];
    }

    /**
     * Create a tiered rate card.
     *
     * @return $this
     */
    public function tiered(): static
    {
        return $this->state([
            'pricing_type' => 'tiered',
            'rates' => [
                'mode' => 'graduated',
                'tiers' => [
                    ['up_to' => 100, 'unit_amount' => 50, 'flat_amount' => 0],
                    ['up_to' => null, 'unit_amount' => 30, 'flat_amount' => 0],
                ],
            ],
        ]);
    }

    /**
     * Create a package rate card.
     *
     * @return $this
     */
    public function package(): static
    {
        return $this->state([
            'pricing_type' => 'package',
            'rates' => [
                'package_size' => 10,
                'package_price' => 500,
            ],
        ]);
    }

    /**
     * Mark the rate card as inactive.
     *
     * @return $this
     */
    public function inactive(): static
    {
        return $this->state([
            'is_active' => false,
            'effective_until' => now(),
        ]);
    }
}

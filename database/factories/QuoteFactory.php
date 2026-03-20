<?php

namespace Laravel\Cashier\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Quote;

class QuoteFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Quote::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $model = Cashier::$customerModel;

        return [
            (new $model)->getForeignKey() => ($model)::factory(),
            'stripe_id' => 'qt_'.Str::random(40),
            'status' => 'draft',
            'number' => null,
            'amount_subtotal' => null,
            'amount_total' => null,
            'currency' => config('cashier.currency', 'usd'),
            'expires_at' => null,
            'finalized_at' => null,
            'accepted_at' => null,
            'canceled_at' => null,
        ];
    }

    /**
     * Mark the quote as open (finalized).
     *
     * @return $this
     */
    public function open(): static
    {
        return $this->state([
            'status' => 'open',
            'finalized_at' => now(),
        ]);
    }

    /**
     * Mark the quote as accepted.
     *
     * @return $this
     */
    public function accepted(): static
    {
        return $this->state([
            'status' => 'accepted',
            'finalized_at' => now(),
            'accepted_at' => now(),
        ]);
    }

    /**
     * Mark the quote as canceled.
     *
     * @return $this
     */
    public function canceled(): static
    {
        return $this->state([
            'status' => 'canceled',
            'canceled_at' => now(),
        ]);
    }
}

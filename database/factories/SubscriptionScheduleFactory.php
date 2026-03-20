<?php

namespace Laravel\Cashier\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\SubscriptionSchedule;

class SubscriptionScheduleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SubscriptionSchedule::class;

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
            'type' => 'default',
            'stripe_id' => 'sub_sched_'.Str::random(40),
            'stripe_status' => 'not_started',
            'subscription_id' => null,
            'current_phase_started_at' => null,
            'current_phase_ends_at' => null,
            'canceled_at' => null,
            'completed_at' => null,
            'released_at' => null,
        ];
    }

    /**
     * Mark the schedule as active.
     *
     * @return $this
     */
    public function active(): static
    {
        return $this->state([
            'stripe_status' => 'active',
            'current_phase_started_at' => now(),
            'current_phase_ends_at' => now()->addMonth(),
        ]);
    }

    /**
     * Mark the schedule as completed.
     *
     * @return $this
     */
    public function completed(): static
    {
        return $this->state([
            'stripe_status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark the schedule as canceled.
     *
     * @return $this
     */
    public function canceled(): static
    {
        return $this->state([
            'stripe_status' => 'canceled',
            'canceled_at' => now(),
        ]);
    }

    /**
     * Mark the schedule as released.
     *
     * @return $this
     */
    public function released(): static
    {
        return $this->state([
            'stripe_status' => 'released',
            'released_at' => now(),
        ]);
    }
}

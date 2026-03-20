<?php

namespace Laravel\Cashier\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\UsageThreshold;

class UsageThresholdFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = UsageThreshold::class;

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
            'metric' => 'api_calls',
            'threshold' => 1000,
            'alert_options' => null,
        ];
    }
}

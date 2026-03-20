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
            'meter_id' => 'meter_'.$this->faker->word(),
            'threshold' => 1000,
            'period' => 'billing_cycle',
            'alert_options' => null,
        ];
    }
}

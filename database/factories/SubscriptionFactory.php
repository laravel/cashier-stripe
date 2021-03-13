<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Laravel\Cashier\Subscription;
use Stripe\Subscription as StripeSubscription;

class SubscriptionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Subscription::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $model = config('cashier.model');

        return [
            (new $model)->getForeignKey() => ($model)::factory(),
            'name' => $this->faker->title,
            'stripe_id' => 'sub_' . Str::random(40),
            'stripe_status' => StripeSubscription::STATUS_ACTIVE,
            'stripe_plan' => null,
            'quantity' => null,
            'trial_ends_at' => null,
            'ends_at' => null
        ];
    }

    /**
     * Add a plan identifier to the model
     *
     * @param  string  $plan
     * @return $this
     */
    public function withPlan($plan)
    {
        return $this->state([
            'stripe_plan' => $plan
        ]);
    }
}

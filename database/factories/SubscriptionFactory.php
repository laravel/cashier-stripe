<?php

namespace Laravel\Cashier\Database\Factories;

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

    /**
     * Mark the subscription as active
     *
     * @return $this
     */
    public function active()
    {
        return $this->state([
            'stripe_status' => StripeSubscription::STATUS_ACTIVE
        ]);
    }

    /**
     * Mark the subscription as canceled
     *
     * @return $this
     */
    public function canceled()
    {
        return $this->state([
            'stripe_status' => StripeSubscription::STATUS_CANCELED
        ]);
    }

    /**
     * Mark the subscription as incomplete
     *
     * @return $this
     */
    public function incomplete()
    {
        return $this->state([
            'stripe_status' => StripeSubscription::STATUS_INCOMPLETE
        ]);
    }

    /**
     * Mark the subscription as incomplete where the allowed completion period has expired
     *
     * @return $this
     */
    public function incompleteAndExpired()
    {
        return $this->state([
            'stripe_status' => StripeSubscription::STATUS_INCOMPLETE_EXPIRED
        ]);
    }

    /**
     * Mark the subscription as being past the due date
     *
     * @return $this
     */
    public function pastDue()
    {
        return $this->state([
            'stripe_status' => StripeSubscription::STATUS_PAST_DUE
        ]);
    }

    /**
     * Mark the subscription as being in trial mode
     *
     * @return $this
     */
    public function trial()
    {
        return $this->state([
            'stripe_status' => StripeSubscription::STATUS_TRIALING
        ]);
    }

    /**
     * Mark the subscription as unpaid
     *
     * @return $this
     */
    public function unpaid()
    {
        return $this->state([
            'stripe_status' => StripeSubscription::STATUS_UNPAID
        ]);
    }
}

<?php

namespace Laravel\Cashier\Database\Factories;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Laravel\Cashier\Cashier;
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
        $model = Cashier::$customerModel;

        return [
            (new $model)->getForeignKey() => ($model)::factory(),
            'name' => 'default',
            'stripe_id' => 'sub_'.Str::random(40),
            'stripe_status' => StripeSubscription::STATUS_ACTIVE,
            'stripe_price' => null,
            'quantity' => null,
            'trial_ends_at' => null,
            'ends_at' => null,
        ];
    }

    /**
     * Add a price identifier to the model.
     *
     * @param  string  $price
     * @return $this
     */
    public function withPrice($price)
    {
        return $this->state([
            'stripe_price' => $price,
        ]);
    }

    /**
     * Mark the subscription as active.
     *
     * @return $this
     */
    public function active()
    {
        return $this->state([
            'stripe_status' => StripeSubscription::STATUS_ACTIVE,
        ]);
    }

    /**
     * Mark the subscription as being within a trial period.
     *
     * @param  \DateTimeInterface  $trialEndsAt
     * @return $this
     */
    public function trialing(DateTimeInterface $trialEndsAt = null)
    {
        return $this->state([
            'stripe_status' => StripeSubscription::STATUS_TRIALING,
            'trial_ends_at' => $trialEndsAt,
        ]);
    }

    /**
     * Mark the subscription as canceled.
     *
     * @return $this
     */
    public function canceled()
    {
        return $this->state([
            'stripe_status' => StripeSubscription::STATUS_CANCELED,
        ]);
    }

    /**
     * Mark the subscription as incomplete.
     *
     * @return $this
     */
    public function incomplete()
    {
        return $this->state([
            'stripe_status' => StripeSubscription::STATUS_INCOMPLETE,
        ]);
    }

    /**
     * Mark the subscription as incomplete where the allowed completion period has expired.
     *
     * @return $this
     */
    public function incompleteAndExpired()
    {
        return $this->state([
            'stripe_status' => StripeSubscription::STATUS_INCOMPLETE_EXPIRED,
        ]);
    }

    /**
     * Mark the subscription as being past the due date.
     *
     * @return $this
     */
    public function pastDue()
    {
        return $this->state([
            'stripe_status' => StripeSubscription::STATUS_PAST_DUE,
        ]);
    }

    /**
     * Mark the subscription as unpaid.
     *
     * @return $this
     */
    public function unpaid()
    {
        return $this->state([
            'stripe_status' => StripeSubscription::STATUS_UNPAID,
        ]);
    }
}

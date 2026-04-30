<?php

namespace Laravel\Cashier\Concerns;

use Laravel\Cashier\SubscriptionSchedule;
use Laravel\Cashier\SubscriptionScheduleBuilder;

trait ManagesSubscriptionSchedules
{
    /**
     * Get all of the subscription schedules for the Stripe model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function subscriptionSchedules()
    {
        return $this->hasMany(SubscriptionSchedule::class, $this->getForeignKey())->orderBy('created_at', 'desc');
    }

    /**
     * Get a subscription schedule instance by Stripe ID.
     *
     * @param  string  $subscriptionScheduleId
     * @return \Laravel\Cashier\SubscriptionSchedule|null
     */
    public function findSubscriptionSchedule($subscriptionScheduleId)
    {
        return $this->subscriptionSchedules()->where('stripe_id', $subscriptionScheduleId)->first();
    }

    /**
     * Begin creating a new subscription schedule.
     *
     * @return \Laravel\Cashier\SubscriptionScheduleBuilder
     */
    public function newSubscriptionSchedule()
    {
        return new SubscriptionScheduleBuilder($this);
    }
}

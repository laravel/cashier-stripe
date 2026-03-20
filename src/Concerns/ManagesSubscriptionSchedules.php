<?php

namespace Laravel\Cashier\Concerns;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\SubscriptionScheduleBuilder;

trait ManagesSubscriptionSchedules
{
    /**
     * Get all of the subscription schedules for the Stripe model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function subscriptionSchedules(): HasMany
    {
        return $this->hasMany(Cashier::$subscriptionScheduleModel, $this->getForeignKey())
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get a subscription schedule by its type.
     *
     * @param  string  $type
     * @return \Laravel\Cashier\SubscriptionSchedule|null
     */
    public function subscriptionSchedule(string $type = 'default')
    {
        return $this->subscriptionSchedules->where('type', $type)->first();
    }

    /**
     * Get a subscription schedule instance by Stripe ID.
     *
     * @param  string  $scheduleId
     * @return \Laravel\Cashier\SubscriptionSchedule|null
     */
    public function findSubscriptionSchedule(string $scheduleId)
    {
        return $this->subscriptionSchedules()->where('stripe_id', $scheduleId)->first();
    }

    /**
     * Begin creating a new subscription schedule.
     *
     * @param  string  $type
     * @return \Laravel\Cashier\SubscriptionScheduleBuilder
     */
    public function newSubscriptionSchedule(string $type = 'default'): SubscriptionScheduleBuilder
    {
        return new SubscriptionScheduleBuilder($this, $type);
    }
}

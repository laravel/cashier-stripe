<?php

namespace Laravel\Cashier\Gateway;

use DateTimeInterface;
use Laravel\Cashier\Subscription;

abstract class SubscriptionManager
{
    /**
     * The subscription being managed.
     *
     * @var \Laravel\Cashier\Subscription
     */
    protected $subscription;

    /**
     * The date on which the billing cycle should be anchored.
     *
     * @var string|null
     */
    protected $billingCycleAnchor = null;

    /**
     * Indicates if the plan change should be prorated.
     *
     * @var bool
     */
    protected $prorate = true;

    /**
     * Constructor
     *
     * @param \Laravel\Cashier\Subscription $subscription
     */
    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
    }

    /**
     * Change the billing cycle anchor on a plan change.
     *
     * @param  \DateTimeInterface|int|string  $date
     * @return \Laravel\Cashier\Subscription
     */
    public function anchorBillingCycleOn($date = 'now')
    {
        if ($date instanceof DateTimeInterface) {
            $date = $date->getTimestamp();
        }

        $this->billingCycleAnchor = $date;

        return $this->subscription;
    }

    /**
     * Indicate that the plan change should not be prorated.
     *
     * @return \Laravel\Cashier\Subscription
     */
    public function noProrate()
    {
        $this->prorate = false;

        return $this->subscription;
    }

    abstract public function swap($plan);

    abstract public function cancel();

    abstract public function cancelNow();

    abstract public function resume();
}

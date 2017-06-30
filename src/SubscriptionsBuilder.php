<?php

namespace Laravel\Cashier;

class SubscriptionsBuilder extends SubscriptionBuilder
{
    /**
     * Array of plans being subscribed too.
     *
     * @var array
     */
    protected $plans = [];

    /**
     * Array of the plans being subscribed too.
     *
     * @var array
     */
    protected $names = [];

    /**
     * SubscriptionsBuilder constructor.
     * @param mixed $owner
     * @param array $plans
     * @param string $misc [IGNORE]
     */
    public function __construct($owner, array $plans, $misc)
    {
        $this->owner = $owner;
        $this->_processPlans($plans);
    }

    /**
     * Create a new combined Stripe subscriptions.
     *
     * @param  string|null  $token
     * @param  array  $options
     * @return \Laravel\Cashier\Subscription
     */
    public function create($token = null, array $options = [])
    {
        $customer = $this->getStripeCustomer($token, $options);
        $subscription = $customer->subscriptions->create($this->buildPayload());

        if ($this->skipTrial) {
            $trialEndsAt = null;
        } else {
            $trialEndsAt = $this->trialExpires;
        }

        $lastItem = null;
        foreach ($this->names as $name) {
            $tmpItem = [
                'name' => $name['name'],
                'stripe_id' => $subscription->id,
                'stripe_plan' => $name['plan'],
                'quantity' => $name['quantity'],
                'trial_ends_at' => $trialEndsAt,
                'ends_at' => null,
            ];
            // Seems to struggle when bundling multiple items through create?
            $lastItem = $this->owner->subscriptions()->create($tmpItem);
        }

        return $lastItem;
    }

    /**
     * Process data into the assoc arrays.
     *
     * @param array $plans
     */
    protected function _processPlans(array $plans)
    {
        if (! empty($plans)) {
            foreach ($plans as $plan) {
                if (! isset($plan['plan'])) {
                    continue;
                }

                if (! isset($plan['quantity'])) {
                    continue;
                }

                if (! isset($plan['subscription'])) {
                    continue;
                }

                $this->plans[] = [
                    'plan' => $plan['plan'],
                    'quantity' => $plan['quantity'],
                ];
                $this->names[] = [
                    'name' => $plan['subscription'],
                    'plan' => $plan['plan'],
                    'quantity' => $plan['quantity'],
                ];
            }
        }
    }

    /**
     * Overwrite Payload class.
     *
     * @return array
     */
    protected function buildPayload()
    {
        return array_filter([
            'coupon' => $this->coupon,
            'trial_end' => $this->getTrialEndForPayload(),
            'tax_percent' => $this->getTaxPercentageForPayload(),
            'metadata' => $this->metadata,
            'items' => $this->plans,
        ]);
    }
}

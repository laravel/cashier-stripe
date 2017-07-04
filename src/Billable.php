<?php

namespace Laravel\Cashier;

use Carbon\Carbon;
use Closure;
use Illuminate\Support\Collection;
use Laravel\Cashier\Gateway\Invoice;
use Laravel\Cashier\Gateway\Stripe\Card;
use Stripe\Error\InvalidRequest as StripeErrorInvalidRequest;
use Stripe\Invoice as StripeInvoice;

/**
 * Trait Billable
 *
 * @package Laravel\Cashier
 * @mixin \Illuminate\Database\Eloquent\Model
 * @property-read \Laravel\Cashier\Subscription[]|Collection $subscriptions
 */
trait Billable
{
    use UsesPaymentGateway;

    /**
     * Assigned billing manager.
     *
     * @var \Laravel\Cashier\Gateway\BillingManager
     */
    protected $billingManager;

    /**
     * Make a "one off" charge on the customer for the given amount.
     *
     * @param  int $amount
     * @param  array $options
     * @return mixed
     */
    public function charge($amount, array $options = [])
    {
        return $this->getBillingManager()->charge($amount, $options);
    }

    /**
     * Refund a customer for a charge.
     *
     * @param  string $charge
     * @param  array $options
     * @return mixed
     */
    public function refund($charge, array $options = [])
    {
        return $this->getBillingManager()->refund($charge, $options);
    }

    /**
     * Determines if the customer currently has a card on file.
     *
     * @return bool
     */
    public function hasCardOnFile()
    {
        return (bool) $this->card_brand;
    }

    /**
     * Add an invoice item to the customer's upcoming invoice.
     *
     * @param  string $description
     * @param  int $amount
     * @param  array $options
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public function tab($description, $amount, array $options = [])
    {
        return $this->getBillingManager()->tab($description, $amount, $options);
    }

    /**
     * Invoice the customer for the given amount and generate an invoice immediately.
     *
     * @param  string $description
     * @param  int $amount
     * @param  array $options
     * @return mixed
     */
    public function invoiceFor($description, $amount, array $options = [])
    {
        return $this->getBillingManager()->invoiceFor($description, $amount, $options);
    }

    /**
     * Get the Stripe supported currency used by the entity.
     *
     * @return string
     */
    public function preferredCurrency()
    {
        return Cashier::usesCurrency();
    }

    /**
     * Begin creating a new subscription.
     *
     * @param  string $subscription
     * @param  string $plan
     * @return \Laravel\Cashier\Gateway\SubscriptionBuilder
     */
    public function newSubscription($subscription, $plan)
    {
        $this->getGateway()->buildSubscription($this, $subscription, $plan);
    }

    /**
     * Determine if the Stripe model is on trial.
     *
     * @param  string  $subscriptionName
     * @param  string|null  $plan
     * @return bool
     */
    public function onTrial($subscriptionName = 'default', $plan = null)
    {
        if (func_num_args() === 0 && $this->onGenericTrial()) {
            return true;
        }

        $subscription = $this->subscription($subscriptionName);

        if (null === $plan) {
            return $subscription && $subscription->onTrial();
        }

        return $subscription && $subscription->onTrial() && $subscription->payment_gateway_plan === $plan;
    }

    /**
     * Determine if the Stripe model is on a "generic" trial at the model level.
     *
     * @return bool
     */
    public function onGenericTrial()
    {
        return $this->trial_ends_at && Carbon::now()->lt($this->trial_ends_at);
    }

    /**
     * Get a subscription instance by name.
     *
     * @param  string $subscriptionName
     * @return \Laravel\Cashier\Subscription|null
     */
    public function subscription($subscriptionName = 'default')
    {
        return $this->subscriptions->sortByDesc(function ($subscription) {
            return $subscription->created_at->getTimestamp();
        })->first(function ($subscription) use ($subscriptionName) {
            return $subscription->name === $subscriptionName;
        });
    }

    /**
     * Determine if the Stripe model has a given subscription.
     *
     * @param  string $subscriptionName
     * @param  string|null $plan
     * @return bool
     */
    public function subscribed($subscriptionName = 'default', $plan = null)
    {
        $subscription = $this->subscription($subscriptionName);

        if (null === $subscription) {
            return false;
        }

        if (null === $plan) {
            return $subscription->valid();
        }

        return $subscription->valid() && $subscription->payment_gateway_plan === $plan;
    }

    /**
     * Get all of the subscriptions for the Stripe model.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, $this->getForeignKey())->orderBy('created_at', 'desc');
    }

    /**
     * Find an invoice or throw a 404 error.
     *
     * @param  string $id
     * @return \Laravel\Cashier\Gateway\Invoice
     */
    public function findInvoiceOrFail($id)
    {
        return $this->getBillingManager()->findInvoiceOrFail($id);
    }

    /**
     * Find an invoice by ID.
     *
     * @param  string $id
     * @return Invoice|null
     */
    public function findInvoice($id)
    {
        return $this->getBillingManager()->findInvoice($id);
    }

    /**
     * Create an invoice download Response.
     *
     * @param  string  $id
     * @param  array  $data
     * @param  string  $storagePath
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function downloadInvoice($id, array $data, $storagePath = null)
    {
        return $this->getBillingManager()->downloadInvoice($id, $data, $storagePath);
    }

    /**
     * Get an array of the entity's invoices.
     *
     * @param  array $parameters
     * @return \Illuminate\Support\Collection
     */
    public function invoicesIncludingPending(array $parameters = [])
    {
        return $this->invoices(true, $parameters);
    }

    /**
     * Get a collection of the entity's invoices.
     *
     * @param  bool $includePending
     * @param  array $parameters
     * @return \Illuminate\Support\Collection
     */
    public function invoices($includePending = false, $parameters = [])
    {
        return $this->getBillingManager()->invoices($includePending, $parameters);
    }

    /**
     * Apply a coupon to the billable entity.
     *
     * @param  string $coupon
     * @return void
     */
    public function applyCoupon($coupon)
    {
        $customer = $this->asStripeCustomer();

        $customer->coupon = $coupon;

        $customer->save();
    }

    /**
     * Determine if the Stripe model is actively subscribed to one of the given plans.
     *
     * @param  array|string $plans
     * @param  string $subscription
     * @return bool
     */
    public function subscribedToPlan($plans, $subscription = 'default')
    {
        $subscription = $this->subscription($subscription);

        if (! $subscription || ! $subscription->valid()) {
            return false;
        }

        foreach ((array) $plans as $plan) {
            if ($subscription->payment_gateway_plan === $plan) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the entity is on the given plan.
     *
     * @param  string $plan
     * @return bool
     */
    public function onPlan($plan)
    {
        return ! is_null($this->subscriptions->first(function (Subscription $subscription) use ($plan) {
            return $subscription->payment_gateway_plan === $plan && $subscription->valid();
        }));
    }

    /**
     * Update customer's credit card.
     *
     * @param  string $token
     * @return void
     */
    public function updateCard($token, array $options = [])
    {
        $this->getBillingManager()->updateCard($token, $options);
    }

    /**
     * Get the tax percentage to apply to the subscription.
     *
     * @return int
     */
    public function taxPercentage()
    {
        return 0;
    }

    /**
     * Allow for dynamic calls to billing manager.
     *
     * @param $name
     * @param $arguments
     * @return \Laravel\Cashier\Gateway\BillingManager|mixed
     */
    public function __call($name, $arguments)
    {
        if (empty($arguments) && Cashier::hasGateway($name)) {
            if (!$this->getAssignedPaymentGateway()) {
                $this->billingManager = Cashier::gateway($name)->manageBilling($this);
            }

            $billingManager = $this->getBillingManager();
            if ($billingManager->getGateway()->getName() === $name) {
                return $billingManager;
            }
        }

        return parent::__call($name, $arguments);
    }

    /**
     * Only run code for specific gateway.
     *
     * @param  string  $gateway
     * @param  \Closure  $callback
     * @return $this
     *
     * @throws \Laravel\Cashier\Exception
     */
    protected function ifGateway($gateway, Closure $callback)
    {
        if ($gateway === $this->payment_gateway) {
            $callback($this->getBillingManager());
        }

        return $this;
    }

    /**
     * Get the billing manager.
     *
     * @return \Laravel\Cashier\Gateway\BillingManager
     */
    protected function getBillingManager()
    {
        if (null === $this->billingManager) {
            $this->billingManager = $this->getGateway()->manageBilling($this);
        }

        return $this->billingManager;
    }
}

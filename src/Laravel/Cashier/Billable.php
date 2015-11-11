<?php

namespace Laravel\Cashier;

use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait Billable
{
    /**
     * The Stripe API key.
     *
     * @var string
     */
    protected static $stripeKey;

    /**
     * Get the name that should be shown on the entity's invoices.
     *
     * @return string
     */
    public function getBillableName()
    {
        return $this->email;
    }

    /**
     * Write the entity to persistent storage.
     *
     * @return void
     */
    public function saveBillableInstance()
    {
        $this->save();
    }

    /**
     * Make a "one off" charge on the customer for the given amount.
     *
     * @param  int  $amount
     * @param  array  $options
     * @return bool|mixed
     */
    public function charge($amount, array $options = [])
    {
        return (new StripeGateway($this))->charge($amount, $options);
    }

    /**
     * Get a new billing gateway instance for the given plan.
     *
     * @param  string|null  $plan
     * @return \Laravel\Cashier\StripeGateway
     */
    public function subscription($plan = null)
    {
        return new StripeGateway($this, $plan);
    }

    /**
     * Invoice the billable entity outside of regular billing cycle.
     *
     * @return bool
     */
    public function invoice()
    {
        return $this->subscription()->invoice();
    }

    /**
     * Find an invoice by ID.
     *
     * @param  string  $id
     * @return \Laravel\Cashier\Invoice|null
     */
    public function findInvoice($id)
    {
        $invoice = $this->subscription()->findInvoice($id);

        if ($invoice && $invoice->customer == $this->getStripeId()) {
            return $invoice;
        }
    }

    /**
     * Find an invoice or throw a 404 error.
     *
     * @param  string  $id
     * @return \Laravel\Cashier\Invoice
     */
    public function findInvoiceOrFail($id)
    {
        $invoice = $this->findInvoice($id);

        if (is_null($invoice)) {
            throw new NotFoundHttpException;
        } else {
            return $invoice;
        }
    }

    /**
     * Get an SplFileInfo instance for a given invoice.
     *
     * @param  string  $id
     * @param  array  $data
     * @param  string $storagePath
     * @return \SplFileInfo
     */
    public function invoiceFile($id, array $data, $storagePath = null)
    {
        return $this->findInvoiceOrFail($id)->file($data, $storagePath);
    }

    /**
     * Create an invoice download Response.
     *
     * @param  string  $id
     * @param  array   $data
     * @param  string  $storagePath
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function downloadInvoice($id, array $data, $storagePath = null)
    {
        return $this->findInvoiceOrFail($id)->download($data, $storagePath);
    }

    /**
     * Get an array of the entity's invoices.
     *
     * @param  array  $parameters
     * @return array
     */
    public function invoices($parameters = [])
    {
        return $this->subscription()->invoices(false, $parameters);
    }

    /**
     *  Get the entity's upcoming invoice.
     *
     * @return \Laravel\Cashier\Invoice|null
     */
    public function upcomingInvoice()
    {
        return $this->subscription()->upcomingInvoice();
    }

    /**
     * Update customer's credit card.
     *
     * @param  string  $token
     * @return void
     */
    public function updateCard($token)
    {
        return $this->subscription()->updateCard($token);
    }

    /**
     * Apply a coupon to the billable entity.
     *
     * @param  string  $coupon
     * @return void
     */
    public function applyCoupon($coupon)
    {
        return $this->subscription()->applyCoupon($coupon);
    }

    /**
     * Determine if the entity is within their trial period.
     *
     * @return bool
     */
    public function onTrial()
    {
        if (! is_null($this->getTrialEndDate())) {
            return Carbon::today()->lt($this->getTrialEndDate());
        } else {
            return false;
        }
    }

    /**
     * Determine if the entity is on grace period after cancellation.
     *
     * @return bool
     */
    public function onGracePeriod()
    {
        if (! is_null($endsAt = $this->getSubscriptionEndDate())) {
            return Carbon::now()->lt(Carbon::instance($endsAt));
        } else {
            return false;
        }
    }

    /**
     * Determine if the entity has an active subscription.
     *
     * @return bool
     */
    public function subscribed()
    {
        if ($this->requiresCardUpFront()) {
            return $this->stripeIsActive() || $this->onGracePeriod();
        } else {
            return $this->stripeIsActive() || $this->onTrial() || $this->onGracePeriod();
        }
    }

    /**
     * Determine if the entity's trial has expired.
     *
     * @return bool
     */
    public function expired()
    {
        return ! $this->subscribed();
    }

    /**
     * Determine if the entity has a Stripe ID but is no longer active.
     *
     * @return bool
     */
    public function cancelled()
    {
        return $this->readyForBilling() && ! $this->stripeIsActive();
    }

    /**
     * Determine if the user has ever been subscribed.
     *
     * @return bool
     */
    public function everSubscribed()
    {
        return $this->readyForBilling();
    }

    /**
     * Determine if the entity is on the given plan.
     *
     * @param  string  $plan
     * @return bool
     */
    public function onPlan($plan)
    {
        return $this->stripeIsActive() && $this->subscription()->planId() == $plan;
    }

    /**
     * Determine if billing requires a credit card up front.
     *
     * @return bool
     */
    public function requiresCardUpFront()
    {
        if (isset($this->cardUpFront)) {
            return $this->cardUpFront;
        }

        return true;
    }

    /**
     * Determine if the entity is a Stripe customer.
     *
     * @return bool
     */
    public function readyForBilling()
    {
        return ! is_null($this->getStripeId());
    }

    /**
     * Determine if the entity has a current Stripe subscription.
     *
     * @return bool
     */
    public function stripeIsActive()
    {
        return $this->stripe_active;
    }

    /**
     * Set whether the entity has a current Stripe subscription.
     *
     * @param  bool  $active
     * @return \Laravel\Cashier\Contracts\Billable
     */
    public function setStripeIsActive($active = true)
    {
        $this->stripe_active = $active;

        return $this;
    }

    /**
     * Set Stripe as inactive on the entity.
     *
     * @return \Laravel\Cashier\Contracts\Billable
     */
    public function deactivateStripe()
    {
        $this->setStripeIsActive(false);

        $this->stripe_subscription = null;

        return $this;
    }

    /**
     * Determine if the entity has a Stripe customer ID.
     *
     * @return bool
     */
    public function hasStripeId()
    {
        return ! is_null($this->stripe_id);
    }

    /**
     * Get the Stripe ID for the entity.
     *
     * @return string
     */
    public function getStripeId()
    {
        return $this->stripe_id;
    }

    /**
     * Get the name of the Stripe ID database column.
     *
     * @return string
     */
    public function getStripeIdName()
    {
        return 'stripe_id';
    }

    /**
     * Set the Stripe ID for the entity.
     *
     * @param  string  $stripe_id
     * @return \Laravel\Cashier\Contracts\Billable
     */
    public function setStripeId($stripe_id)
    {
        $this->stripe_id = $stripe_id;

        return $this;
    }

    /**
     * Get the current subscription ID.
     *
     * @return string
     */
    public function getStripeSubscription()
    {
        return $this->stripe_subscription;
    }

    /**
     * Set the current subscription ID.
     *
     * @param  string  $subscription_id
     * @return \Laravel\Cashier\Contracts\Billable
     */
    public function setStripeSubscription($subscription_id)
    {
        $this->stripe_subscription = $subscription_id;

        return $this;
    }

    /**
     * Get the Stripe plan ID.
     *
     * @return string
     */
    public function getStripePlan()
    {
        return $this->stripe_plan;
    }

    /**
     * Set the Stripe plan ID.
     *
     * @param  string  $plan
     * @return \Laravel\Cashier\Contracts\Billable
     */
    public function setStripePlan($plan)
    {
        $this->stripe_plan = $plan;

        return $this;
    }

    /**
     * Get the last four digits of the entity's credit card.
     *
     * @return string
     */
    public function getLastFourCardDigits()
    {
        return $this->last_four;
    }

    /**
     * Set the last four digits of the entity's credit card.
     *
     * @return \Laravel\Cashier\Contracts\Billable
     */
    public function setLastFourCardDigits($digits)
    {
        $this->last_four = $digits;

        return $this;
    }

    /**
     * Get the date on which the trial ends.
     *
     * @return \DateTime
     */
    public function getTrialEndDate()
    {
        return $this->trial_ends_at;
    }

    /**
     * Set the date on which the trial ends.
     *
     * @param  \DateTime|null  $date
     * @return \Laravel\Cashier\Contracts\Billable
     */
    public function setTrialEndDate($date)
    {
        $this->trial_ends_at = $date;

        return $this;
    }

    /**
     * Get the subscription end date for the entity.
     *
     * @return \DateTime
     */
    public function getSubscriptionEndDate()
    {
        return $this->subscription_ends_at;
    }

    /**
     * Set the subscription end date for the entity.
     *
     * @param  \DateTime|null  $date
     * @return \Laravel\Cashier\Contracts\Billable
     */
    public function setSubscriptionEndDate($date)
    {
        $this->subscription_ends_at = $date;

        return $this;
    }

    /**
     * Get the Stripe supported currency used by the entity.
     *
     * @return string
     */
    public function getCurrency()
    {
        return 'usd';
    }

    /**
     * Get the locale for the currency used by the entity.
     *
     * @return string
     */
    public function getCurrencyLocale()
    {
        return 'en_US';
    }

    /**
     * Get the tax percentage to apply to the subscription.
     *
     * @return int
     */
    public function getTaxPercent()
    {
        return 0;
    }

    /**
     * Format the given currency for display, without the currency symbol.
     *
     * @param  int  $amount
     * @return mixed
     */
    public function formatCurrency($amount)
    {
        return number_format($amount / 100, 2);
    }

    /**
     * Add the currency symbol to a given amount.
     *
     * @param  string  $amount
     * @return string
     */
    public function addCurrencySymbol($amount)
    {
        return '$'.$amount;
    }

    /**
     * Get the Stripe API key.
     *
     * @return string
     */
    public static function getStripeKey()
    {
        return static::$stripeKey ?: Config::get('services.stripe.secret');
    }

    /**
     * Set the Stripe API key.
     *
     * @param  string  $key
     * @return void
     */
    public static function setStripeKey($key)
    {
        static::$stripeKey = $key;
    }
}

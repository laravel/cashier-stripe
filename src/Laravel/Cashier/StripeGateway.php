<?php

namespace Laravel\Cashier;

use Exception;
use Carbon\Carbon;
use Stripe\Stripe;
use Stripe\Charge as StripeCharge;
use Stripe\Invoice as StripeInvoice;
use Stripe\Customer as StripeCustomer;
use Stripe\Error\Card as StripeErrorCard;
use Stripe\Error\InvalidRequest as StripeErrorInvalidRequest;
use InvalidArgumentException;
use Laravel\Cashier\Contracts\Billable as BillableContract;

class StripeGateway
{
    /**
     * The billable instance.
     *
     * @var \Laravel\Cashier\Contracts\Billable
     */
    protected $billable;

    /**
     * The name of the plan.
     *
     * @var string
     */
    protected $plan;

    /**
     * The coupon to apply to the subscription.
     *
     * @var string
     */
    protected $coupon;

    /**
     * Indicates if the plan change should be prorated.
     *
     * @var bool
     */
    protected $prorate = true;

    /**
     * Indicates the "quantity" of the plan.
     *
     * @var int
     */
    protected $quantity = 1;

    /**
     * The trial end date that should be used when updating.
     *
     * @var \Carbon\Carbon
     */
    protected $trialEnd;

    /**
     * Indicates if the trial should be immediately cancelled for the operation.
     *
     * @var bool
     */
    protected $skipTrial = false;

    /**
     * The plan's billing cycle anchor.
     *
     * @var \Carbon\Carbon|string
     */
    protected $billingCycleAnchor;

    /**
     * Create a new Stripe gateway instance.
     *
     * @param  \Laravel\Cashier\Contracts\Billable   $billable
     * @param  string|null  $plan
     * @return void
     */
    public function __construct(BillableContract $billable, $plan = null)
    {
        $this->plan = $plan;
        $this->billable = $billable;

        Stripe::setApiKey($this->getStripeKey());
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
        $options = array_merge([
            'currency' => $this->getCurrency(),
        ], $options);

        $options['amount'] = $amount;

        if (! array_key_exists('source', $options) && $this->billable->hasStripeId()) {
            $options['customer'] = $this->billable->getStripeId();
        }

        if (! array_key_exists('source', $options) && ! array_key_exists('customer', $options)) {
            throw new InvalidArgumentException('No payment source provided.');
        }

        try {
            $response = StripeCharge::create($options);
        } catch (StripeErrorCard $e) {
            return false;
        }

        return $response;
    }

    /**
     * Subscribe to the plan for the first time.
     *
     * @param  string  $token
     * @param  array   $properties
     * @param  object|null  $customer
     * @return void
     */
    public function create($token, array $properties = [], $customer = null)
    {
        $freshCustomer = false;

        if (! $customer) {
            $customer = $this->createStripeCustomer($token, $properties);

            $freshCustomer = true;
        } elseif (! is_null($token)) {
            $this->updateCard($token);
        }

        $this->billable->setStripeSubscription(
            $customer->updateSubscription($this->buildPayload())->id
        );

        $customer = $this->getStripeCustomer($customer->id);

        if ($freshCustomer && $trialEnd = $this->getTrialEndForCustomer($customer)) {
            $this->billable->setTrialEndDate($trialEnd);
        }

        $this->updateLocalStripeData($customer);
    }

    /**
     * Build the payload for a subscription create / update.
     *
     * @return array
     */
    protected function buildPayload()
    {
        $payload = [
            'plan' => $this->plan, 'prorate' => $this->prorate,
            'quantity' => $this->quantity,
        ];

        if ($trialEnd = $this->getTrialEndForUpdate()) {
            $payload['trial_end'] = $trialEnd;
        }

        if ($taxPercent = $this->billable->getTaxPercent()) {
            $payload['tax_percent'] = $taxPercent;
        }

        if ($billingCycleAnchor = $this->getBillingCycleAnchorForUpdate()) {
            $payload['billing_cycle_anchor'] = $billingCycleAnchor;
        }

        return $payload;
    }

    /**
     * Swap the billable entity to a new plan.
     *
     * @param  int|null  $quantity
     * @return void
     */
    public function swap($quantity = null)
    {
        $customer = $this->getStripeCustomer();

        // If no specific trial end date has been set, the default behavior should be
        // to maintain the current trial state, whether that is "active" or to run
        // the swap out with the exact number of days left on this current plan.
        if (is_null($this->trialEnd)) {
            $this->maintainTrial();
        }

        // Again, if no explicit quantity was set, the default behaviors should be to
        // maintain the current quantity onto the new plan. This is a sensible one
        // that should be the expected behavior for most developers with Stripe.
        if (isset($customer->subscription) && is_null($quantity)) {
            $this->quantity(
                $customer->subscription->quantity
            );
        }

        // If the developer specified an explicit quantity we can just pass it to the
        // quantity method directly. This will set the proper quantity on this new
        // plan that we are swapping to. Then we'll make this subscription swap.
        else {
            $this->quantity($quantity);
        }

        return $this->create(null, [], $customer);
    }

    /**
     * Swap the billable entity to a new plan and invoice immediately.
     *
     * @param  int|null  $quantity
     * @return void
     */
    public function swapAndInvoice($quantity = null)
    {
        $this->swap($quantity);

        $this->invoice();
    }

    /**
     * Resubscribe a customer to a given plan.
     *
     * @param  string  $token
     * @return void
     */
    public function resume($token = null)
    {
        $this->noProrate()->skipTrial()->create($token, [], $this->getStripeCustomer());

        $this->billable->setTrialEndDate(null)->saveBillableInstance();
    }

    /**
     * Invoice the billable entity outside of regular billing cycle.
     *
     * @return bool
     */
    public function invoice()
    {
        try {
            $customer = $this->getStripeCustomer();

            StripeInvoice::create(['customer' => $customer->id], $this->getStripeKey())->pay();

            return true;
        } catch (StripeErrorInvalidRequest $e) {
            return false;
        }
    }

    /**
     * Find an invoice by ID.
     *
     * @param  string  $id
     * @return \Laravel\Cashier\Invoice|null
     */
    public function findInvoice($id)
    {
        try {
            return new Invoice($this->billable, StripeInvoice::retrieve($id, $this->getStripeKey()));
        } catch (Exception $e) {
            //
        }
    }

    /**
     * Get an array of the entity's invoices.
     *
     * @param  bool   $includePending
     * @param  array  $parameters
     * @return array
     */
    public function invoices($includePending = false, $parameters = [])
    {
        $invoices = [];

        $stripeInvoices = $this->getStripeCustomer()->invoices($parameters);

        // Here we will loop through the Stripe invoices and create our own custom Invoice
        // instances that have more helper methods and are generally more convenient to
        // work with than the plain Stripe objects are. Then, we'll return the array.
        if (! is_null($stripeInvoices)) {
            foreach ($stripeInvoices->data as $invoice) {
                if ($invoice->paid || $includePending) {
                    $invoices[] = new Invoice($this->billable, $invoice);
                }
            }
        }

        return $invoices;
    }

    /**
     * Get all invoices, including pending.
     *
     * @return array
     */
    public function allInvoices()
    {
        return $this->invoices(true);
    }

    /**
     * Get the entity's upcoming invoice.
     *
     * @return \Laravel\Cashier\Invoice|null
     */
    public function upcomingInvoice()
    {
        try {
            $customer = $this->getStripeCustomer();

            $stripeInvoice = StripeInvoice::upcoming(['customer' => $customer->id]);

            return new Invoice($this->billable, $stripeInvoice);
        } catch (StripeErrorInvalidRequest $e) {
            //
        }
    }

    /**
     * Increment the quantity of the subscription.
     *
     * @param  int  $count
     * @return void
     */
    public function increment($count = 1)
    {
        $customer = $this->getStripeCustomer();

        $this->updateQuantity($customer->subscription->quantity + $count, $customer);
    }

    /**
     *  Increment the quantity of the subscription. and invoice immediately.
     *
     * @param  int|null  $quantity
     * @return void
     */
    public function incrementAndInvoice($quantity = null)
    {
        $this->increment($quantity);

        $this->invoice();
    }

    /**
     * Decrement the quantity of the subscription.
     *
     * @param  int  $count
     * @return void
     */
    public function decrement($count = 1)
    {
        $customer = $this->getStripeCustomer();

        $this->updateQuantity($customer->subscription->quantity - $count, $customer);
    }

    /**
     * Update the quantity of the subscription.
     *
     * @param  int  $quantity
     * @param  \Stripe\Customer|null  $customer
     * @return void
     */
    public function updateQuantity($quantity, $customer = null)
    {
        $customer = $customer ?: $this->getStripeCustomer();

        $subscription = [
            'plan' => $customer->subscription->plan->id,
            'quantity' => $quantity,
        ];

        if ($trialEnd = $this->getTrialEndForUpdate()) {
            $subscription['trial_end'] = $trialEnd;
        }

        $customer->updateSubscription($subscription);
    }

    /**
     * Cancel the billable entity's subscription.
     *
     * @return void
     */
    public function cancel($atPeriodEnd = true)
    {
        $customer = $this->getStripeCustomer();

        if ($customer->subscription) {
            if ($atPeriodEnd) {
                $this->billable->setSubscriptionEndDate(
                    Carbon::createFromTimestamp($this->getSubscriptionEndTimestamp($customer))
                );
            }

            $customer->cancelSubscription(['at_period_end' => $atPeriodEnd]);
        }

        if ($atPeriodEnd) {
            $this->billable->setStripeIsActive(false)->saveBillableInstance();
        } else {
            $this->billable->setSubscriptionEndDate(Carbon::now());

            $this->billable->deactivateStripe()->saveBillableInstance();
        }
    }

    /**
     * Cancel the billable entity's subscription at the end of the period.
     *
     * @return void
     */
    public function cancelAtEndOfPeriod()
    {
        return $this->cancel(true);
    }

    /**
     * Cancel the billable entity's subscription immediately.
     *
     * @return void
     */
    public function cancelNow()
    {
        return $this->cancel(false);
    }

    /**
     * Get the subscription end timestamp for the customer.
     *
     * @param  object  $customer
     * @return int
     */
    protected function getSubscriptionEndTimestamp($customer)
    {
        if (! is_null($customer->subscription->trial_end) && $customer->subscription->trial_end > time()) {
            return $customer->subscription->trial_end;
        } else {
            return $customer->subscription->current_period_end;
        }
    }

    /**
     * Get the current subscription period's end date.
     *
     * @return \Carbon\Carbon
     */
    public function getSubscriptionEndDate()
    {
        $customer = $this->getStripeCustomer();

        return Carbon::createFromTimestamp($this->getSubscriptionEndTimestamp($customer));
    }

    /**
     * Update the credit card attached to the entity.
     *
     * @param  string  $token
     * @return void
     */
    public function updateCard($token)
    {
        $customer = $this->getStripeCustomer();

        $card = $customer->sources->create(['source' => $token]);

        $customer->default_source = $card->id;

        $customer->save();

        $this->billable
             ->setLastFourCardDigits($this->getLastFourCardDigits($this->getStripeCustomer()))
             ->saveBillableInstance();
    }

    /**
     * Apply a coupon to the billable entity.
     *
     * @param  string  $coupon
     * @return void
     */
    public function applyCoupon($coupon)
    {
        $customer = $this->getStripeCustomer();

        $customer->coupon = $coupon;

        $customer->save();
    }

    /**
     * Get the plan ID for the billable entity.
     *
     * @return string
     */
    public function planId()
    {
        $customer = $this->getStripeCustomer();

        if (isset($customer->subscription)) {
            return $customer->subscription->plan->id;
        }
    }

    /**
     * Update the local Stripe data in storage.
     *
     * @param  \Stripe\Customer  $customer
     * @param  string|null  $plan
     * @return void
     */
    public function updateLocalStripeData($customer, $plan = null)
    {
        $this->billable
                ->setStripeId($customer->id)
                ->setStripePlan($plan ?: $this->plan)
                ->setLastFourCardDigits($this->getLastFourCardDigits($customer))
                ->setStripeIsActive(true)
                ->setSubscriptionEndDate(null)
                ->saveBillableInstance();
    }

    /**
     * Create a new Stripe customer instance.
     *
     * @param  string  $token
     * @param  array   $properties
     * @return \Stripe\Customer
     */
    public function createStripeCustomer($token, array $properties = [])
    {
        if ($this->coupon) {
            $properties['coupon'] = $this->coupon;
        }

        $customer = StripeCustomer::create(
            array_merge(['source' => $token], $properties), $this->getStripeKey()
        );

        return $this->getStripeCustomer($customer->id);
    }

    /**
     * Get the Stripe customer for entity.
     *
     * @return \Stripe\Customer
     */
    public function getStripeCustomer($id = null)
    {
        $customer = Customer::retrieve($id ?: $this->billable->getStripeId(), $this->getStripeKey());

        if (! isset($customer->deleted) && $this->usingMultipleSubscriptionApi($customer)) {
            $customer->subscription = $customer->findSubscription($this->billable->getStripeSubscription());
        }

        return $customer;
    }

    /**
     * Determine if the customer has a subscription.
     *
     * @param  \Stripe\Customer  $customer
     * @return bool
     */
    protected function usingMultipleSubscriptionApi($customer)
    {
        return ! isset($customer->subscription) &&
                 count($customer->subscriptions) > 0 &&
                 ! is_null($this->billable->getStripeSubscription());
    }

    /**
     * Get the last four credit card digits for a customer.
     *
     * @param  \Stripe\Customer  $customer
     * @return string
     */
    protected function getLastFourCardDigits($customer)
    {
        return ($customer->default_source) ? $customer->sources->retrieve($customer->default_source)->last4 : null;
    }

    /**
     * The coupon to apply to a new subscription.
     *
     * @param  string  $coupon
     * @return \Laravel\Cashier\StripeGateway
     */
    public function withCoupon($coupon)
    {
        $this->coupon = $coupon;

        return $this;
    }

    /**
     * Indicate that the plan change should be prorated.
     *
     * @return \Laravel\Cashier\StripeGateway
     */
    public function prorate()
    {
        $this->prorate = true;

        return $this;
    }

    /**
     * Indicate that the plan change should not be prorated.
     *
     * @return \Laravel\Cashier\StripeGateway
     */
    public function noProrate()
    {
        $this->prorate = false;

        return $this;
    }

    /**
     * Get the subscription quantity.
     *
     * @return int
     */
    public function getQuantity()
    {
        $customer = $this->getStripeCustomer();

        return $customer->subscription->quantity;
    }

    /**
     * Set the quantity to apply to the subscription.
     *
     * @param  int  $quantity
     * @return \Laravel\Cashier\StripeGateway
     */
    public function quantity($quantity)
    {
        $this->quantity = $quantity;

        return $this;
    }

    /**
     * Indicate that no trial should be enforced on the operation.
     *
     * @return \Laravel\Cashier\StripeGateway
     */
    public function skipTrial()
    {
        $this->skipTrial = true;

        return $this;
    }

    /**
     * Specify the ending date of the trial.
     *
     * @param  \DateTime  $trialEnd
     * @return \Laravel\Cashier\StripeGateway
     */
    public function trialFor(\DateTime $trialEnd)
    {
        $this->trialEnd = $trialEnd;

        return $this;
    }

    /**
     * Specify when the billing cycle should be anchored.
     *
     * @param  \DateTime|string  $billingCycleAnchor
     * @return \Laravel\Cashier\StripeGateway
     */
    public function anchorOn($billingCycleAnchor)
    {
        $this->billingCycleAnchor = $billingCycleAnchor;

        return $this;
    }

    /**
     * Get the billing cycle anchor for subscription change.
     *
     * @return int|null
     */
    protected function getBillingCycleAnchorForUpdate()
    {
        if ($this->billingCycleAnchor == 'now' || $this->billingCycleAnchor == 'unchanged') {
            return $this->billingCycleAnchor;
        }

        return $this->billingCycleAnchor ? $this->billingCycleAnchor->getTimestamp() : null;
    }

    /**
     * Get the current trial end date for subscription change.
     *
     * @return \DateTime
     */
    public function getTrialFor()
    {
        return $this->trialEnd;
    }

    /**
     * Get the trial end timestamp for a Stripe subscription update.
     *
     * @return int
     */
    protected function getTrialEndForUpdate()
    {
        if ($this->skipTrial) {
            return 'now';
        }

        return $this->trialEnd ? $this->trialEnd->getTimestamp() : null;
    }

    /**
     * Maintain the days left of the current trial (if applicable).
     *
     * @return \Laravel\Cashier\StripeGateway
     */
    public function maintainTrial()
    {
        if ($this->billable->readyForBilling()) {
            if (! is_null($trialEnd = $this->getTrialEndForCustomer($this->getStripeCustomer()))) {
                $this->calculateRemainingTrialDays($trialEnd);
            } else {
                $this->skipTrial();
            }
        }

        return $this;
    }

    /**
     * Get the trial end date for the customer's subscription.
     *
     * @param  object  $customer
     * @return \Carbon\Carbon|null
     */
    public function getTrialEndForCustomer($customer)
    {
        if (isset($customer->subscription) && isset($customer->subscription->trial_end)) {
            return Carbon::createFromTimestamp($customer->subscription->trial_end);
        }
    }

    /**
     * Calculate the remaining trial days based on the current trial end.
     *
     * @param  \Carbon\Carbon  $trialEnd
     * @return void
     */
    protected function calculateRemainingTrialDays($trialEnd)
    {
        // If there is still trial left on the current plan, we'll maintain that amount of
        // time on the new plan. If there is no time left on the trial we will force it
        // to skip any trials on this new plan, as this is the most expected actions.
        $diff = Carbon::now()->diffInHours($trialEnd);

        return $diff > 0 ? $this->trialFor(Carbon::now()->addHours($diff)) : $this->skipTrial();
    }

    /**
     * Get the Stripe API key for the instance.
     *
     * @return string
     */
    protected function getStripeKey()
    {
        return $this->billable->getStripeKey();
    }

    /**
     * Get the currency for the billable entity.
     *
     * @return string
     */
    protected function getCurrency()
    {
        return $this->billable->getCurrency();
    }
}

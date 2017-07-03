<?php

namespace Laravel\Cashier;

use Carbon\Carbon;
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
 * @property-read \Laravel\Cashier\Subscription[] $subscriptions
 */
trait Billable
{
    use UsesPaymentGateway;

    /**
     * Make a "one off" charge on the customer for the given amount.
     *
     * @param  int $amount
     * @param  array $options
     * @return \Stripe\Charge|array
     */
    public function charge($amount, array $options = [])
    {
        return $this->getGateway()->charge($amount, $options);
    }

    /**
     * Refund a customer for a charge.
     *
     * @param  string $charge
     * @param  array $options
     * @return \Stripe\Charge|mixed
     */
    public function refund($charge, array $options = [])
    {
        return $this->getGateway()->refund($charge, $options);
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
     * @return \Stripe\InvoiceItem
     *
     * @throws \InvalidArgumentException
     */
    public function tab($description, $amount, array $options = [])
    {
        return $this->getGateway()->tab($description, $amount, $options);
    }

    /**
     * Invoice the customer for the given amount and generate an invoice immediately.
     *
     * @param  string $description
     * @param  int $amount
     * @param  array $options
     * @return \Laravel\Cashier\Gateway\Invoice|bool
     */
    public function invoiceFor($description, $amount, array $options = [])
    {
        return $this->getGateway()->invoiceFor($description, $amount, $options);
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
     * Invoice the billable entity outside of regular billing cycle.
     *
     * @return \Stripe\Invoice|bool
     */
    public function invoice()
    {
        // FIXME
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
     * @param  string $subscription
     * @param  string|null $plan
     * @return bool
     */
    public function onTrial($subscription = 'default', $plan = null)
    {
        if (func_num_args() === 0 && $this->onGenericTrial()) {
            return true;
        }

        $subscription = $this->subscription($subscription);

        if (is_null($plan)) {
            return $subscription && $subscription->onTrial();
        }

        return $subscription && $subscription->onTrial() && $subscription->getPaymentGatewayPlanAttribute() === $plan;
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
     * @param  string $subscription
     * @return \Laravel\Cashier\Subscription|null
     */
    public function subscription($subscription = 'default')
    {
        return $this->subscriptions->sortByDesc(function ($value) {
            return $value->created_at->getTimestamp();
        })->first(function ($value) use ($subscription) {
                return $value->name === $subscription;
            });
    }

    /**
     * Determine if the Stripe model has a given subscription.
     *
     * @param  string $subscription
     * @param  string|null $plan
     * @return bool
     */
    public function subscribed($subscription = 'default', $plan = null)
    {
        $subscription = $this->subscription($subscription);

        if (is_null($subscription)) {
            return false;
        }

        if (is_null($plan)) {
            return $subscription->valid();
        }

        return $subscription->valid() && $subscription->getPaymentGatewayPlanAttribute() === $plan;
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
     * Get the entity's upcoming invoice.
     *
     * @return \Laravel\Cashier\Gateway\Invoice|null
     */
    public function upcomingInvoice()
    {
        // FIXME: Not in braintree
        try {
            $stripeInvoice = StripeInvoice::upcoming(['customer' => $this->stripe_id], ['api_key' => $this->getStripeKey()]);

            return new Invoice($this, $stripeInvoice);
        } catch (StripeErrorInvalidRequest $e) {
            //
        }
    }

    /**
     * Find an invoice or throw a 404 error.
     *
     * @param  string $id
     * @return \Laravel\Cashier\Gateway\Invoice
     */
    public function findInvoiceOrFail($id)
    {
        return $this->findInvoiceOrFail($id);
    }

    /**
     * Find an invoice by ID.
     *
     * @param  string $id
     * @return \Laravel\Cashier\Gateway\Invoice|null
     */
    public function findInvoice($id)
    {
        return $this->findInvoice($id);
    }

    /**
     * Create an invoice download Response.
     *
     * @param  string $id
     * @param  array $data
     * @param  string $storagePath
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function downloadInvoice($id, array $data, $storagePath = null)
    {
        return $this->getGateway()->downloadInvoice($id, $data, $storagePath);
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
        return $this->getGateway()->invoices($includePending, $parameters);
    }

    /**
     * Get the Stripe customer for the Stripe model.
     *
     * @return \Stripe\Customer
     */
    public function asStripeCustomer()
    {
        return $this->asCustomer('stripe');
    }

    /**
     * Synchronises the customer's card from Stripe back into the database.
     *
     * @return $this
     */
    public function updateCardFromStripe()
    {
        $customer = $this->asStripeCustomer();

        $defaultCard = null;

        foreach ($customer->sources->data as $card) {
            if ($card->id === $customer->default_source) {
                $defaultCard = $card;
                break;
            }
        }

        if ($defaultCard) {
            $this->fillCardDetails($defaultCard)->save();
        } else {
            $this->forceFill([
                'card_brand' => null,
                'card_last_four' => null,
            ])->save();
        }

        return $this;
    }

    /**
     * Fills the model's properties with the source from Stripe.
     *
     * @param  \Stripe\Card|null $card
     * @return $this
     */
    protected function fillCardDetails($card)
    {
        if ($card) {
            $this->card_brand = $card->brand;
            $this->card_last_four = $card->last4;
        }

        return $this;
    }

    /**
     * Deletes the entity's cards.
     *
     * @return void
     */
    public function deleteCards()
    {
        $this->cards()->each(function ($card) {
            $card->delete();
        });
    }

    /**
     * Get a collection of the entity's cards.
     *
     * @param  array $parameters
     * @return \Illuminate\Support\Collection
     */
    public function cards($parameters = [])
    {
        $cards = [];

        $parameters = array_merge(['limit' => 24], $parameters);

        $stripeCards = $this->asStripeCustomer()->sources->all(['object' => 'card'] + $parameters);

        if (! is_null($stripeCards)) {
            foreach ($stripeCards->data as $card) {
                $cards[] = new Card($this, $card);
            }
        }

        return new Collection($cards);
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
            if ($subscription->getPaymentGatewayPlanAttribute() === $plan) {
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
            return $subscription->getPaymentGatewayPlanAttribute() === $plan && $subscription->valid();
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
        $this->getGateway()->updateCard($token, $options);
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
     * @param $gateway
     * @param $token
     * @param array $options
     * @return \Stripe\Customer|\Braintree\Customer|mixed
     */
    public function createAsCustomer($gateway, $token, array $options = [])
    {
        $oldMethodName = 'createAs'.Str::studly($gateway).'Customer';
        if (method_exists($this, $oldMethodName)) {
            return $this->$oldMethodName($token, $options);
        }

        // FIXME
    }

    /**
     * @param $gateway
     * @return \Stripe\Customer|\Braintree\Customer|mixed
     */
    public function asCustomer($gateway)
    {
        $oldMethodName = 'as'.Str::studly($gateway).'Customer';
        if (method_exists($this, $oldMethodName)) {
            return $this->$oldMethodName();
        }

        return Cashier::gateway($gateway)->asCustomer($this);
    }
}

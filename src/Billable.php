<?php

namespace Laravel\Cashier;

use Exception;
use Stripe\Card as StripeCard;
use Stripe\Token as StripeToken;
use Illuminate\Support\Collection;
use Stripe\Invoice as StripeInvoice;
use Stripe\Customer as StripeCustomer;
use Stripe\BankAccount as StripeBankAccount;
use Stripe\InvoiceItem as StripeInvoiceItem;
use Stripe\Error\Card as StripeCardException;
use Stripe\PaymentIntent as StripePaymentIntent;
use Laravel\Cashier\Exceptions\InvalidStripeCustomer;
use Stripe\Error\InvalidRequest as StripeErrorInvalidRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

trait Billable
{
    /**
     * Make a "one off" charge on the customer for the given amount.
     *
     * @param  int  $amount
     * @param  string  $paymentMethod
     * @param  array  $options
     * @return \Laravel\Cashier\Payment
     */
    public function charge($amount, $paymentMethod, array $options = [])
    {
        $options = array_merge([
            'confirmation_method' => 'automatic',
            'confirm' => true,
            'currency' => $this->preferredCurrency(),
        ], $options);

        $options['amount'] = $amount;
        $options['payment_method'] = $paymentMethod;

        if ($this->stripe_id) {
            $options['customer'] = $this->stripe_id;
        }

        $payment = new Payment(
            StripePaymentIntent::create($options, Cashier::stripeOptions())
        );

        $payment->validate();

        return $payment;
    }

    /**
     * Refund a customer for a charge.
     *
     * @param  string  $paymentIntent
     * @param  array  $options
     * @return \Stripe\Refund
     * @throws \InvalidArgumentException
     */
    public function refund($paymentIntent, array $options = [])
    {
        $intent = StripePaymentIntent::retrieve($paymentIntent, Cashier::stripeOptions());

        return $intent->charges->data[0]->refund($options);
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
     * @param  string  $description
     * @param  int  $amount
     * @param  array  $options
     * @return \Stripe\InvoiceItem
     * @throws \InvalidArgumentException
     */
    public function tab($description, $amount, array $options = [])
    {
        $this->assertCustomerExists();

        $options = array_merge([
            'customer' => $this->stripe_id,
            'amount' => $amount,
            'currency' => $this->preferredCurrency(),
            'description' => $description,
        ], $options);

        return StripeInvoiceItem::create($options, Cashier::stripeOptions());
    }

    /**
     * Invoice the customer for the given amount and generate an invoice immediately.
     *
     * @param  string  $description
     * @param  int  $amount
     * @param  array  $tabOptions
     * @param  array  $invoiceOptions
     * @return \Stripe\Invoice|bool
     */
    public function invoiceFor($description, $amount, array $tabOptions = [], array $invoiceOptions = [])
    {
        $this->tab($description, $amount, $tabOptions);

        return $this->invoice($invoiceOptions);
    }

    /**
     * Begin creating a new subscription.
     *
     * @param  string  $subscription
     * @param  string  $plan
     * @return \Laravel\Cashier\SubscriptionBuilder
     */
    public function newSubscription($subscription, $plan)
    {
        return new SubscriptionBuilder($this, $subscription, $plan);
    }

    /**
     * Determine if the Stripe model is on trial.
     *
     * @param  string  $subscription
     * @param  string|null  $plan
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

        return $subscription && $subscription->onTrial() &&
               $subscription->stripe_plan === $plan;
    }

    /**
     * Determine if the Stripe model is on a "generic" trial at the model level.
     *
     * @return bool
     */
    public function onGenericTrial()
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Determine if the Stripe model has a given subscription.
     *
     * @param  string  $subscription
     * @param  string|null  $plan
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

        return $subscription->valid() &&
               $subscription->stripe_plan === $plan;
    }

    /**
     * Get a subscription instance by name.
     *
     * @param  string  $subscription
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
     * Get all of the subscriptions for the Stripe model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, $this->getForeignKey())->orderBy('created_at', 'desc');
    }

    /**
     * Invoice the billable entity outside of regular billing cycle.
     *
     * @param  array  $options
     * @return \Stripe\Invoice|bool
     */
    public function invoice(array $options = [])
    {
        $this->assertCustomerExists();

        $parameters = array_merge($options, ['customer' => $this->stripe_id]);

        try {
            /** @var \Stripe\Invoice $invoice */
            $invoice = StripeInvoice::create($parameters, Cashier::stripeOptions());

            return $invoice->pay();
        } catch (StripeErrorInvalidRequest $e) {
            return false;
        } catch (StripeCardException $exception) {
            $payment = new Payment(
                StripePaymentIntent::retrieve($invoice->refresh()->payment_intent, Cashier::stripeOptions())
            );

            $payment->validate();
        }
    }

    /**
     * Get the entity's upcoming invoice.
     *
     * @return \Laravel\Cashier\Invoice|null
     */
    public function upcomingInvoice()
    {
        $this->assertCustomerExists();

        try {
            $stripeInvoice = StripeInvoice::upcoming(['customer' => $this->stripe_id], Cashier::stripeOptions());

            return new Invoice($this, $stripeInvoice);
        } catch (StripeErrorInvalidRequest $e) {
            //
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
            $stripeInvoice = StripeInvoice::retrieve(
                $id, Cashier::stripeOptions()
            );

            $stripeInvoice->lines = StripeInvoice::retrieve($id, Cashier::stripeOptions())
                        ->lines
                        ->all(['limit' => 1000]);

            return new Invoice($this, $stripeInvoice);
        } catch (Exception $e) {
            //
        }
    }

    /**
     * Find an invoice or throw a 404 or 403 error.
     *
     * @param  string  $id
     * @return \Laravel\Cashier\Invoice
     */
    public function findInvoiceOrFail($id)
    {
        $invoice = $this->findInvoice($id);

        if (is_null($invoice)) {
            throw new NotFoundHttpException;
        }

        if ($invoice->customer !== $this->stripe_id) {
            throw new AccessDeniedHttpException;
        }

        return $invoice;
    }

    /**
     * Create an invoice download Response.
     *
     * @param  string  $id
     * @param  array  $data
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function downloadInvoice($id, array $data)
    {
        return $this->findInvoiceOrFail($id)->download($data);
    }

    /**
     * Get a collection of the entity's invoices.
     *
     * @param  bool  $includePending
     * @param  array  $parameters
     * @return \Illuminate\Support\Collection
     */
    public function invoices($includePending = false, $parameters = [])
    {
        $this->assertCustomerExists();

        $invoices = [];

        $parameters = array_merge(['limit' => 24], $parameters);

        $stripeInvoices = $this->asStripeCustomer()->invoices($parameters);

        // Here we will loop through the Stripe invoices and create our own custom Invoice
        // instances that have more helper methods and are generally more convenient to
        // work with than the plain Stripe objects are. Then, we'll return the array.
        if (! is_null($stripeInvoices)) {
            foreach ($stripeInvoices->data as $invoice) {
                if ($invoice->paid || $includePending) {
                    $invoices[] = new Invoice($this, $invoice);
                }
            }
        }

        return new Collection($invoices);
    }

    /**
     * Get an array of the entity's invoices.
     *
     * @param  array  $parameters
     * @return \Illuminate\Support\Collection
     */
    public function invoicesIncludingPending(array $parameters = [])
    {
        return $this->invoices(true, $parameters);
    }

    /**
     * Get a collection of the entity's cards.
     *
     * @param  array  $parameters
     * @return \Illuminate\Support\Collection
     */
    public function cards($parameters = [])
    {
        $this->assertCustomerExists();

        $cards = [];

        $parameters = array_merge(['limit' => 24], $parameters);

        $stripeCards = $this->asStripeCustomer()->sources->all(
            ['object' => 'card'] + $parameters
        );

        if (! is_null($stripeCards)) {
            foreach ($stripeCards->data as $card) {
                $cards[] = new Card($this, $card);
            }
        }

        return new Collection($cards);
    }

    /**
     * Get the default card for the entity.
     *
     * @return \Stripe\Card|null
     */
    public function defaultCard()
    {
        if (! $this->hasStripeId()) {
            return;
        }

        $customer = $this->asStripeCustomer();

        foreach ($customer->sources->data as $card) {
            if ($card->id === $customer->default_source) {
                return $card;
            }
        }
    }

    /**
     * Update customer's credit card.
     *
     * @param  string  $token
     * @return void
     */
    public function updateCard($token)
    {
        $this->assertCustomerExists();

        $customer = $this->asStripeCustomer();

        $token = StripeToken::retrieve($token, Cashier::stripeOptions());

        // If the given token already has the card as their default source, we can just
        // bail out of the method now. We don't need to keep adding the same card to
        // a model's account every time we go through this particular method call.
        if ($token[$token->type]->id === $customer->default_source) {
            return;
        }

        $card = $customer->sources->create(['source' => $token]);

        $customer->default_source = $card->id;

        $customer->save();

        // Next we will get the default source for this model so we can update the last
        // four digits and the card brand on the record in the database. This allows
        // us to display the information on the front-end when updating the cards.
        $source = $customer->default_source
                    ? $customer->sources->retrieve($customer->default_source)
                    : null;

        $this->fillCardDetails($source);

        $this->save();
    }

    /**
     * Synchronises the customer's card from Stripe back into the database.
     *
     * @return $this
     */
    public function updateCardFromStripe()
    {
        $defaultCard = $this->defaultCard();

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
     * @param  \Stripe\Card|\Stripe\BankAccount|null  $card
     * @return $this
     */
    protected function fillCardDetails($card)
    {
        if ($card instanceof StripeCard) {
            $this->card_brand = $card->brand;
            $this->card_last_four = $card->last4;
        } elseif ($card instanceof StripeBankAccount) {
            $this->card_brand = 'Bank Account';
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

        $this->updateCardFromStripe();
    }

    /**
     * Apply a coupon to the billable entity.
     *
     * @param  string  $coupon
     * @return void
     */
    public function applyCoupon($coupon)
    {
        $this->assertCustomerExists();

        $customer = $this->asStripeCustomer();

        $customer->coupon = $coupon;

        $customer->save();
    }

    /**
     * Determine if the Stripe model is actively subscribed to one of the given plans.
     *
     * @param  array|string  $plans
     * @param  string  $subscription
     * @return bool
     */
    public function subscribedToPlan($plans, $subscription = 'default')
    {
        $subscription = $this->subscription($subscription);

        if (! $subscription || ! $subscription->valid()) {
            return false;
        }

        foreach ((array) $plans as $plan) {
            if ($subscription->stripe_plan === $plan) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the entity is on the given plan.
     *
     * @param  string  $plan
     * @return bool
     */
    public function onPlan($plan)
    {
        return ! is_null($this->subscriptions->first(function ($value) use ($plan) {
            return $value->stripe_plan === $plan && $value->valid();
        }));
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
     * Determine if the entity has a Stripe customer ID and throw an exception if not.
     *
     * @return void
     *
     * @throws \Laravel\Cashier\Exceptions\InvalidStripeCustomer
     */
    protected function assertCustomerExists()
    {
        if (! $this->stripe_id) {
            throw InvalidStripeCustomer::nonCustomer($this);
        }
    }

    /**
     * Create a Stripe customer for the given model.
     *
     * @param  array  $options
     * @return \Stripe\Customer
     */
    public function createAsStripeCustomer(array $options = [])
    {
        $options = array_key_exists('email', $options)
                ? $options
                : array_merge($options, ['email' => $this->email]);

        // Here we will create the customer instance on Stripe and store the ID of the
        // user from Stripe. This ID will correspond with the Stripe user instances
        // and allow us to retrieve users from Stripe later when we need to work.
        $customer = StripeCustomer::create(
            $options, Cashier::stripeOptions()
        );

        $this->stripe_id = $customer->id;

        $this->save();

        return $customer;
    }

    /**
     * Update the underlying Stripe customer information for the model.
     *
     * @param  array  $options
     * @return \Stripe\Customer
     */
    public function updateStripeCustomer(array $options = [])
    {
        return StripeCustomer::update(
            $this->stripe_id, $options, Cashier::stripeOptions()
        );
    }

    /**
     * Get the Stripe customer instance for the current user and token.
     *
     * @param  array  $options
     * @return \Stripe\Customer
     */
    public function createOrGetStripeCustomer(array $options = [])
    {
        if ($this->stripe_id) {
            return $this->asStripeCustomer();
        }

        return $this->createAsStripeCustomer($options);
    }

    /**
     * Get the Stripe customer for the model.
     *
     * @return \Stripe\Customer
     */
    public function asStripeCustomer()
    {
        return StripeCustomer::retrieve($this->stripe_id, Cashier::stripeOptions());
    }

    /**
     * Get the Stripe supported currency used by the entity.
     *
     * @return string
     */
    public function preferredCurrency()
    {
        return config('cashier.currency');
    }

    /**
     * Get the tax percentage to apply to the subscription.
     *
     * @return int|float
     */
    public function taxPercentage()
    {
        return 0;
    }
}

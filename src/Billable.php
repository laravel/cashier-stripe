<?php

namespace Laravel\Cashier;

interface Billable
{
    /**
     * Make a "one off" charge on the customer for the given amount.
     *
     * @param  int  $amount
     * @param  string  $paymentMethod
     * @param  array  $options
     * @return \Laravel\Cashier\Payment
     */
    public function charge($amount, $paymentMethod, array $options = []);

    /**
     * Refund a customer for a charge.
     *
     * @param  string  $paymentIntent
     * @param  array  $options
     * @return \Stripe\Refund
     */
    public function refund($paymentIntent, array $options = []);

    /**
     * Add an invoice item to the customer's upcoming invoice.
     *
     * @param  string  $description
     * @param  int  $amount
     * @param  array  $options
     * @return \Stripe\InvoiceItem
     */
    public function tab($description, $amount, array $options = []);

    /**
     * Invoice the customer for the given amount and generate an invoice immediately.
     *
     * @param  string  $description
     * @param  int  $amount
     * @param  array  $tabOptions
     * @param  array  $invoiceOptions
     * @return \Laravel\Cashier\Invoice|bool
     */
    public function invoiceFor($description, $amount, array $tabOptions = [], array $invoiceOptions = []);

    /**
     * Begin creating a new subscription.
     *
     * @param  string  $subscription
     * @param  string  $plan
     * @return \Laravel\Cashier\SubscriptionBuilder
     */
    public function newSubscription($subscription, $plan);

    /**
     * Determine if the Stripe model is on trial.
     *
     * @param  string  $subscription
     * @param  string|null  $plan
     * @return bool
     */
    public function onTrial($subscription = 'default', $plan = null);

    /**
     * Determine if the Stripe model is on a "generic" trial at the model level.
     *
     * @return bool
     */
    public function onGenericTrial();

    /**
     * Determine if the Stripe model has a given subscription.
     *
     * @param  string  $subscription
     * @param  string|null  $plan
     * @return bool
     */
    public function subscribed($subscription = 'default', $plan = null);

    /**
     * Get a subscription instance by name.
     *
     * @param  string  $subscription
     * @return \Laravel\Cashier\Subscription|null
     */
    public function subscription($subscription = 'default');

    /**
     * Get all of the subscriptions for the Stripe model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function subscriptions();

    /**
     * Determine if the customer's subscription has an incomplete payment.
     *
     * @param  string  $subscription
     * @return bool
     */
    public function hasIncompletePayment($subscription = 'default');

    /**
     * Invoice the billable entity outside of the regular billing cycle.
     *
     * @param  array  $options
     * @return \Laravel\Cashier\Invoice|bool
     */
    public function invoice(array $options = []);

    /**
     * Get the entity's upcoming invoice.
     *
     * @return \Laravel\Cashier\Invoice|null
     */
    public function upcomingInvoice();

    /**
     * Find an invoice by ID.
     *
     * @param  string  $id
     * @return \Laravel\Cashier\Invoice|null
     */
    public function findInvoice($id);

    /**
     * Find an invoice or throw a 404 or 403 error.
     *
     * @param  string  $id
     * @return \Laravel\Cashier\Invoice
     */
    public function findInvoiceOrFail($id);

    /**
     * Create an invoice download Response.
     *
     * @param  string  $id
     * @param  array  $data
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function downloadInvoice($id, array $data);

    /**
     * Get a collection of the entity's invoices.
     *
     * @param  bool  $includePending
     * @param  array  $parameters
     * @return \Illuminate\Support\Collection
     */
    public function invoices($includePending = false, $parameters = []);

    /**
     * Get an array of the entity's invoices.
     *
     * @param  array  $parameters
     * @return \Illuminate\Support\Collection
     */
    public function invoicesIncludingPending(array $parameters = []);

    /**
     * Create a new SetupIntent instance.
     *
     * @param  array  $options
     * @return \Stripe\SetupIntent
     */
    public function createSetupIntent(array $options = []);

    /**
     * Determines if the customer currently has a default payment method.
     *
     * @return bool
     */
    public function hasDefaultPaymentMethod();

    /**
     * Determines if the customer currently has at least one payment method.
     *
     * @return bool
     */
    public function hasPaymentMethod();

    /**
     * Get a collection of the entity's payment methods.
     *
     * @param  array  $parameters
     * @return \Illuminate\Support\Collection|\Laravel\Cashier\PaymentMethod[]
     */
    public function paymentMethods($parameters = []);

    /**
     * Add a payment method to the customer.
     *
     * @param  \Stripe\PaymentMethod|string  $paymentMethod
     * @return \Laravel\Cashier\PaymentMethod
     */
    public function addPaymentMethod($paymentMethod);

    /**
     * Remove a payment method from the customer.
     *
     * @param  \Stripe\PaymentMethod|string  $paymentMethod
     * @return void
     */
    public function removePaymentMethod($paymentMethod);

    /**
     * Get the default payment method for the entity.
     *
     * @return \Laravel\Cashier\PaymentMethod|\Stripe\Card|\Stripe\BankAccount|null
     */
    public function defaultPaymentMethod();

    /**
     * Update customer's default payment method.
     *
     * @param  \Stripe\PaymentMethod|string  $paymentMethod
     * @return \Laravel\Cashier\PaymentMethod
     */
    public function updateDefaultPaymentMethod($paymentMethod);

    /**
     * Synchronises the customer's default payment method from Stripe back into the database.
     *
     * @return $this
     */
    public function updateDefaultPaymentMethodFromStripe();

    /**
     * Deletes the entity's payment methods.
     *
     * @return void
     */
    public function deletePaymentMethods();

    /**
     * Find a PaymentMethod by ID.
     *
     * @param  string  $paymentMethod
     * @return \Laravel\Cashier\PaymentMethod|null
     */
    public function findPaymentMethod($paymentMethod);

    /**
     * Apply a coupon to the billable entity.
     *
     * @param  string  $coupon
     * @return void
     */
    public function applyCoupon($coupon);

    /**
     * Determine if the Stripe model is actively subscribed to one of the given plans.
     *
     * @param  array|string  $plans
     * @param  string  $subscription
     * @return bool
     */
    public function subscribedToPlan($plans, $subscription = 'default');

    /**
     * Determine if the entity is on the given plan.
     *
     * @param  string  $plan
     * @return bool
     */
    public function onPlan($plan);

    /**
     * Determine if the entity has a Stripe customer ID.
     *
     * @return bool
     */
    public function hasStripeId();

    /**
     * Create a Stripe customer for the given model.
     *
     * @param  array  $options
     * @return \Stripe\Customer
     */
    public function createAsStripeCustomer(array $options = []);

    /**
     * Update the underlying Stripe customer information for the model.
     *
     * @param  array  $options
     * @return \Stripe\Customer
     */
    public function updateStripeCustomer(array $options = []);

    /**
     * Get the Stripe customer instance for the current user or create one.
     *
     * @param  array  $options
     * @return \Stripe\Customer
     */
    public function createOrGetStripeCustomer(array $options = []);

    /**
     * Get the Stripe customer for the model.
     *
     * @return \Stripe\Customer
     */
    public function asStripeCustomer();

    /**
     * Get the Stripe supported currency used by the entity.
     *
     * @return string
     */
    public function preferredCurrency();

    /**
     * Get the tax percentage to apply to the subscription.
     *
     * @return int|float
     */
    public function taxPercentage();

    /**
     * Get the default Stripe API options for the current Billable model.
     *
     * @param  array  $options
     * @return array
     */
    public function stripeOptions(array $options = []);
}

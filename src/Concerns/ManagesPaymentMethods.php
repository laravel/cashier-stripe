<?php

namespace Laravel\Cashier\Concerns;

use Exception;
use Illuminate\Support\Collection;
use Laravel\Cashier\PaymentMethod;
use Stripe\PaymentMethod as StripePaymentMethod;

trait ManagesPaymentMethods
{
    /**
     * Create a new SetupIntent instance.
     *
     * @param  array  $options
     * @return \Stripe\SetupIntent
     */
    public function createSetupIntent(array $options = [])
    {
        return $this->stripe()->setupIntents->create($options);
    }

    /**
     * Determines if the customer currently has a default payment method.
     *
     * @return bool
     */
    public function hasDefaultPaymentMethod()
    {
        return (bool) $this->pm_type;
    }

    /**
     * Determines if the customer currently has at least one payment method of the given type.
     *
     * @param  string  $type
     * @return bool
     */
    public function hasPaymentMethod($type = 'card')
    {
        return $this->paymentMethods($type)->isNotEmpty();
    }

    /**
     * Get a collection of the customer's payment methods of the given type.
     *
     * @param  string  $type
     * @param  array  $parameters
     * @return \Illuminate\Support\Collection|\Laravel\Cashier\PaymentMethod[]
     */
    public function paymentMethods($type = 'card', $parameters = [])
    {
        if (! $this->hasStripeId()) {
            return new Collection();
        }

        $parameters = array_merge(['limit' => 24], $parameters);

        // "type" is temporarily required by Stripe...
        $paymentMethods = $this->stripe()->paymentMethods->all(
            ['customer' => $this->stripe_id, 'type' => $type] + $parameters
        );

        return Collection::make($paymentMethods->data)->map(function ($paymentMethod) {
            return new PaymentMethod($this, $paymentMethod);
        });
    }

    /**
     * Add a payment method to the customer.
     *
     * @param  \Stripe\PaymentMethod|string  $paymentMethod
     * @return \Laravel\Cashier\PaymentMethod
     */
    public function addPaymentMethod($paymentMethod)
    {
        $this->assertCustomerExists();

        $stripePaymentMethod = $this->resolveStripePaymentMethod($paymentMethod);

        if ($stripePaymentMethod->customer !== $this->stripe_id) {
            $stripePaymentMethod = $stripePaymentMethod->attach(
                ['customer' => $this->stripe_id]
            );
        }

        return new PaymentMethod($this, $stripePaymentMethod);
    }

    /**
     * Delete a payment method from the customer.
     *
     * @param  \Stripe\PaymentMethod|string  $paymentMethod
     * @return void
     */
    public function deletePaymentMethod($paymentMethod)
    {
        $this->assertCustomerExists();

        $stripePaymentMethod = $this->resolveStripePaymentMethod($paymentMethod);

        if ($stripePaymentMethod->customer !== $this->stripe_id) {
            return;
        }

        $customer = $this->asStripeCustomer();

        $defaultPaymentMethod = $customer->invoice_settings->default_payment_method;

        $stripePaymentMethod->detach();

        // If the payment method was the default payment method, we'll remove it manually...
        if ($stripePaymentMethod->id === $defaultPaymentMethod) {
            $this->forceFill([
                'pm_type' => null,
                'pm_last_four' => null,
            ])->save();
        }
    }

    /**
     * Remove a payment method from the customer.
     *
     * @param  \Stripe\PaymentMethod|string  $paymentMethod
     * @return void
     *
     * @deprecated Will be removed in a future Cashier Stripe version. Use deletePaymentMethod() instead.
     */
    public function removePaymentMethod($paymentMethod)
    {
        return $this->deletePaymentMethod($paymentMethod);
    }

    /**
     * Get the default payment method for the customer.
     *
     * @return \Laravel\Cashier\PaymentMethod|null
     */
    public function defaultPaymentMethod()
    {
        if (! $this->hasStripeId()) {
            return;
        }

        $customer = $this->asStripeCustomer(['invoice_settings.default_payment_method']);

        if ($customer->invoice_settings->default_payment_method) {
            return new PaymentMethod($this, $customer->invoice_settings->default_payment_method);
        }
    }

    /**
     * Update customer's default payment method.
     *
     * @param  \Stripe\PaymentMethod|string  $paymentMethod
     * @return \Laravel\Cashier\PaymentMethod
     */
    public function updateDefaultPaymentMethod($paymentMethod)
    {
        $this->assertCustomerExists();

        $customer = $this->asStripeCustomer();

        $stripePaymentMethod = $this->resolveStripePaymentMethod($paymentMethod);

        // If the customer already has the payment method as their default, we can bail out
        // of the call now. We don't need to keep adding the same payment method to this
        // model's account every single time we go through this specific process call.
        if ($stripePaymentMethod->id === $customer->invoice_settings->default_payment_method) {
            return;
        }

        $paymentMethod = $this->addPaymentMethod($stripePaymentMethod);

        $this->updateStripeCustomer([
            'invoice_settings' => ['default_payment_method' => $paymentMethod->id],
        ]);

        // Next we will get the default payment method for this user so we can update the
        // payment method details on the record in the database. This will allow us to
        // show that information on the front-end when updating the payment methods.
        $this->fillPaymentMethodDetails($paymentMethod);

        $this->save();

        return $paymentMethod;
    }

    /**
     * Synchronises the customer's default payment method from Stripe back into the database.
     *
     * @return $this
     */
    public function updateDefaultPaymentMethodFromStripe()
    {
        $defaultPaymentMethod = $this->defaultPaymentMethod();

        if ($defaultPaymentMethod && $defaultPaymentMethod instanceof PaymentMethod) {
            $this->fillPaymentMethodDetails(
                $defaultPaymentMethod->asStripePaymentMethod()
            )->save();
        } else {
            $this->forceFill([
                'pm_type' => null,
                'pm_last_four' => null,
            ])->save();
        }

        return $this;
    }

    /**
     * Fills the model's properties with the payment method from Stripe.
     *
     * @param  \Laravel\Cashier\PaymentMethod|\Stripe\PaymentMethod|null  $paymentMethod
     * @return $this
     */
    protected function fillPaymentMethodDetails($paymentMethod)
    {
        if ($paymentMethod->type === 'card') {
            $this->pm_type = $paymentMethod->card->brand;
            $this->pm_last_four = $paymentMethod->card->last4;
        } else {
            $this->pm_type = $type = $paymentMethod->type;
            $this->pm_last_four = optional($paymentMethod)->$type->last4;
        }

        return $this;
    }

    /**
     * Deletes the customer's payment methods of the given type.
     *
     * @param  string  $type
     * @return void
     */
    public function deletePaymentMethods($type = 'card')
    {
        $this->paymentMethods($type)->each(function (PaymentMethod $paymentMethod) {
            $paymentMethod->delete();
        });

        $this->updateDefaultPaymentMethodFromStripe();
    }

    /**
     * Find a PaymentMethod by ID.
     *
     * @param  string  $paymentMethod
     * @return \Laravel\Cashier\PaymentMethod|null
     */
    public function findPaymentMethod($paymentMethod)
    {
        $stripePaymentMethod = null;

        try {
            $stripePaymentMethod = $this->resolveStripePaymentMethod($paymentMethod);
        } catch (Exception $exception) {
            //
        }

        return $stripePaymentMethod ? new PaymentMethod($this, $stripePaymentMethod) : null;
    }

    /**
     * Resolve a PaymentMethod ID to a Stripe PaymentMethod object.
     *
     * @param  \Stripe\PaymentMethod|string  $paymentMethod
     * @return \Stripe\PaymentMethod
     */
    protected function resolveStripePaymentMethod($paymentMethod)
    {
        if ($paymentMethod instanceof StripePaymentMethod) {
            return $paymentMethod;
        }

        return $this->stripe()->paymentMethods->retrieve($paymentMethod);
    }
}

<?php

namespace Laravel\Cashier\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Exceptions\CustomerAlreadyCreated;
use Laravel\Cashier\Exceptions\InvalidCustomer;
use Stripe\BillingPortal\Session as StripeBillingPortalSession;
use Stripe\Customer as StripeCustomer;
use Stripe\Exception\InvalidRequestException as StripeInvalidRequestException;

trait ManagesCustomer
{
    /**
     * Retrieve the Stripe customer ID.
     *
     * @return string|null
     */
    public function stripeId()
    {
        return $this->stripe_id;
    }

    /**
     * Determine if the customer has a Stripe customer ID.
     *
     * @return bool
     */
    public function hasStripeId()
    {
        return ! is_null($this->stripe_id);
    }

    /**
     * Determine if the customer has a Stripe customer ID and throw an exception if not.
     *
     * @return void
     *
     * @throws \Laravel\Cashier\Exceptions\InvalidCustomer
     */
    protected function assertCustomerExists()
    {
        if (! $this->hasStripeId()) {
            throw InvalidCustomer::notYetCreated($this);
        }
    }

    /**
     * Create a Stripe customer for the given model.
     *
     * @param  array  $options
     * @return \Stripe\Customer
     *
     * @throws \Laravel\Cashier\Exceptions\CustomerAlreadyCreated
     */
    public function createAsStripeCustomer(array $options = [])
    {
        if ($this->hasStripeId()) {
            throw CustomerAlreadyCreated::exists($this);
        }

        if (! array_key_exists('email', $options) && $email = $this->stripeEmail()) {
            $options['email'] = $email;
        }

        // Here we will create the customer instance on Stripe and store the ID of the
        // user from Stripe. This ID will correspond with the Stripe user instances
        // and allow us to retrieve users from Stripe later when we need to work.
        $customer = StripeCustomer::create(
            $options, $this->stripeOptions()
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
            $this->stripe_id, $options, $this->stripeOptions()
        );
    }

    /**
     * Get the Stripe customer instance for the current user or create one.
     *
     * @param  array  $options
     * @return \Stripe\Customer
     */
    public function createOrGetStripeCustomer(array $options = [])
    {
        if ($this->hasStripeId()) {
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
        $this->assertCustomerExists();

        return StripeCustomer::retrieve($this->stripe_id, $this->stripeOptions());
    }

    /**
     * Get the email address used to create the customer in Stripe.
     *
     * @return string|null
     */
    public function stripeEmail()
    {
        return $this->email;
    }

    /**
     * Apply a coupon to the customer.
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
     * Get the Stripe supported currency used by the customer.
     *
     * @return string
     */
    public function preferredCurrency()
    {
        return config('cashier.currency');
    }

    /**
     * Get the Stripe billing portal for this customer.
     *
     * @param  string|null  $returnUrl
     * @return string
     */
    public function billingPortalUrl($returnUrl = null)
    {
        $this->assertCustomerExists();

        return StripeBillingPortalSession::create([
            'customer' => $this->stripeId(),
            'return_url' => $returnUrl ?? route('home'),
        ], $this->stripeOptions())['url'];
    }

    /**
     * Generate a redirect response to the customer's Stripe billing portal.
     *
     * @param  string|null  $returnUrl
     * @return \Illuminate\Http\RedirectResponse
     */
    public function redirectToBillingPortal($returnUrl = null)
    {
        return new RedirectResponse(
            $this->billingPortalUrl($returnUrl)
        );
    }

    /**
     * Get a collection of the customer's TaxID's.
     *
     * @return \Illuminate\Support\Collection|\Stripe\TaxId[]
     */
    public function taxIds(array $options = [])
    {
        $this->assertCustomerExists();

        return new Collection(
            StripeCustomer::allTaxIds($this->stripe_id, $options, $this->stripeOptions())->data
        );
    }

    /**
     * Find a TaxID by ID.
     *
     * @return \Stripe\TaxId|null
     */
    public function findTaxId($id)
    {
        $this->assertCustomerExists();

        try {
            return StripeCustomer::retrieveTaxId(
                $this->stripe_id, $id, [], $this->stripeOptions()
            );
        } catch (StripeInvalidRequestException $exception) {
            //
        }
    }

    /**
     * Create a TaxID for the customer.
     *
     * @param  string  $type
     * @param  string  $value
     * @return \Stripe\TaxId
     */
    public function createTaxId($type, $value)
    {
        $this->assertCustomerExists();

        return StripeCustomer::createTaxId($this->stripe_id, [
            'type' => $type,
            'value' => $value,
        ], $this->stripeOptions());
    }

    /**
     * Delete a TaxID for the customer.
     *
     * @param  string  $id
     * @return void
     */
    public function deleteTaxId($id)
    {
        $this->assertCustomerExists();

        try {
            StripeCustomer::deleteTaxId($this->stripe_id, $id, [], $this->stripeOptions());
        } catch (StripeInvalidRequestException $exception) {
            //
        }
    }

    /**
     * Determine if the customer is not exempted from taxes.
     *
     * @return bool
     */
    public function isNotTaxExempt()
    {
        return $this->asStripeCustomer()->tax_exempt === StripeCustomer::TAX_EXEMPT_NONE;
    }

    /**
     * Determine if the customer is exempted from taxes.
     *
     * @return bool
     */
    public function isTaxExempt()
    {
        return $this->asStripeCustomer()->tax_exempt === StripeCustomer::TAX_EXEMPT_EXEMPT;
    }

    /**
     * Determine if reverse charge applies to the customer.
     *
     * @return bool
     */
    public function reverseChargeApplies()
    {
        return $this->asStripeCustomer()->tax_exempt === StripeCustomer::TAX_EXEMPT_REVERSE;
    }

    /**
     * Get the default Stripe API options for the current Billable model.
     *
     * @param  array  $options
     * @return array
     */
    public function stripeOptions(array $options = [])
    {
        return Cashier::stripeOptions($options);
    }
}

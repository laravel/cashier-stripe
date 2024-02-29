<?php

namespace Laravel\Cashier\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\CustomerBalanceTransaction;
use Laravel\Cashier\Discount;
use Laravel\Cashier\Exceptions\CustomerAlreadyCreated;
use Laravel\Cashier\Exceptions\InvalidCustomer;
use Laravel\Cashier\PromotionCode;
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

        if (! array_key_exists('name', $options) && $name = $this->stripeName()) {
            $options['name'] = $name;
        }

        if (! array_key_exists('email', $options) && $email = $this->stripeEmail()) {
            $options['email'] = $email;
        }

        if (! array_key_exists('phone', $options) && $phone = $this->stripePhone()) {
            $options['phone'] = $phone;
        }

        if (! array_key_exists('address', $options) && $address = $this->stripeAddress()) {
            $options['address'] = $address;
        }

        if (! array_key_exists('preferred_locales', $options) && $locales = $this->stripePreferredLocales()) {
            $options['preferred_locales'] = $locales;
        }

        if (! array_key_exists('metadata', $options) && $metadata = $this->stripeMetadata()) {
            $options['metadata'] = $metadata;
        }

        // Here we will create the customer instance on Stripe and store the ID of the
        // user from Stripe. This ID will correspond with the Stripe user instances
        // and allow us to retrieve users from Stripe later when we need to work.
        $customer = static::stripe()->customers->create($options);

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
        return static::stripe()->customers->update(
            $this->stripe_id, $options
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
            return $this->asStripeCustomer($options['expand'] ?? []);
        }

        return $this->createAsStripeCustomer($options);
    }

    /**
     * Update the Stripe customer information for the current user or create one.
     *
     * @param  array  $options
     * @return \Stripe\Customer
     */
    public function updateOrCreateStripeCustomer(array $options = [])
    {
        if ($this->hasStripeId()) {
            return $this->updateStripeCustomer($options);
        }

        return $this->createAsStripeCustomer($options);
    }

    /**
     * Sync the customer's information to Stripe for the current user or create one.
     *
     * @param  array  $options
     * @return \Stripe\Customer
     */
    public function syncOrCreateStripeCustomer(array $options = [])
    {
        if ($this->hasStripeId()) {
            return $this->syncStripeCustomerDetails();
        }

        return $this->createAsStripeCustomer($options);
    }

    /**
     * Get the Stripe customer for the model.
     *
     * @param  array  $expand
     * @return \Stripe\Customer
     */
    public function asStripeCustomer(array $expand = [])
    {
        $this->assertCustomerExists();

        return static::stripe()->customers->retrieve(
            $this->stripe_id, ['expand' => $expand]
        );
    }

    /**
     * Get the name that should be synced to Stripe.
     *
     * @return string|null
     */
    public function stripeName()
    {
        return $this->name ?? null;
    }

    /**
     * Get the email address that should be synced to Stripe.
     *
     * @return string|null
     */
    public function stripeEmail()
    {
        return $this->email ?? null;
    }

    /**
     * Get the phone number that should be synced to Stripe.
     *
     * @return string|null
     */
    public function stripePhone()
    {
        return $this->phone ?? null;
    }

    /**
     * Get the address that should be synced to Stripe.
     *
     * @return array|null
     */
    public function stripeAddress()
    {
        return [];

        // return [
        //     'city' => 'Little Rock',
        //     'country' => 'US',
        //     'line1' => '1 Main St.',
        //     'line2' => 'Apartment 5',
        //     'postal_code' => '72201',
        //     'state' => 'Arkansas',
        // ];
    }

    /**
     * Get the locales that should be synced to Stripe.
     *
     * @return array|null
     */
    public function stripePreferredLocales()
    {
        return [];

        // return ['en'];
    }

    /**
     * Get the metadata that should be synced to Stripe.
     *
     * @return array|null
     */
    public function stripeMetadata()
    {
        return [];
    }

    /**
     * Sync the customer's information to Stripe.
     *
     * @return \Stripe\Customer
     */
    public function syncStripeCustomerDetails()
    {
        return $this->updateStripeCustomer([
            'name' => $this->stripeName(),
            'email' => $this->stripeEmail(),
            'phone' => $this->stripePhone(),
            'address' => $this->stripeAddress(),
            'preferred_locales' => $this->stripePreferredLocales(),
            'metadata' => $this->stripeMetadata(),
        ]);
    }

    /**
     * The discount that applies to the customer, if applicable.
     *
     * @return \Laravel\Cashier\Discount|null
     */
    public function discount()
    {
        $customer = $this->asStripeCustomer(['discount.promotion_code']);

        return $customer->discount
            ? new Discount($customer->discount)
            : null;
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

        $this->updateStripeCustomer([
            'coupon' => $coupon,
        ]);
    }

    /**
     * Apply a promotion code to the customer.
     *
     * @param  string  $promotionCodeId
     * @return void
     */
    public function applyPromotionCode($promotionCodeId)
    {
        $this->assertCustomerExists();

        $this->updateStripeCustomer([
            'promotion_code' => $promotionCodeId,
        ]);
    }

    /**
     * Retrieve a promotion code by its code.
     *
     * @param  string  $code
     * @param  array  $options
     * @return \Laravel\Cashier\PromotionCode|null
     */
    public function findPromotionCode($code, array $options = [])
    {
        $codes = static::stripe()->promotionCodes->all(array_merge([
            'code' => $code,
            'limit' => 1,
        ], $options));

        if ($codes && $promotionCode = $codes->first()) {
            return new PromotionCode($promotionCode);
        }
    }

    /**
     * Retrieve a promotion code by its code.
     *
     * @param  string  $code
     * @param  array  $options
     * @return \Laravel\Cashier\PromotionCode|null
     */
    public function findActivePromotionCode($code, array $options = [])
    {
        return $this->findPromotionCode($code, array_merge($options, ['active' => true]));
    }

    /**
     * Get the total balance of the customer.
     *
     * @return string
     */
    public function balance()
    {
        return $this->formatAmount($this->rawBalance());
    }

    /**
     * Get the raw total balance of the customer.
     *
     * @return int
     */
    public function rawBalance()
    {
        if (! $this->hasStripeId()) {
            return 0;
        }

        return $this->asStripeCustomer()->balance;
    }

    /**
     * Return a customer's balance transactions.
     *
     * @param  int  $limit
     * @param  array  $options
     * @return \Illuminate\Support\Collection
     */
    public function balanceTransactions($limit = 10, array $options = [])
    {
        if (! $this->hasStripeId()) {
            return new Collection();
        }

        $transactions = static::stripe()
            ->customers
            ->allBalanceTransactions($this->stripe_id, array_merge(['limit' => $limit], $options));

        return Collection::make($transactions->data)->map(function ($transaction) {
            return new CustomerBalanceTransaction($this, $transaction);
        });
    }

    /**
     * Credit a customer's balance.
     *
     * @param  int  $amount
     * @param  string|null  $description
     * @param  array  $options
     * @return \Laravel\Cashier\CustomerBalanceTransaction
     */
    public function creditBalance($amount, $description = null, array $options = [])
    {
        return $this->applyBalance(-$amount, $description, $options);
    }

    /**
     * Debit a customer's balance.
     *
     * @param  int  $amount
     * @param  string|null  $description
     * @param  array  $options
     * @return \Laravel\Cashier\CustomerBalanceTransaction
     */
    public function debitBalance($amount, $description = null, array $options = [])
    {
        return $this->applyBalance($amount, $description, $options);
    }

    /**
     * Apply a new amount to the customer's balance.
     *
     * @param  int  $amount
     * @param  string|null  $description
     * @param  array  $options
     * @return \Laravel\Cashier\CustomerBalanceTransaction
     */
    public function applyBalance($amount, $description = null, array $options = [])
    {
        $this->assertCustomerExists();

        $transaction = static::stripe()
            ->customers
            ->createBalanceTransaction($this->stripe_id, array_filter(array_merge([
                'amount' => $amount,
                'currency' => $this->preferredCurrency(),
                'description' => $description,
            ], $options)));

        return new CustomerBalanceTransaction($this, $transaction);
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
     * Format the given amount into a displayable currency.
     *
     * @param  int  $amount
     * @return string
     */
    protected function formatAmount($amount)
    {
        return Cashier::formatAmount($amount, $this->preferredCurrency());
    }

    /**
     * Get the Stripe billing portal for this customer.
     *
     * @param  string|null  $returnUrl
     * @param  array  $options
     * @return string
     */
    public function billingPortalUrl($returnUrl = null, array $options = [])
    {
        $this->assertCustomerExists();

        return static::stripe()->billingPortal->sessions->create(array_merge([
            'customer' => $this->stripeId(),
            'return_url' => $returnUrl ?? route('home'),
        ], $options))['url'];
    }

    /**
     * Generate a redirect response to the customer's Stripe billing portal.
     *
     * @param  string|null  $returnUrl
     * @param  array  $options
     * @return \Illuminate\Http\RedirectResponse
     */
    public function redirectToBillingPortal($returnUrl = null, array $options = [])
    {
        return new RedirectResponse(
            $this->billingPortalUrl($returnUrl, $options)
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
            static::stripe()->customers->allTaxIds($this->stripe_id, $options)->data
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
            return static::stripe()->customers->retrieveTaxId(
                $this->stripe_id, $id, []
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

        return static::stripe()->customers->createTaxId($this->stripe_id, [
            'type' => $type,
            'value' => $value,
        ]);
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
            static::stripe()->customers->deleteTaxId($this->stripe_id, $id);
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
     * Get the Stripe SDK client.
     *
     * @param  array  $options
     * @return \Stripe\StripeClient
     */
    public static function stripe(array $options = [])
    {
        return Cashier::stripe($options);
    }
}

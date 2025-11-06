<?php

namespace Laravel\Cashier\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Coupon;
use Laravel\Cashier\CustomerBalanceTransaction;
use Laravel\Cashier\Discount;
use Laravel\Cashier\Exceptions\CustomerAlreadyCreated;
use Laravel\Cashier\Exceptions\InvalidCoupon;
use Laravel\Cashier\Exceptions\InvalidCustomer;
use Laravel\Cashier\PromotionCode;
use Laravel\Cashier\Subscription;
use Stripe\Customer as StripeCustomer;
use Stripe\Exception\InvalidRequestException as StripeInvalidRequestException;

trait ManagesCustomer
{
    use InteractsWithStripe;

    /**
     * Retrieve the Stripe customer ID.
     *
     * @return string|null
     */
    public function stripeId(): ?string
    {
        return $this->stripe_id;
    }

    /**
     * Determine if the customer has a Stripe customer ID.
     *
     * @return bool
     */
    public function hasStripeId(): bool
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
    protected function assertCustomerExists(): void
    {
        if (! $this->hasStripeId()) {
            throw InvalidCustomer::notYetCreated($this);
        }
    }

    /**
     * Create a Stripe customer for the given model.
     *
     * @param  array  $options
     * @param  array  $requestOptions
     * @return \Stripe\Customer
     *
     * @throws \Laravel\Cashier\Exceptions\CustomerAlreadyCreated
     */
    public function createAsStripeCustomer(array $options = [], array $requestOptions = [])
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
        $customersService = static::stripe()->customers;

        $customer = $customersService->create($options, $requestOptions);

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
        /** @var \Stripe\Service\CustomerService $customersService */
        $customersService = static::stripe()->customers;

        return $customersService->update(
            $this->stripe_id, $options
        );
    }

    /**
     * Get the Stripe customer instance for the current user or create one.
     *
     * @param  array  $options
     * @param  array  $requestOptions
     * @return \Stripe\Customer
     */
    public function createOrGetStripeCustomer(array $options = [], array $requestOptions = [])
    {
        if ($this->hasStripeId()) {
            return $this->asStripeCustomer($options['expand'] ?? []);
        }

        return $this->createAsStripeCustomer($options, $requestOptions);
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

        /** @var \Stripe\Service\CustomerService $customersService */
        $customersService = static::stripe()->customers;

        return $customersService->retrieve(
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
    public function stripeEmail(): ?string
    {
        return $this->email ?? null;
    }

    /**
     * Get the phone number that should be synced to Stripe.
     *
     * @return string|null
     */
    public function stripePhone(): ?string
    {
        return $this->phone ?? null;
    }

    /**
     * Get the address that should be synced to Stripe.
     *
     * @return array|null
     */
    public function stripeAddress(): array
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
    public function stripePreferredLocales(): ?array
    {
        return [];

        // return ['en'];
    }

    /**
     * Get the metadata that should be synced to Stripe.
     *
     * @return array|null
     */
    public function stripeMetadata(): ?array
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
     * The discount that applies to the customer's primary subscription, if applicable.
     *
     * @return \Laravel\Cashier\Discount|null
     */
    public function discount(): ?Discount
    {
        // Customer-level discounts are no longer supported, check any active subscription...
        // Try default subscription first, then any active subscription...
        $subscription = $this->subscription()
            ?: $this->subscriptions->where('stripe_status', 'active')->first();

        if (! $subscription instanceof Subscription) {
            return null;
        }

        // Use the same expansion logic as the subscription's discount method...
        $stripeSubscription = $subscription->asStripeSubscription(['discounts.promotion_code']);

        if (isset($stripeSubscription->discounts) && ! empty($stripeSubscription->discounts)) {
            return new Discount($stripeSubscription->discounts[0]);
        }

        return null;
    }

    /**
     * Get all discounts that apply to the customer's subscriptions.
     *
     * @return \Illuminate\Support\Collection<int, \Laravel\Cashier\Discount>
     */
    public function discounts(): Collection
    {
        return $this->subscriptions->map(function ($subscription) {
            return $subscription->discounts();
        })->flatten();
    }

    /**
     * Apply a coupon to the customer's subscriptions.
     *
     * By default, applies to the primary subscription only.
     *
     * @param  string  $couponId
     * @param  string|array<int, string>|null  $subscriptionTypes
     * @return void
     *
     * @throws \Laravel\Cashier\Exceptions\InvalidCoupon
     */
    public function applyCoupon(string $couponId, string|array|null $subscriptionTypes = null): void
    {
        $this->assertCustomerExists();

        // Validate the coupon to ensure it's not a forever amount_off coupon...
        $this->validateCouponForCustomerApplication($couponId);

        $subscriptions = $this->getTargetSubscriptions($subscriptionTypes);

        foreach ($subscriptions as $subscription) {
            $subscription->updateStripeSubscription([
                'discounts' => [['coupon' => $couponId]],
            ]);
        }
    }

    /**
     * Apply a promotion code to the customer's subscriptions.
     *
     * By default, applies to the primary subscription only for safety.
     *
     * @param  string  $promotionCodeId
     * @param  string|array|null  $subscriptionTypes
     * @return void
     */
    public function applyPromotionCode(string $promotionCodeId, string|array|null $subscriptionTypes = null): void
    {
        $this->assertCustomerExists();

        $subscriptions = $this->getTargetSubscriptions($subscriptionTypes);

        foreach ($subscriptions as $subscription) {
            $subscription->updateStripeSubscription([
                'discounts' => [['promotion_code' => $promotionCodeId]],
            ]);
        }
    }

    /**
     * Apply a coupon to all active subscriptions.
     *
     * @param  string  $couponId
     * @return void
     *
     * @throws \Laravel\Cashier\Exceptions\InvalidCoupon
     */
    public function applyCouponToAllSubscriptions(string $couponId): void
    {
        $this->applyCoupon($couponId, '*');
    }

    /**
     * Apply a promotion code to all active subscriptions.
     *
     * @param  string  $promotionCodeId
     * @return void
     */
    public function applyPromotionCodeToAllSubscriptions(string $promotionCodeId): void
    {
        $this->applyPromotionCode($promotionCodeId, '*');
    }

    /**
     * Get the target subscriptions based on the provided criteria.
     *
     * @param  string|array<int, string>|null  $subscriptionTypes
     * @return \Illuminate\Support\Collection
     */
    protected function getTargetSubscriptions(string|array|null $subscriptionTypes = null): Collection
    {
        // If null, target the primary subscription only (safest default)...
        if ($subscriptionTypes === null) {
            // Try default subscription first, then fall back to the first active subscription...
            $primarySubscription = $this->subscription() ?: $this->subscriptions->where('stripe_status', 'active')->first();

            return $primarySubscription ? collect([$primarySubscription]) : collect([]);
        }

        // If '*', target all active subscriptions...
        if ($subscriptionTypes === '*') {
            return $this->subscriptions->where('stripe_status', 'active');
        }

        // If specific types provided, target those...
        $types = is_array($subscriptionTypes) ? $subscriptionTypes : [$subscriptionTypes];

        return $this->subscriptions->whereIn('type', $types)->where('stripe_status', 'active');
    }

    /**
     * Validate that a coupon can be applied to a customer.
     *
     * @param  string  $couponId
     * @return void
     *
     * @throws \Laravel\Cashier\Exceptions\InvalidCoupon
     * @throws \Stripe\Exception\ApiErrorException
     */
    protected function validateCouponForCustomerApplication(string $couponId): void
    {
        /** @var \Stripe\Service\CouponService $couponsService */
        $couponsService = static::stripe()->coupons;

        $stripeCoupon = $couponsService->retrieve($couponId);

        $coupon = new Coupon($stripeCoupon);

        if ($coupon->isForeverAmountOff()) {
            throw InvalidCoupon::foreverAmountOffCouponNotAllowed($couponId);
        }
    }

    /**
     * Retrieve a promotion code by its code.
     *
     * @param  string  $code
     * @param  array  $options
     * @return \Laravel\Cashier\PromotionCode|null
     */
    public function findPromotionCode(string $code, array $options = []): ?PromotionCode
    {
        /** @var \Stripe\Service\PromotionCodeService $promotionCodesService */
        $promotionCodesService = static::stripe()->promotionCodes;

        $codes = $promotionCodesService->all(array_merge([
            'code' => $code,
            'limit' => 1,
        ], $options));

        if ($codes && $promotionCode = $codes->first()) {
            return new PromotionCode($promotionCode);
        }

        return null;
    }

    /**
     * Retrieve a promotion code by its code.
     *
     * @param  string  $code
     * @param  array  $options
     * @return \Laravel\Cashier\PromotionCode|null
     */
    public function findActivePromotionCode(string $code, array $options = []): ?PromotionCode
    {
        return $this->findPromotionCode($code, array_merge($options, ['active' => true]));
    }

    /**
     * Get the total balance of the customer.
     *
     * @return string
     */
    public function balance(): string
    {
        return $this->formatAmount($this->rawBalance());
    }

    /**
     * Get the raw total balance of the customer.
     *
     * @return int
     */
    public function rawBalance(): int
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
    public function balanceTransactions(int $limit = 10, array $options = []): Collection
    {
        if (! $this->hasStripeId()) {
            return new Collection();
        }

        /** @var \Stripe\Service\CustomerService $customersService */
        $customersService = static::stripe()->customers;

        $transactions = $customersService->allBalanceTransactions(
            $this->stripe_id, array_merge(['limit' => $limit], $options)
        );

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
    public function creditBalance(int $amount, ?string $description = null, array $options = []): CustomerBalanceTransaction
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
    public function debitBalance(int $amount, ?string $description = null, array $options = []): CustomerBalanceTransaction
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
    public function applyBalance(int $amount, ?string $description = null, array $options = []): CustomerBalanceTransaction
    {
        $this->assertCustomerExists();

        /** @var \Stripe\Service\CustomerService $customersService */
        $customersService = static::stripe()->customers;

        $transaction = $customersService->createBalanceTransaction(
            $this->stripe_id,
            array_filter(array_merge([
                'amount' => $amount,
                'currency' => $this->preferredCurrency(),
                'description' => $description,
            ], $options))
        );

        return new CustomerBalanceTransaction($this, $transaction);
    }

    /**
     * Get the Stripe supported currency used by the customer.
     *
     * @return string
     */
    public function preferredCurrency(): string
    {
        return config('cashier.currency');
    }

    /**
     * Format the given amount into a displayable currency.
     *
     * @param  int  $amount
     * @return string
     */
    protected function formatAmount(int $amount): string
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
    public function billingPortalUrl(?string $returnUrl = null, array $options = []): string
    {
        $this->assertCustomerExists();

        /** @var \Stripe\Service\BillingPortal\SessionService $sessionsService */
        $sessionsService = static::stripe()->billingPortal->sessions;

        return $sessionsService->create(array_merge([
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
    public function redirectToBillingPortal(?string $returnUrl = null, array $options = []): RedirectResponse
    {
        return new RedirectResponse(
            $this->billingPortalUrl($returnUrl, $options)
        );
    }

    /**
     * Get a collection of the customer's TaxID's.
     *
     * @param  array  $options
     * @return \Illuminate\Support\Collection<int, Stripe\TaxId>
     */
    public function taxIds(array $options = []): Collection
    {
        $this->assertCustomerExists();

        /** @var \Stripe\Service\CustomerService $customersService */
        $customersService = static::stripe()->customers;

        return new Collection(
            $customersService->allTaxIds($this->stripe_id, $options)->data
        );
    }

    /**
     * Find a TaxID by ID.
     *
     * @param  string  $id
     * @return \Stripe\TaxId|null
     */
    public function findTaxId(string $id)
    {
        $this->assertCustomerExists();

        /** @var \Stripe\Service\CustomerService $customersService */
        $customersService = static::stripe()->customers;

        try {
            return $customersService->retrieveTaxId(
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
    public function createTaxId(string $type, string $value)
    {
        $this->assertCustomerExists();

        /** @var \Stripe\Service\CustomerService $customersService */
        $customersService = static::stripe()->customers;

        return $customersService->createTaxId($this->stripe_id, [
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
    public function deleteTaxId(string $id): void
    {
        $this->assertCustomerExists();

        /** @var \Stripe\Service\CustomerService $customersService */
        $customersService = static::stripe()->customers;

        try {
            $customersService->deleteTaxId($this->stripe_id, $id);
        } catch (StripeInvalidRequestException $exception) {
            //
        }
    }

    /**
     * Determine if the customer is not exempted from taxes.
     *
     * @return bool
     */
    public function isNotTaxExempt(): bool
    {
        return $this->asStripeCustomer()->tax_exempt === StripeCustomer::TAX_EXEMPT_NONE;
    }

    /**
     * Determine if the customer is exempted from taxes.
     *
     * @return bool
     */
    public function isTaxExempt(): bool
    {
        return $this->asStripeCustomer()->tax_exempt === StripeCustomer::TAX_EXEMPT_EXEMPT;
    }

    /**
     * Determine if reverse charge applies to the customer.
     *
     * @return bool
     */
    public function reverseChargeApplies(): bool
    {
        return $this->asStripeCustomer()->tax_exempt === StripeCustomer::TAX_EXEMPT_REVERSE;
    }
}

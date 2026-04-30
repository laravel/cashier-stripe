<?php

namespace Laravel\Cashier;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use Laravel\Cashier\Concerns\AllowsCoupons;
use Laravel\Cashier\Concerns\HandlesTaxes;

class CheckoutBuilder
{
    use AllowsCoupons;
    use HandlesTaxes;

    /**
     * The Stripe model instance.
     *
     * @var \Illuminate\Database\Eloquent\Model|null
     */
    protected $owner;

    /**
     * The billing mode for the subscription.
     *
     * @var array|null
     */
    protected $billingMode = null;

    /**
     * Create a new checkout builder instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model|null  $owner
     * @param  object|null  $parentInstance
     * @return void
     */
    public function __construct($owner = null, ?object $parentInstance = null)
    {
        $this->owner = $owner;

        if ($parentInstance && in_array(AllowsCoupons::class, class_uses_recursive($parentInstance))) {
            $this->couponId = $parentInstance->couponId;
            $this->promotionCodeId = $parentInstance->promotionCodeId;
            $this->allowPromotionCodes = $parentInstance->allowPromotionCodes;
        }

        if ($parentInstance && in_array(HandlesTaxes::class, class_uses_recursive($parentInstance))) {
            $this->customerIpAddress = $parentInstance->customerIpAddress;
            $this->estimationBillingAddress = $parentInstance->estimationBillingAddress;
            $this->collectTaxIds = $parentInstance->collectTaxIds;
        }

        if ($parentInstance) {
            // Use reflection to access the protected billingMode property if it exists
            try {
                $reflection = new \ReflectionClass($parentInstance);
                if ($reflection->hasProperty('billingMode')) {
                    $property = $reflection->getProperty('billingMode');
                    $billingMode = $property->getValue($parentInstance);
                    if ($billingMode !== null) {
                        $this->billingMode = $billingMode;
                    }
                }
            } catch (\ReflectionException $e) {
                // Ignore reflection errors
            }
        }
    }

    /**
     * Create a new checkout builder instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model|null  $owner
     * @param  object|null  $instance
     * @return \Laravel\Cashier\CheckoutBuilder
     */
    public static function make($owner = null, ?object $instance = null)
    {
        return new static($owner, $instance);
    }

    /**
     * Set the billing mode for the subscription.
     *
     * @param  string  $type
     * @return $this
     */
    public function withBillingMode($type = 'flexible')
    {
        $this->billingMode = ['type' => $type];

        return $this;
    }

    /**
     * Get the default billing mode from config.
     *
     * @return string
     */
    protected function getDefaultBillingMode()
    {
        return config('cashier.default_billing_mode', 'classic');
    }

    /**
     * Get the effective billing mode.
     *
     * @return string
     */
    protected function getEffectiveBillingMode()
    {
        return $this->billingMode['type'] ?? $this->getDefaultBillingMode();
    }

    /**
     * Get the billing mode for the Stripe payload.
     *
     * @return array|null
     */
    protected function getBillingModeForPayload()
    {
        $effectiveMode = $this->getEffectiveBillingMode();

        // Only include billing_mode in payload if it's flexible
        // Classic mode is Stripe's default, so we omit it for backwards compatibility
        if ($effectiveMode === 'flexible') {
            $this->validateFlexibleBillingSupport();

            return ['type' => 'flexible'];
        }

        return null;
    }

    /**
     * Validate that flexible billing mode is supported by the current API version.
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    protected function validateFlexibleBillingSupport()
    {
        $apiVersion = config('cashier.stripe.api_version') ?? \Stripe\Stripe::getApiVersion();

        if ($apiVersion && version_compare($apiVersion, '2025-06-30', '<')) {
            throw new InvalidArgumentException(
                'Flexible billing mode requires Stripe API version 2025-06-30.basil or later. '.
                'Current version: '.$apiVersion.'. Please update your API version.'
            );
        }
    }

    /**
     * Create a new checkout session for subscriptions.
     *
     * @param  array|string  $items
     * @param  array  $sessionOptions
     * @param  array  $customerOptions
     * @return \Laravel\Cashier\Checkout
     */
    public function createSubscription($items, array $sessionOptions = [], array $customerOptions = [])
    {
        $sessionOptions['mode'] = 'subscription';

        return $this->create($items, $sessionOptions, $customerOptions);
    }

    /**
     * Create a new checkout session.
     *
     * @param  array|string  $items
     * @param  array  $sessionOptions
     * @param  array  $customerOptions
     * @return \Laravel\Cashier\Checkout
     */
    public function create(string|array $items, array $sessionOptions = [], array $customerOptions = []): Checkout
    {
        $payload = array_filter([
            'allow_promotion_codes' => $this->allowPromotionCodes,
            'automatic_tax' => $this->automaticTaxPayload(),
            'discounts' => $this->checkoutDiscounts(),
            'line_items' => Collection::make((array) $items)->map(function ($item, $key) {
                if (is_string($key)) {
                    return ['price' => $key, 'quantity' => $item];
                }

                $item = is_string($item) ? ['price' => $item] : $item;

                $item['quantity'] = $item['quantity'] ?? 1;

                return $item;
            })->values()->all(),
            'tax_id_collection' => (Cashier::$calculatesTaxes ?: $this->collectTaxIds)
                ? ['enabled' => true]
                : [],
        ]);

        // Add billing mode for subscription mode only
        if (($sessionOptions['mode'] ?? null) === 'subscription') {
            if ($billingMode = $this->getBillingModeForPayload()) {
                $payload['subscription_data']['billing_mode'] = $billingMode;
            }
        }

        return Checkout::create($this->owner, array_merge($payload, $sessionOptions), $customerOptions);
    }
}

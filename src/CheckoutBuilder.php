<?php

namespace Laravel\Cashier;

use Illuminate\Support\Collection;
use Laravel\Cashier\Concerns\AllowsCoupons;
use Laravel\Cashier\Concerns\HandlesTaxes;

class CheckoutBuilder
{
    use AllowsCoupons;
    use HandlesTaxes;

    /**
     * Create a new checkout builder instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model|null  $owner
     * @param  object|null  $parentInstance
     * @return void
     */
    public function __construct(protected $owner = null, ?object $parentInstance = null)
    {
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

        return Checkout::create($this->owner, array_merge($payload, $sessionOptions), $customerOptions);
    }
}

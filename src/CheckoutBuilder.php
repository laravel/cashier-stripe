<?php

namespace Laravel\Cashier;
use Laravel\Cashier\Concerns\AllowsCoupons;
use Laravel\Cashier\Concerns\HandlesTaxes;
use Illuminate\Support\Collection;

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
     * Create a new checkout builder instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model|null  $owner
     * @param  object|null  $subject
     * @return void
     */
    public function __construct($owner = null, $subject = null)
    {
        $this->owner = $owner;

        if ($subject && in_array(AllowsCoupons::class, class_uses_recursive($subject))) {
            $this->couponId = $subject->couponId;
            $this->promotionCodeId = $subject->promotionCodeId;
            $this->allowPromotionCodes = $subject->allowPromotionCodes;
        }

        if ($subject && in_array(HandlesTaxes::class, class_uses_recursive($subject))) {
            $this->customerIpAddress = $subject->customerIpAddress;
            $this->estimationBillingAddress = $subject->estimationBillingAddress;
            $this->collectTaxIds = $subject->collectTaxIds;
        }
    }

    /**
     * Create a new checkout builder instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model|null  $owner
     * @param  object|null  $instance
     * @return \Laravel\Cashier\CheckoutBuilder
     */
    public static function make($owner = null, $instance = null)
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
    public function create($items, array $sessionOptions = [], array $customerOptions = [])
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
            'tax_id_collection' => [
                'enabled' => Cashier::$calculatesTaxes ?: $this->collectTaxIds,
            ],
        ]);

        return Checkout::create($this->owner, array_merge($payload, $sessionOptions), $customerOptions);
    }
}

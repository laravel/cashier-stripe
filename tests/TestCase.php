<?php

namespace Laravel\Cashier\Tests;

use Stripe\Stripe;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public $stripe_prefix = 'cashier-test-';
    public $plan_1_id;
    public $plan_2_id;
    public $coupon_1_id;

    protected function setUp()
    {
        Stripe::setApiVersion('2019-03-14');
        $this->plan_1_id = $this->stripe_prefix.'monthly-10-'.str_random(10);
        $this->plan_2_id = $this->stripe_prefix.'monthly-10-'.str_random(10);
        $this->coupon_1_id = $this->stripe_prefix.'coupon-'.str_random(10);

        $this->bootstrapStripe();
    }

    public function bootstrapStripe()
    {
        Stripe::setApiKey(getenv('STRIPE_SECRET'));

        $items = [
            'products' => [
                [
                    'id' => $this->stripe_prefix.'product-1',
                    'name' => 'Laravel Cashier Test Product',
                    'type' => 'service',
                ],
            ],
            'plans' => [
                [
                    'id' => $this->plan_1_id,
                    'currency' => 'Monthly $10 Test 1',
                    'currency' => 'USD',
                    'interval' => 'month',
                    'billing_scheme' => 'per_unit',
                    'amount' => 1000,
                    'product' => $this->stripe_prefix.'product-1',
                ],
                [
                    'id' => $this->plan_2_id,
                    'currency' => 'Monthly $10 Test 2',
                    'currency' => 'USD',
                    'interval' => 'month',
                    'billing_scheme' => 'per_unit',
                    'amount' => 1000,
                    'product' => $this->stripe_prefix.'product-1',
                ],
            ],
            'coupons' => [
                [
                    'id' => $this->coupon_1_id,
                    'duration' => 'repeating',
                    'amount_off' => 500,
                    'duration_in_months' => 3,
                    'currency' => 'USD',
                ],
            ],
        ];

        foreach ($items as $item_key => $items) {
            $stripe_class = '\Stripe\\'.ucfirst(str_singular($item_key));
            if (! $this->checkStripeItems($items, $stripe_class)) {
                $this->deleteExistingStripeItems($items, $stripe_class);
                $this->createStripeItems($items, $stripe_class);
            }
        }
    }

    /**
     * Check if the Stripe items are properly set.
     * @param array $items
     * @param string $stripe_class
     */
    public function checkStripeItems(array $items, string $stripe_class)
    {
        foreach ($items as $item) {
            try {
                $stripe_item = $stripe_class::retrieve($item['id']);
            } catch (\Stripe\Error\InvalidRequest $e) {
                return false;
            }
            if ($stripe_item) {
                // If the item exists, check that all the keys match
                foreach ($item as $item_key => $item_value) {
                    if (strtolower($stripe_item->$item_key) != strtolower($item_value)) {
                        return false;
                    }
                }
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * Delete any Stripe items matching the ones in the array.
     * @param array $items
     * @param string $stripe_class
     */
    public function deleteExistingStripeItems(array $items, string $stripe_class)
    {
        foreach ($items as $item) {
            try {
                $stripe_item = $stripe_class::retrieve($item['id']);
            } catch (\Stripe\Error\InvalidRequest $e) {
                continue;
            }
            if ($stripe_item) {
                $stripe_item->delete();
            }
        }
    }

    /**
     * Create a Stripe item using the data from the array.
     * @param array $items
     * @param string $stripe_class
     */
    public function createStripeItems(array $items, string $stripe_class)
    {
        foreach ($items as $item) {
            $stripe_class::create($item);
        }
    }
}

<?php

namespace Laravel\Cashier\Tests\Feature;

use Stripe\Exception\CardException as StripeCardException;

class PendingUpdatesTest extends FeatureTestCase
{
    /**
     * @var string
     */
    protected static $productId;

    /**
     * @var string
     */
    protected static $priceId;

    /**
     * @var string
     */
    protected static $otherPriceId;

    /**
     * @var string
     */
    protected static $premiumPriceId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        static::$productId = self::stripe()->products->create([
            'name' => 'Laravel Cashier Test Product',
            'type' => 'service',
        ])->id;

        static::$priceId = self::stripe()->prices->create([
            'product' => static::$productId,
            'nickname' => 'Monthly $10',
            'currency' => 'USD',
            'recurring' => [
                'interval' => 'month',
            ],
            'billing_scheme' => 'per_unit',
            'unit_amount' => 1000,
        ])->id;

        static::$otherPriceId = self::stripe()->prices->create([
            'product' => static::$productId,
            'nickname' => 'Monthly $10 Other',
            'currency' => 'USD',
            'recurring' => [
                'interval' => 'month',
            ],
            'billing_scheme' => 'per_unit',
            'unit_amount' => 1000,
        ])->id;

        static::$premiumPriceId = self::stripe()->prices->create([
            'product' => static::$productId,
            'nickname' => 'Monthly $20 Premium',
            'currency' => 'USD',
            'recurring' => [
                'interval' => 'month',
            ],
            'billing_scheme' => 'per_unit',
            'unit_amount' => 2000,
        ])->id;
    }

    public function test_subscription_can_error_if_incomplete()
    {
        $user = $this->createCustomer('subscription_can_error_if_incomplete');

        $subscription = $user->newSubscription('main', static::$priceId)->create('pm_card_visa');

        // Set a faulty card as the customer's default payment method.
        $user->updateDefaultPaymentMethod('pm_card_threeDSecure2Required');

        try {
            // Attempt to swap and pay with a faulty card.
            $subscription = $subscription->errorIfPaymentFails()->swapAndInvoice(static::$premiumPriceId);

            $this->fail('Expected exception '.StripeCardException::class.' was not thrown.');
        } catch (StripeCardException $e) {
            // Assert that the price was not swapped.
            $this->assertEquals(static::$priceId, $subscription->refresh()->stripe_price);

            // Assert subscription is active.
            $this->assertTrue($subscription->active());
        }
    }

    // public function test_subscription_can_be_pending_if_incomplete()
    // {
    //     $user = $this->createCustomer('subscription_can_be_pending_if_incomplete');
    //
    //     $subscription = $user->newSubscription('main', static::$priceId)->create('pm_card_visa');
    //
    //     // Set a faulty card as the customer's default payment method.
    //     $user->updateDefaultPaymentMethod('pm_card_threeDSecure2Required');
    //
    //     try {
    //         // Attempt to swap and pay with a faulty card.
    //         $subscription = $subscription->pendingIfPaymentFails()->swapAndInvoice(static::$premiumPriceId);
    //
    //         $this->fail('Expected exception '.IncompletePayment::class.' was not thrown.');
    //     } catch (IncompletePayment $e) {
    //         // Assert that the payment needs an extra action.
    //         $this->assertTrue($e->payment->requiresAction());
    //
    //         // Assert that the price was not swapped.
    //         $this->assertEquals(static::$priceId, $subscription->refresh()->stripe_price);
    //
    //         // Assert subscription is active.
    //         $this->assertTrue($subscription->active());
    //
    //         // Assert subscription has pending updates.
    //         $this->assertTrue($subscription->pending());
    //
    //         // Void the last invoice to cancel any pending updates.
    //         $subscription->latestInvoice()->void();
    //
    //         // Assert subscription has no more pending updates.
    //         $this->assertFalse($subscription->pending());
    //     }
    // }
}

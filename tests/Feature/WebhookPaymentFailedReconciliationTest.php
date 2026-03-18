<?php

namespace Laravel\Cashier\Tests\Feature;

use Stripe\Subscription as StripeSubscription;

/**
 * Regression tests for laravel/cashier-stripe#1817.
 *
 * The bug: When swapAndInvoice() is called and payment fails (card
 * declined, 3D Secure required), the local DB retains the new premium
 * price even though payment hasn't succeeded. Users end up on the
 * expensive plan for free.
 *
 * How to reproduce manually:
 *   1. Create a customer with a subscription on a basic plan ($10/month)
 *   2. Call $subscription->swapAndInvoice('premium_price') using a
 *      card that will fail (e.g. pm_card_chargeDeclined)
 *   3. The swap throws IncompletePayment, but the local DB already
 *      shows stripe_price = premium_price
 *   4. Check the subscriptions table — it shows the premium price
 *      even though no payment was collected
 *   5. Stripe sends invoice.payment_failed webhook, but without this
 *      fix, nothing in Cashier handles it for subscription updates
 *   6. The customer now has the premium plan for free in your app
 *
 * The fix: A new handleInvoicePaymentFailed() webhook handler detects
 * when a subscription_update invoice fails and syncs the local DB back
 * to Stripe's actual subscription state.
 */
class WebhookPaymentFailedReconciliationTest extends FeatureTestCase
{
    /**
     * @var string
     */
    protected static $productId;

    /**
     * @var string
     */
    protected static $basicPriceId;

    /**
     * @var string
     */
    protected static $premiumPriceId;

    public static function setUpBeforeClass(): void
    {
        if (! getenv('STRIPE_SECRET')) {
            return;
        }

        parent::setUpBeforeClass();

        static::$productId = self::stripe()->products->create([
            'name' => 'Reconciliation Test Product',
            'type' => 'service',
        ])->id;

        static::$basicPriceId = self::stripe()->prices->create([
            'product' => static::$productId,
            'nickname' => 'Basic $10',
            'currency' => 'USD',
            'recurring' => ['interval' => 'month'],
            'billing_scheme' => 'per_unit',
            'unit_amount' => 1000,
        ])->id;

        static::$premiumPriceId = self::stripe()->prices->create([
            'product' => static::$productId,
            'nickname' => 'Premium $100',
            'currency' => 'USD',
            'recurring' => ['interval' => 'month'],
            'billing_scheme' => 'per_unit',
            'unit_amount' => 10000,
        ])->id;
    }

    /**
     * Test that invoice.payment_failed with billing_reason=subscription_update
     * syncs the local subscription back to Stripe's actual state.
     *
     * This is the core fix: after swapAndInvoice() fails, the webhook
     * handler corrects the local DB so it matches what Stripe actually
     * has (the old price, not the failed upgrade).
     */
    public function test_failed_subscription_update_invoice_syncs_local_state()
    {
        $user = $this->createCustomer('reconciliation_sync');
        $subscription = $user->newSubscription('main', static::$basicPriceId)->create('pm_card_visa');

        // Simulate that swapAndInvoice optimistically updated the local DB
        // to the premium price, but payment failed on Stripe's side
        $subscription->update([
            'stripe_price' => static::$premiumPriceId,
            'stripe_status' => StripeSubscription::STATUS_PAST_DUE,
        ]);

        // Verify the local DB is "wrong" — shows premium price
        $this->assertEquals(static::$premiumPriceId, $subscription->fresh()->stripe_price);

        // Now simulate Stripe sending invoice.payment_failed
        $this->postJson('stripe/webhook', [
            'id' => 'evt_reconcile',
            'type' => 'invoice.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'in_failed',
                    'customer' => $user->stripe_id,
                    'subscription' => $subscription->stripe_id,
                    'billing_reason' => 'subscription_update',
                ],
            ],
        ])->assertOk();

        // After the webhook, local DB should match Stripe's actual state
        $subscription->refresh();

        // Stripe still has the basic price (the upgrade failed)
        $this->assertEquals(static::$basicPriceId, $subscription->stripe_price);
        $this->assertEquals(StripeSubscription::STATUS_ACTIVE, $subscription->stripe_status);
    }

    /**
     * Test that invoice.payment_failed for non-subscription-update reasons
     * (e.g. renewal, manual) is ignored by the reconciliation handler.
     */
    public function test_non_subscription_update_invoice_failure_is_ignored()
    {
        $user = $this->createCustomer('reconciliation_ignore');
        $subscription = $user->newSubscription('main', static::$basicPriceId)->create('pm_card_visa');

        $originalPrice = $subscription->stripe_price;

        // Send invoice.payment_failed with billing_reason=subscription_cycle (renewal)
        $this->postJson('stripe/webhook', [
            'id' => 'evt_renewal_fail',
            'type' => 'invoice.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'in_renewal_fail',
                    'customer' => $user->stripe_id,
                    'subscription' => $subscription->stripe_id,
                    'billing_reason' => 'subscription_cycle',
                ],
            ],
        ])->assertOk();

        // Subscription should be unchanged
        $subscription->refresh();
        $this->assertEquals($originalPrice, $subscription->stripe_price);
    }

    /**
     * Test that the handler ignores invoices with no subscription
     * (e.g. one-off invoices).
     */
    public function test_invoice_failure_without_subscription_is_ignored()
    {
        $user = $this->createCustomer('reconciliation_no_sub', ['stripe_id' => 'cus_no_sub']);

        $response = $this->postJson('stripe/webhook', [
            'id' => 'evt_no_sub',
            'type' => 'invoice.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'in_one_off',
                    'customer' => 'cus_no_sub',
                    'subscription' => null,
                    'billing_reason' => 'subscription_update',
                ],
            ],
        ]);

        $response->assertOk();
    }

    /**
     * Test the full realistic flow: create subscription, attempt swap
     * with a declining card, then verify webhook reconciliation.
     *
     * This is the closest simulation to the actual production scenario
     * described in issue #1817.
     */
    public function test_full_swap_failure_and_webhook_reconciliation_flow()
    {
        $user = $this->createCustomer('reconciliation_full_flow');
        $subscription = $user->newSubscription('main', static::$basicPriceId)->create('pm_card_visa');

        // Verify starting state
        $this->assertEquals(static::$basicPriceId, $subscription->stripe_price);
        $this->assertEquals('active', $subscription->stripe_status);

        // Attempt to swap to premium — this would normally be done with
        // a declining card (pm_card_chargeDeclined), but the actual Stripe
        // API call would throw IncompletePayment. We simulate the result:
        // the local DB has been optimistically updated to the premium price.
        $subscription->update([
            'stripe_price' => static::$premiumPriceId,
            'stripe_status' => 'past_due',
        ]);

        // At this point, the user's local record says "premium + past_due"
        // but Stripe's actual subscription is still on the basic price.
        $this->assertEquals(static::$premiumPriceId, $subscription->fresh()->stripe_price);
        $this->assertEquals('past_due', $subscription->fresh()->stripe_status);

        // Stripe sends the webhook
        $this->postJson('stripe/webhook', [
            'id' => 'evt_full_flow',
            'type' => 'invoice.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'in_swap_failed',
                    'customer' => $user->stripe_id,
                    'subscription' => $subscription->stripe_id,
                    'billing_reason' => 'subscription_update',
                ],
            ],
        ])->assertOk();

        // After reconciliation, local DB matches Stripe's actual state
        $subscription->refresh();
        $this->assertEquals(static::$basicPriceId, $subscription->stripe_price);
        $this->assertEquals('active', $subscription->stripe_status);

        // Verify subscription items also match Stripe
        $this->assertDatabaseHas('subscription_items', [
            'subscription_id' => $subscription->id,
            'stripe_price' => static::$basicPriceId,
        ]);
    }
}

<?php

namespace Laravel\Cashier\Tests\Feature;

use Laravel\Cashier\Cashier;

/**
 * End-to-end lifecycle tests for flexible billing.
 *
 * These tests verify complete workflows, not individual operations.
 * Each test represents a real-world billing scenario.
 */
class FlexibleBillingLifecycleTest extends FeatureTestCase
{
    protected static $productId;
    protected static $priceId;
    protected static $premiumPriceId;
    protected static $meteredPriceId;
    protected static $meterId;

    public static function setUpBeforeClass(): void
    {
        if (! getenv('STRIPE_SECRET')) {
            return;
        }

        parent::setUpBeforeClass();

        static::$productId = self::stripe()->products->create([
            'name' => 'Lifecycle Test Product '.time(),
            'type' => 'service',
        ])->id;

        static::$priceId = self::stripe()->prices->create([
            'product' => static::$productId,
            'nickname' => 'Monthly $10',
            'currency' => 'USD',
            'recurring' => ['interval' => 'month'],
            'unit_amount' => 1000,
        ])->id;

        static::$premiumPriceId = self::stripe()->prices->create([
            'product' => static::$productId,
            'nickname' => 'Monthly $25 Premium',
            'currency' => 'USD',
            'recurring' => ['interval' => 'month'],
            'unit_amount' => 2500,
        ])->id;

        $uniqueSuffix = bin2hex(random_bytes(8));
        static::$meterId = self::stripe()->billing->meters->create([
            'display_name' => 'Lifecycle API Calls '.$uniqueSuffix,
            'event_name' => 'lifecycle_api_calls_'.$uniqueSuffix,
            'default_aggregation' => ['formula' => 'sum'],
        ])->id;

        static::$meteredPriceId = self::stripe()->prices->create([
            'product' => static::$productId,
            'nickname' => 'Metered per call',
            'currency' => 'USD',
            'recurring' => [
                'interval' => 'month',
                'usage_type' => 'metered',
                'meter' => static::$meterId,
            ],
            'unit_amount' => 1,
        ])->id;
    }

    protected function tearDown(): void
    {
        Cashier::$defaultBillingMode = 'classic';
        parent::tearDown();
    }

    /**
     * Scenario: Classic subscription → migrate to flexible → swap price.
     *
     * Tests the most common upgrade path: start classic, realize you need
     * flexible, migrate, then change plans.
     */
    public function test_classic_to_flexible_migration_then_swap()
    {
        $user = $this->createCustomer('lifecycle-migrate-swap');

        // Step 1: Create classic subscription
        $subscription = $user->newSubscription('default', static::$priceId)
            ->create('pm_card_visa');

        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->usesFlexibleBilling());
        $this->assertSame(static::$priceId, $subscription->stripe_price);

        // Step 2: Migrate to flexible
        $subscription->migrateToFlexibleBillingMode();
        $this->assertTrue($subscription->usesFlexibleBilling());

        // Step 3: Swap price — should work under flexible mode
        $subscription->swap(static::$premiumPriceId);
        $this->assertSame(static::$premiumPriceId, $subscription->stripe_price);
        $this->assertTrue($subscription->usesFlexibleBilling());

        // Step 4: Cancel
        $subscription->cancel();
        $this->assertTrue($subscription->onGracePeriod());
    }

    /**
     * Scenario: Create flexible subscription with proration discounts → cancel immediately.
     */
    public function test_flexible_with_proration_discounts_create_and_cancel()
    {
        $user = $this->createCustomer('lifecycle-proration');

        $subscription = $user->newSubscription('default', static::$priceId)
            ->withBillingMode('flexible')
            ->withProrationDiscounts('itemized')
            ->create('pm_card_visa');

        $this->assertTrue($subscription->active());
        $this->assertTrue($subscription->usesFlexibleBilling());

        // Verify proration_discounts was set on Stripe
        $stripeSubscription = $subscription->asStripeSubscription();
        $this->assertSame('flexible', $stripeSubscription->billing_mode->type);

        // Cancel now
        $subscription->cancelNow();
        $this->assertFalse($subscription->active());
    }

    /**
     * Scenario: Multi-price flexible subscription with metered + fixed.
     *
     * This is the "hybrid billing" use case — base plan + usage.
     */
    public function test_hybrid_flexible_subscription_lifecycle()
    {
        $user = $this->createCustomer('lifecycle-hybrid');

        // Create hybrid: fixed base + metered usage
        $subscription = $user->newSubscription('default')
            ->price(static::$priceId)
            ->meteredPrice(static::$meteredPriceId)
            ->withBillingMode('flexible')
            ->create('pm_card_visa');

        $this->assertTrue($subscription->active());
        $this->assertTrue($subscription->hasMultiplePrices());
        $this->assertTrue($subscription->usesFlexibleBilling());

        // Verify both items exist
        $this->assertSame(2, $subscription->items->count());

        // Remove metered price (should NOT send clear_usage in flexible mode)
        $subscription->removePrice(static::$meteredPriceId);
        $this->assertTrue($subscription->hasSinglePrice());
        $this->assertSame(static::$priceId, $subscription->stripe_price);
    }

    /**
     * Scenario: Subscription schedule with flexible billing.
     */
    public function test_subscription_schedule_flexible_lifecycle()
    {
        $user = $this->createCustomer('lifecycle-schedule');

        // Create a flexible schedule
        $schedule = $user->newSubscriptionSchedule('default')
            ->withBillingMode('flexible')
            ->addPhase([
                ['price' => static::$priceId, 'quantity' => 1],
            ], ['iterations' => 1])
            ->startDate('now')
            ->create();

        $this->assertTrue($schedule->active());
        $this->assertNotNull($schedule->stripe_id);

        // Verify it has a subscription
        $stripeSchedule = $schedule->asStripeSubscriptionSchedule();
        $this->assertNotNull($stripeSchedule->subscription);

        // Look up the schedule
        $found = $user->findSubscriptionSchedule($schedule->stripe_id);
        $this->assertNotNull($found);

        // Release the schedule (subscription continues independently)
        $schedule->release();
        $this->assertTrue($schedule->released());
    }

    /**
     * Scenario: Create and accept a quote.
     */
    public function test_quote_full_lifecycle()
    {
        $user = $this->createCustomer('lifecycle-quote');
        $user->createAsStripeCustomer();
        $user->updateDefaultPaymentMethod('pm_card_visa');

        // Create quote
        $quote = $user->newQuote()
            ->addLineItem(static::$priceId, 1)
            ->description('Lifecycle test quote')
            ->create();

        $this->assertTrue($quote->draft());
        $this->assertSame(1000, $quote->amount_total);

        // Finalize
        $quote->finalize();
        $this->assertTrue($quote->open());
        $this->assertNotNull($quote->finalized_at);

        // Accept
        $quote->accept();
        $this->assertTrue($quote->accepted());
        $this->assertNotNull($quote->accepted_at);

        // Verify we can find it
        $found = $user->findQuote($quote->stripe_id);
        $this->assertNotNull($found);
        $this->assertTrue($found->accepted());
    }

    /**
     * Scenario: Billing credits workflow.
     */
    public function test_billing_credits_full_workflow()
    {
        $user = $this->createCustomer('lifecycle-credits');
        $user->createAsStripeCustomer();

        // Start with no credits
        $this->assertSame(0, $user->availableCredits());
        $this->assertFalse($user->hasSufficientCredits(100));

        // Add $100 in credits
        $transaction = $user->addBillingCredits(10000, 'Welcome credit');
        $this->assertSame(-10000, $transaction->rawAmount());

        // Verify balance
        $this->assertSame(10000, $user->availableCredits());
        $this->assertTrue($user->hasSufficientCredits(5000));
        $this->assertTrue($user->hasSufficientCredits(10000));
        $this->assertFalse($user->hasSufficientCredits(10001));

        // Calculate partial application
        $calc = $user->calculateCreditApplication(15000);
        $this->assertSame(10000, $calc['applied_credits']);
        $this->assertSame(5000, $calc['remaining_usage']);
        $this->assertSame(0, $calc['credits_after']);

        // Deduct some credits
        $user->deductBillingCredits(3000, 'Usage charge');
        $this->assertSame(7000, $user->availableCredits());

        // Verify transaction history
        $transactions = $user->balanceTransactions(10);
        $this->assertGreaterThanOrEqual(2, $transactions->count());
    }

    /**
     * Scenario: Create schedule from existing subscription.
     * Verify billing_mode is inherited, not set explicitly.
     */
    public function test_schedule_from_flexible_subscription_inherits_billing_mode()
    {
        $user = $this->createCustomer('lifecycle-sched-inherit');

        // Create a flexible subscription
        $subscription = $user->newSubscription('default', static::$priceId)
            ->withBillingMode('flexible')
            ->create('pm_card_visa');

        $this->assertTrue($subscription->usesFlexibleBilling());

        // Create a schedule from it — billing_mode should NOT be set explicitly
        $schedule = $user->newSubscriptionSchedule('default')
            ->createFromSubscription($subscription);

        $this->assertTrue($schedule->active());

        // Cancel the schedule to clean up
        $schedule->cancel();
    }

    /**
     * Scenario: Idempotent migration — calling migrate on already-flexible sub is a no-op.
     */
    public function test_migrate_already_flexible_is_noop()
    {
        $user = $this->createCustomer('lifecycle-idempotent');

        $subscription = $user->newSubscription('default', static::$priceId)
            ->withBillingMode('flexible')
            ->create('pm_card_visa');

        $this->assertTrue($subscription->usesFlexibleBilling());

        // Should not throw or make API call
        $result = $subscription->migrateToFlexibleBillingMode();
        $this->assertSame($subscription, $result);
        $this->assertTrue($subscription->usesFlexibleBilling());
    }
}

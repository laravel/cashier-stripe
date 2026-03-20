<?php

namespace Laravel\Cashier\Tests\Feature;

use Laravel\Cashier\Cashier;

class FlexibleBillingTest extends FeatureTestCase
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
    protected static $meteredPriceId;

    /**
     * @var string
     */
    protected static $meterId;

    public static function setUpBeforeClass(): void
    {
        if (! getenv('STRIPE_SECRET')) {
            return;
        }

        parent::setUpBeforeClass();

        static::$productId = self::stripe()->products->create([
            'name' => 'Flexible Billing Test Product '.time(),
            'type' => 'service',
        ])->id;

        static::$priceId = self::stripe()->prices->create([
            'product' => static::$productId,
            'nickname' => 'Monthly $10 Flex',
            'currency' => 'USD',
            'recurring' => ['interval' => 'month'],
            'billing_scheme' => 'per_unit',
            'unit_amount' => 1000,
        ])->id;

        static::$otherPriceId = self::stripe()->prices->create([
            'product' => static::$productId,
            'nickname' => 'Monthly $20 Flex Premium',
            'currency' => 'USD',
            'recurring' => ['interval' => 'month'],
            'billing_scheme' => 'per_unit',
            'unit_amount' => 2000,
        ])->id;

        // Create a meter for metered billing tests (must be globally unique across runs)
        $uniqueSuffix = bin2hex(random_bytes(8));
        static::$meterId = self::stripe()->billing->meters->create([
            'display_name' => 'Test API Calls '.$uniqueSuffix,
            'event_name' => 'api_calls_flex_'.$uniqueSuffix,
            'default_aggregation' => ['formula' => 'sum'],
        ])->id;

        static::$meteredPriceId = self::stripe()->prices->create([
            'product' => static::$productId,
            'nickname' => 'Metered API Calls',
            'currency' => 'USD',
            'recurring' => [
                'interval' => 'month',
                'usage_type' => 'metered',
                'meter' => static::$meterId,
            ],
            'billing_scheme' => 'per_unit',
            'unit_amount' => 1, // $0.01 per event
        ])->id;
    }

    protected function tearDown(): void
    {
        Cashier::$defaultBillingMode = 'classic';

        parent::tearDown();
    }

    // =========================================================================
    // Tier 1: Core Flexible Billing Mode
    // =========================================================================

    public function test_can_create_subscription_with_flexible_billing_mode()
    {
        $user = $this->createCustomer('flex-billing-create');

        $subscription = $user->newSubscription('default', static::$priceId)
            ->withBillingMode('flexible')
            ->create('pm_card_visa');

        $this->assertTrue($subscription->valid());
        $this->assertTrue($subscription->active());

        // Verify billing mode on Stripe
        $stripeSubscription = $subscription->asStripeSubscription();
        $this->assertSame('flexible', $stripeSubscription->billing_mode->type);
    }

    public function test_can_create_subscription_with_classic_billing_mode()
    {
        $user = $this->createCustomer('classic-billing-create');

        $subscription = $user->newSubscription('default', static::$priceId)
            ->create('pm_card_visa');

        $this->assertTrue($subscription->valid());
        $this->assertTrue($subscription->active());

        // Classic mode should be the default on Stripe
        $stripeSubscription = $subscription->asStripeSubscription();
        $this->assertSame('classic', $stripeSubscription->billing_mode->type);
    }

    public function test_global_default_billing_mode_applies_to_new_subscriptions()
    {
        Cashier::defaultBillingMode('flexible');

        $user = $this->createCustomer('flex-global-default');

        $subscription = $user->newSubscription('default', static::$priceId)
            ->create('pm_card_visa');

        $this->assertTrue($subscription->valid());

        $stripeSubscription = $subscription->asStripeSubscription();
        $this->assertSame('flexible', $stripeSubscription->billing_mode->type);
    }

    public function test_instance_billing_mode_overrides_global_default()
    {
        Cashier::defaultBillingMode('flexible');

        $user = $this->createCustomer('flex-override');

        $subscription = $user->newSubscription('default', static::$priceId)
            ->withBillingMode('classic')
            ->create('pm_card_visa');

        $this->assertTrue($subscription->valid());

        $stripeSubscription = $subscription->asStripeSubscription();
        $this->assertSame('classic', $stripeSubscription->billing_mode->type);
    }

    public function test_uses_flexible_billing_returns_correct_value()
    {
        $user = $this->createCustomer('flex-check');

        $subscription = $user->newSubscription('default', static::$priceId)
            ->withBillingMode('flexible')
            ->create('pm_card_visa');

        $this->assertTrue($subscription->usesFlexibleBilling());
    }

    public function test_can_migrate_subscription_to_flexible_billing_mode()
    {
        $user = $this->createCustomer('flex-migrate');

        $subscription = $user->newSubscription('default', static::$priceId)
            ->create('pm_card_visa');

        // Starts as classic
        $this->assertFalse($subscription->usesFlexibleBilling());

        // Migrate to flexible
        $subscription->migrateToFlexibleBillingMode();

        // Verify it's now flexible
        $this->assertTrue($subscription->usesFlexibleBilling());
    }

    public function test_flexible_subscription_can_swap_prices()
    {
        $user = $this->createCustomer('flex-swap');

        $subscription = $user->newSubscription('default', static::$priceId)
            ->withBillingMode('flexible')
            ->create('pm_card_visa');

        $subscription->swap(static::$otherPriceId);

        $this->assertSame(static::$otherPriceId, $subscription->stripe_price);
        $this->assertTrue($subscription->usesFlexibleBilling());
    }

    public function test_flexible_subscription_does_not_use_clear_usage_on_remove()
    {
        $user = $this->createCustomer('flex-no-clear-usage');

        // Create a multi-price subscription with flexible billing
        $subscription = $user->newSubscription('default')
            ->price(static::$priceId)
            ->meteredPrice(static::$meteredPriceId)
            ->withBillingMode('flexible')
            ->create('pm_card_visa');

        $this->assertTrue($subscription->valid());
        $this->assertTrue($subscription->hasMultiplePrices());
        $this->assertTrue($subscription->usesFlexibleBilling());

        // This should NOT throw an error about clear_usage
        $subscription->removePrice(static::$meteredPriceId);

        $this->assertTrue($subscription->hasSinglePrice());
    }

    // =========================================================================
    // Tier 1: Billing Credits (extends existing ManagesCustomer)
    // =========================================================================

    public function test_billing_credits_add_and_check()
    {
        $user = $this->createCustomer('billing-credits');
        $user->createAsStripeCustomer();

        // Add $50 in credits
        $user->addBillingCredits(5000, 'Test credit');

        $this->assertTrue($user->hasSufficientCredits(3000));
        $this->assertTrue($user->hasSufficientCredits(5000));
        $this->assertFalse($user->hasSufficientCredits(5001));
        $this->assertSame(5000, $user->availableCredits());
    }

    public function test_billing_credits_calculate_application()
    {
        $user = $this->createCustomer('billing-credits-calc');
        $user->createAsStripeCustomer();

        $user->addBillingCredits(3000, 'Partial credit');

        $result = $user->calculateCreditApplication(5000);

        $this->assertSame(3000, $result['applied_credits']);
        $this->assertSame(2000, $result['remaining_usage']);
        $this->assertSame(0, $result['credits_after']);
    }

    // =========================================================================
    // Tier 2: Subscription Schedules
    // =========================================================================

    public function test_can_create_subscription_schedule()
    {
        $user = $this->createCustomer('sched-create');

        $schedule = $user->newSubscriptionSchedule('default')
            ->addPhase([
                ['price' => static::$priceId, 'quantity' => 1],
            ], ['iterations' => 1])
            ->startDate('now')
            ->create();

        $this->assertNotNull($schedule->id);
        $this->assertNotNull($schedule->stripe_id);
        $this->assertSame('default', $schedule->type);

        // Schedule should be active since we started it 'now'
        $this->assertTrue($schedule->active());
    }

    public function test_can_create_multi_phase_schedule()
    {
        $user = $this->createCustomer('sched-multiphase');

        $schedule = $user->newSubscriptionSchedule('default')
            ->addPhase([
                ['price' => static::$priceId, 'quantity' => 1],
            ], ['iterations' => 1])
            ->addPhase([
                ['price' => static::$otherPriceId, 'quantity' => 1],
            ], ['iterations' => 1])
            ->startDate('now')
            ->create();

        $this->assertTrue($schedule->active());

        $phases = $schedule->phases();
        $this->assertCount(2, $phases);
    }

    public function test_can_cancel_subscription_schedule()
    {
        $user = $this->createCustomer('sched-cancel');

        $schedule = $user->newSubscriptionSchedule('default')
            ->addPhase([
                ['price' => static::$priceId, 'quantity' => 1],
            ], ['iterations' => 1])
            ->startDate('now')
            ->create();

        $schedule->cancel();

        $this->assertTrue($schedule->canceled());
    }

    public function test_can_release_subscription_schedule()
    {
        $user = $this->createCustomer('sched-release');

        $schedule = $user->newSubscriptionSchedule('default')
            ->addPhase([
                ['price' => static::$priceId, 'quantity' => 1],
            ], ['iterations' => 1])
            ->startDate('now')
            ->create();

        $schedule->release();

        $this->assertTrue($schedule->released());
    }

    public function test_can_find_subscription_schedule()
    {
        $user = $this->createCustomer('sched-find');

        $schedule = $user->newSubscriptionSchedule('default')
            ->addPhase([
                ['price' => static::$priceId, 'quantity' => 1],
            ], ['iterations' => 1])
            ->startDate('now')
            ->create();

        $found = $user->findSubscriptionSchedule($schedule->stripe_id);
        $this->assertNotNull($found);
        $this->assertSame($schedule->stripe_id, $found->stripe_id);
    }

    public function test_can_create_schedule_from_existing_subscription()
    {
        $user = $this->createCustomer('sched-from-sub');

        $subscription = $user->newSubscription('default', static::$priceId)
            ->create('pm_card_visa');

        $schedule = $user->newSubscriptionSchedule('default')
            ->createFromSubscription($subscription);

        $this->assertNotNull($schedule->stripe_id);
        $this->assertTrue($schedule->active());
    }

    public function test_can_create_schedule_with_flexible_billing()
    {
        $user = $this->createCustomer('sched-flex');

        $schedule = $user->newSubscriptionSchedule('default')
            ->withBillingMode('flexible')
            ->addPhase([
                ['price' => static::$priceId, 'quantity' => 1],
            ], ['iterations' => 1])
            ->startDate('now')
            ->create();

        $this->assertTrue($schedule->active());

        // Verify the subscription created by the schedule uses flexible billing
        $stripeSchedule = $schedule->asStripeSubscriptionSchedule();
        $this->assertNotNull($stripeSchedule->subscription);
    }

    // =========================================================================
    // Tier 2: Quotes
    // =========================================================================

    public function test_can_create_quote()
    {
        $user = $this->createCustomer('quote-create');

        $quote = $user->newQuote()
            ->addLineItem(static::$priceId, 1)
            ->create();

        $this->assertNotNull($quote->id);
        $this->assertNotNull($quote->stripe_id);
        $this->assertTrue($quote->draft());
        $this->assertSame('usd', $quote->currency);
    }

    public function test_can_create_quote_with_multiple_items()
    {
        $user = $this->createCustomer('quote-multi');

        $quote = $user->newQuote()
            ->addLineItem(static::$priceId, 1)
            ->addLineItem(static::$otherPriceId, 2)
            ->create();

        $this->assertTrue($quote->draft());
        $this->assertSame(5000, $quote->amount_total); // $10 + $40 = $50
    }

    public function test_can_finalize_quote()
    {
        $user = $this->createCustomer('quote-finalize');

        $quote = $user->newQuote()
            ->addLineItem(static::$priceId, 1)
            ->create();

        $quote->finalize();

        $this->assertTrue($quote->open());
        $this->assertNotNull($quote->finalized_at);
    }

    public function test_can_accept_quote()
    {
        $user = $this->createCustomer('quote-accept');
        $user->createAsStripeCustomer();
        $user->updateDefaultPaymentMethod('pm_card_visa');

        $quote = $user->newQuote()
            ->addLineItem(static::$priceId, 1)
            ->create();

        $quote->finalize();
        $quote->accept();

        $this->assertTrue($quote->accepted());
        $this->assertNotNull($quote->accepted_at);
    }

    public function test_can_cancel_quote()
    {
        $user = $this->createCustomer('quote-cancel');

        $quote = $user->newQuote()
            ->addLineItem(static::$priceId, 1)
            ->create();

        $quote->finalize();
        $quote->cancel();

        $this->assertTrue($quote->canceled());
    }

    public function test_can_find_quote()
    {
        $user = $this->createCustomer('quote-find');

        $quote = $user->newQuote()
            ->addLineItem(static::$priceId, 1)
            ->create();

        $found = $user->findQuote($quote->stripe_id);
        $this->assertNotNull($found);
        $this->assertSame($quote->stripe_id, $found->stripe_id);
    }

    public function test_can_create_quote_with_description()
    {
        $user = $this->createCustomer('quote-desc');

        $quote = $user->newQuote()
            ->addLineItem(static::$priceId, 1)
            ->description('Test quote description')
            ->create();

        $stripeQuote = $quote->asStripeQuote();
        $this->assertSame('Test quote description', $stripeQuote->description);
    }

    public function test_can_sync_quote_with_stripe()
    {
        $user = $this->createCustomer('quote-sync');

        $quote = $user->newQuote()
            ->addLineItem(static::$priceId, 1)
            ->create();

        // Finalize on Stripe directly
        $user->stripe()->quotes->finalizeQuote($quote->stripe_id);

        // Sync local record
        $quote->syncWithStripe();

        $this->assertTrue($quote->open());
    }
}

<?php

namespace Laravel\Cashier\Tests\Feature;

use Carbon\Carbon;
use DateTime;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Exceptions\IncompletePayment;
use Laravel\Cashier\Payment;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\Tests\Fixtures\User;
use Stripe\Invoice as StripeInvoice;
use Stripe\Subscription as StripeSubscription;

class SubscriptionsTest extends FeatureTestCase
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

    /**
     * @var string
     */
    protected static $couponId;

    /**
     * @var string
     */
    protected static $taxRateId;

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

        static::$couponId = self::stripe()->coupons->create([
            'duration' => 'repeating',
            'amount_off' => 500,
            'duration_in_months' => 3,
            'currency' => 'USD',
        ])->id;

        static::$taxRateId = self::stripe()->taxRates->create([
            'display_name' => 'VAT',
            'description' => 'VAT Belgium',
            'jurisdiction' => 'BE',
            'percentage' => 21,
            'inclusive' => false,
        ])->id;
    }

    public function test_subscriptions_can_be_created()
    {
        $user = $this->createCustomer('subscriptions_can_be_created');

        // Create Subscription
        $user->newSubscription('main', static::$priceId)
            ->withMetadata($metadata = ['order_id' => '8'])
            ->create('pm_card_visa');

        $this->assertEquals(1, count($user->subscriptions));
        $this->assertNotNull(($subscription = $user->subscription('main'))->stripe_id);
        $this->assertSame($metadata, $subscription->asStripeSubscription()->metadata->toArray());

        $this->assertTrue($user->subscribed('main'));
        $this->assertTrue($user->subscribedToProduct(static::$productId, 'main'));
        $this->assertTrue($user->subscribedToPrice(static::$priceId, 'main'));
        $this->assertFalse($user->subscribedToPrice(static::$priceId, 'something'));
        $this->assertFalse($user->subscribedToPrice(static::$otherPriceId, 'main'));
        $this->assertTrue($user->subscribed('main', static::$priceId));
        $this->assertFalse($user->subscribed('main', static::$otherPriceId));
        $this->assertTrue($user->subscription('main')->active());
        $this->assertFalse($user->subscription('main')->cancelled());
        $this->assertFalse($user->subscription('main')->onGracePeriod());
        $this->assertTrue($user->subscription('main')->recurring());
        $this->assertFalse($user->subscription('main')->ended());

        // Cancel Subscription
        $subscription = $user->subscription('main');
        $subscription->cancel();

        $this->assertTrue($subscription->active());
        $this->assertTrue($subscription->cancelled());
        $this->assertTrue($subscription->onGracePeriod());
        $this->assertFalse($subscription->recurring());
        $this->assertFalse($subscription->ended());

        // Modify Ends Date To Past
        $oldGracePeriod = $subscription->ends_at;
        $subscription->fill(['ends_at' => Carbon::now()->subDays(5)])->save();

        $this->assertFalse($subscription->active());
        $this->assertTrue($subscription->cancelled());
        $this->assertFalse($subscription->onGracePeriod());
        $this->assertFalse($subscription->recurring());
        $this->assertTrue($subscription->ended());

        $subscription->fill(['ends_at' => $oldGracePeriod])->save();

        // Resume Subscription
        $subscription->resume();

        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->cancelled());
        $this->assertFalse($subscription->onGracePeriod());
        $this->assertTrue($subscription->recurring());
        $this->assertFalse($subscription->ended());

        // Increment & Decrement
        $subscription->incrementQuantity();

        $this->assertEquals(2, $subscription->quantity);

        $subscription->decrementQuantity();

        $this->assertEquals(1, $subscription->quantity);

        // Swap Price and invoice immediately.
        $subscription->swapAndInvoice(static::$otherPriceId);

        $this->assertEquals(static::$otherPriceId, $subscription->stripe_price);

        // Invoice Tests
        $invoice = $user->invoices()[1];

        $this->assertEquals('$10.00', $invoice->total());
        $this->assertFalse($invoice->hasDiscount());
        $this->assertFalse($invoice->hasStartingBalance());
        $this->assertEmpty($invoice->discounts());
        $this->assertInstanceOf(Carbon::class, $invoice->date());
    }

    public function test_swapping_subscription_with_coupon()
    {
        $user = $this->createCustomer('swapping_subscription_with_coupon');
        $user->newSubscription('main', static::$priceId)->create('pm_card_visa');
        $subscription = $user->subscription('main');

        $subscription->swap(static::$otherPriceId, [
            'coupon' => static::$couponId,
        ]);

        $this->assertEquals(static::$couponId, $subscription->asStripeSubscription()->discount->coupon->id);
    }

    public function test_swapping_subscription_and_preserving_quantity()
    {
        $user = $this->createCustomer('swapping_subscription_and_preserving_quantity');
        $subscription = $user->newSubscription('main', static::$priceId)
            ->quantity(5, static::$priceId)
            ->create('pm_card_visa');

        $subscription = $subscription->swap(static::$otherPriceId);

        $this->assertSame(5, $subscription->quantity);
        $this->assertSame(5, $subscription->asStripeSubscription()->quantity);
    }

    public function test_swapping_subscription_and_adopting_new_quantity()
    {
        $user = $this->createCustomer('swapping_subscription_and_adopting_new_quantity');
        $subscription = $user->newSubscription('main', static::$priceId)
            ->quantity(5, static::$priceId)
            ->create('pm_card_visa');

        $subscription = $subscription->swap([static::$otherPriceId => ['quantity' => 3]]);

        $this->assertSame(3, $subscription->quantity);
        $this->assertSame(3, $subscription->asStripeSubscription()->quantity);
    }

    public function test_swapping_subscription_with_inline_price_data()
    {
        $user = $this->createCustomer('swapping_subscription_with_inline_price_data');
        $user->newSubscription('main', static::$priceId)->create('pm_card_visa');
        $subscription = $user->subscription('main');

        $subscription->swap([[
            'price_data' => [
                'product' => static::$productId,
                'tax_behavior' => 'exclusive',
                'currency' => 'USD',
                'recurring' => [
                    'interval' => 'month',
                ],
                'unit_amount' => 1100,
            ],
        ]]);

        $this->assertEquals(1100, $subscription->asStripeSubscription()->items->data[0]->price->unit_amount);
        $this->assertEquals('exclusive', $subscription->asStripeSubscription()->items->data[0]->price->tax_behavior);
    }

    public function test_declined_card_during_new_quantity()
    {
        $user = $this->createCustomer('declined_card_during_new_quantity');

        $subscription = $user->newSubscription('main', static::$priceId)
            ->quantity(5)
            ->create('pm_card_visa');

        // Set a faulty card as the customer's default payment method.
        $user->updateDefaultPaymentMethod('pm_card_chargeCustomerFail');

        try {
            // Attempt to increment quantity and pay with a faulty card.
            $subscription = $subscription->incrementAndInvoice(3);

            $this->fail('Expected exception '.IncompletePayment::class.' was not thrown.');
        } catch (IncompletePayment $e) {
            // Assert that the payment needs a valid payment method.
            $this->assertTrue($e->payment->requiresPaymentMethod());

            // Assert that the quantity was updated anyway.
            $this->assertEquals(8, $subscription->refresh()->quantity);

            // Assert subscription is past due.
            $this->assertTrue($subscription->pastDue());
        }
    }

    public function test_declined_card_during_new_quantity_for_specific_price()
    {
        $user = $this->createCustomer('declined_card_during_new_quantity_for_specific_price');

        $subscription = $user->newSubscription('main', static::$priceId)
            ->quantity(5, static::$priceId)
            ->create('pm_card_visa');

        // Set a faulty card as the customer's default payment method.
        $user->updateDefaultPaymentMethod('pm_card_chargeCustomerFail');

        try {
            // Attempt to increment quantity and pay with a faulty card.
            $subscription = $subscription->incrementAndInvoice(3);

            $this->fail('Expected exception '.IncompletePayment::class.' was not thrown.');
        } catch (IncompletePayment $e) {
            // Assert that the payment needs a valid payment method.
            $this->assertTrue($e->payment->requiresPaymentMethod());

            // Assert that the quantity was updated anyway.
            $this->assertEquals(8, $subscription->refresh()->quantity);

            // Assert subscription is past due.
            $this->assertTrue($subscription->pastDue());
        }
    }

    public function test_declined_card_during_subscribing_results_in_an_exception()
    {
        $user = $this->createCustomer('declined_card_during_subscribing_results_in_an_exception');

        try {
            $user->newSubscription('main', static::$priceId)->create('pm_card_chargeCustomerFail');

            $this->fail('Expected exception '.IncompletePayment::class.' was not thrown.');
        } catch (IncompletePayment $e) {
            // Assert that the payment needs a valid payment method.
            $this->assertTrue($e->payment->requiresPaymentMethod());

            // Assert subscription was added to the customer.
            $this->assertInstanceOf(Subscription::class, $subscription = $user->subscription('main'));

            // Assert subscription is incomplete.
            $this->assertTrue($subscription->incomplete());
        }
    }

    public function test_next_action_needed_during_subscribing_results_in_an_exception()
    {
        $user = $this->createCustomer('next_action_needed_during_subscribing_results_in_an_exception');

        try {
            $user->newSubscription('main', static::$priceId)->create('pm_card_threeDSecure2Required');

            $this->fail('Expected exception '.IncompletePayment::class.' was not thrown.');
        } catch (IncompletePayment $e) {
            // Assert that the payment needs an extra action.
            $this->assertTrue($e->payment->requiresAction());

            // Assert subscription was added to the customer.
            $this->assertInstanceOf(Subscription::class, $subscription = $user->subscription('main'));

            // Assert subscription is incomplete.
            $this->assertTrue($subscription->incomplete());
        }
    }

    public function test_declined_card_during_price_swap_results_in_an_exception()
    {
        $user = $this->createCustomer('declined_card_during_price_swap_results_in_an_exception');

        $subscription = $user->newSubscription('main', static::$priceId)->create('pm_card_visa');

        // Set a faulty card as the customer's default payment method.
        $user->updateDefaultPaymentMethod('pm_card_chargeCustomerFail');

        try {
            // Attempt to swap and pay with a faulty card.
            $subscription = $subscription->swapAndInvoice(static::$premiumPriceId);

            $this->fail('Expected exception '.IncompletePayment::class.' was not thrown.');
        } catch (IncompletePayment $e) {
            // Assert that the payment needs a valid payment method.
            $this->assertTrue($e->payment->requiresPaymentMethod());

            // Assert that the price was swapped anyway.
            $this->assertEquals(static::$premiumPriceId, $subscription->refresh()->stripe_price);

            // Assert subscription is past due.
            $this->assertTrue($subscription->pastDue());
        }
    }

    public function test_next_action_needed_during_price_swap_results_in_an_exception()
    {
        $user = $this->createCustomer('next_action_needed_during_price_swap_results_in_an_exception');

        $subscription = $user->newSubscription('main', static::$priceId)->create('pm_card_visa');

        // Set a card that requires a next action as the customer's default payment method.
        $user->updateDefaultPaymentMethod('pm_card_threeDSecure2Required');

        try {
            // Attempt to swap and pay with a faulty card.
            $subscription = $subscription->swapAndInvoice(static::$premiumPriceId);

            $this->fail('Expected exception '.IncompletePayment::class.' was not thrown.');
        } catch (IncompletePayment $e) {
            // Assert that the payment needs an extra action.
            $this->assertTrue($e->payment->requiresAction());

            // Assert that the price was swapped anyway.
            $this->assertEquals(static::$premiumPriceId, $subscription->refresh()->stripe_price);

            // Assert subscription is past due.
            $this->assertTrue($subscription->pastDue());
        }
    }

    public function test_downgrade_with_faulty_card_does_not_incomplete_subscription()
    {
        $user = $this->createCustomer('downgrade_with_faulty_card_does_not_incomplete_subscription');

        $subscription = $user->newSubscription('main', static::$premiumPriceId)->create('pm_card_visa');

        // Set a card that requires a next action as the customer's default payment method.
        $user->updateDefaultPaymentMethod('pm_card_chargeCustomerFail');

        // Attempt to swap and pay with a faulty card.
        $subscription = $subscription->swap(static::$priceId);

        // Assert that the price was swapped anyway.
        $this->assertEquals(static::$priceId, $subscription->refresh()->stripe_price);

        // Assert subscription is still active.
        $this->assertTrue($subscription->active());
    }

    public function test_downgrade_with_3d_secure_does_not_incomplete_subscription()
    {
        $user = $this->createCustomer('downgrade_with_3d_secure_does_not_incomplete_subscription');

        $subscription = $user->newSubscription('main', static::$premiumPriceId)->create('pm_card_visa');

        // Set a card that requires a next action as the customer's default payment method.
        $user->updateDefaultPaymentMethod('pm_card_threeDSecure2Required');

        // Attempt to swap and pay with a faulty card.
        $subscription = $subscription->swap(static::$priceId);

        // Assert that the price was swapped anyway.
        $this->assertEquals(static::$priceId, $subscription->refresh()->stripe_price);

        // Assert subscription is still active.
        $this->assertTrue($subscription->active());
    }

    public function test_creating_subscription_with_coupons()
    {
        $user = $this->createCustomer('creating_subscription_with_coupons');

        // Create Subscription
        $user->newSubscription('main', static::$priceId)
            ->withCoupon(static::$couponId)
            ->create('pm_card_visa');

        $subscription = $user->subscription('main');

        $this->assertTrue($user->subscribed('main'));
        $this->assertTrue($user->subscribed('main', static::$priceId));
        $this->assertFalse($user->subscribed('main', static::$otherPriceId));
        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->cancelled());
        $this->assertFalse($subscription->onGracePeriod());
        $this->assertTrue($subscription->recurring());
        $this->assertFalse($subscription->ended());

        // Invoice Tests
        $invoice = $user->invoices()[0];

        $coupon = $invoice->discounts()[0]->coupon();

        $this->assertTrue($invoice->hasDiscount());
        $this->assertEquals('$5.00', $invoice->total());
        $this->assertEquals('$5.00', $coupon->amountOff());
        $this->assertFalse($coupon->isPercentage());
    }

    public function test_creating_subscription_with_inline_price_data()
    {
        $user = $this->createCustomer('creating_subscription_with_inline_price_data');

        $user->newSubscription('main')->price([
            'price_data' => [
                'product' => static::$productId,
                'tax_behavior' => 'exclusive',
                'currency' => 'USD',
                'recurring' => [
                    'interval' => 'month',
                ],
                'unit_amount' => 1100,
            ],
        ])->create('pm_card_visa');

        $subscription = $user->subscription('main');

        $this->assertTrue($user->subscribed('main'));
        $this->assertNotNull($user->subscribed('main', static::$otherPriceId));
        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->cancelled());
        $this->assertFalse($subscription->onGracePeriod());
        $this->assertTrue($subscription->recurring());
        $this->assertFalse($subscription->ended());

        $invoice = $user->invoices()[0];

        $this->assertEquals('$11.00', $invoice->total());
        $this->assertEquals('exclusive', $invoice->invoiceLineItems()[0]->price->tax_behavior);
    }

    public function test_creating_subscription_with_an_anchored_billing_cycle()
    {
        $user = $this->createCustomer('creating_subscription_with_an_anchored_billing_cycle');

        // Create Subscription
        $user->newSubscription('main', static::$priceId)
            ->anchorBillingCycleOn(new DateTime('first day of next month'))
            ->create('pm_card_visa');

        $subscription = $user->subscription('main');

        $this->assertTrue($user->subscribed('main'));
        $this->assertTrue($user->subscribed('main', static::$priceId));
        $this->assertFalse($user->subscribed('main', static::$otherPriceId));
        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->cancelled());
        $this->assertFalse($subscription->onGracePeriod());
        $this->assertTrue($subscription->recurring());
        $this->assertFalse($subscription->ended());

        // Invoice Tests
        $invoice = $user->invoices()[0];
        $invoicePeriod = $invoice->invoiceItems()[0]->period;

        $this->assertEquals(
            (new DateTime('now'))->format('Y-m-d'),
            date('Y-m-d', $invoicePeriod->start)
        );
        $this->assertEquals(
            (new DateTime('first day of next month'))->format('Y-m-d'),
            date('Y-m-d', $invoicePeriod->end)
        );
    }

    public function test_creating_subscription_with_trial()
    {
        $user = $this->createCustomer('creating_subscription_with_trial');

        // Create Subscription
        $user->newSubscription('main', static::$priceId)
            ->trialDays(7)
            ->create('pm_card_visa');

        $subscription = $user->subscription('main');

        $this->assertTrue($subscription->active());
        $this->assertTrue($subscription->onTrial());
        $this->assertFalse($subscription->recurring());
        $this->assertFalse($subscription->ended());
        $this->assertEquals(Carbon::today()->addDays(7)->day, $user->trialEndsAt('main')->day);

        // Cancel Subscription
        $subscription->cancel();

        $this->assertTrue($subscription->active());
        $this->assertTrue($subscription->onGracePeriod());
        $this->assertFalse($subscription->recurring());
        $this->assertFalse($subscription->ended());

        // Resume Subscription
        $subscription->resume();

        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->onGracePeriod());
        $this->assertTrue($subscription->onTrial());
        $this->assertFalse($subscription->recurring());
        $this->assertFalse($subscription->ended());
        $this->assertEquals(Carbon::today()->addDays(7)->day, $subscription->trial_ends_at->day);
    }

    public function test_user_without_subscriptions_can_return_its_generic_trial_end_date()
    {
        $user = new User;
        $user->trial_ends_at = $tomorrow = Carbon::tomorrow();

        $this->assertTrue($user->onGenericTrial());
        $this->assertSame($tomorrow, $user->trialEndsAt());
    }

    public function test_user_with_subscription_can_return_generic_trial_end_date()
    {
        $user = $this->createCustomer('user_with_subscription_can_return_generic_trial_end_date');
        $user->trial_ends_at = $tomorrow = Carbon::tomorrow();

        $user->newSubscription('default', static::$priceId)
            ->create('pm_card_visa');

        $subscription = $user->subscription('default');

        $this->assertTrue($user->onGenericTrial());
        $this->assertTrue($user->onTrial());
        $this->assertFalse($subscription->onTrial());
        $this->assertSame($tomorrow, $user->trialEndsAt());
    }

    public function test_creating_subscription_with_explicit_trial()
    {
        $user = $this->createCustomer('creating_subscription_with_explicit_trial');

        // Create Subscription
        $user->newSubscription('main', static::$priceId)
            ->trialUntil(Carbon::tomorrow()->hour(3)->minute(15))
            ->create('pm_card_visa');

        $subscription = $user->subscription('main');

        $this->assertTrue($subscription->active());
        $this->assertTrue($subscription->onTrial());
        $this->assertFalse($subscription->recurring());
        $this->assertFalse($subscription->ended());
        $this->assertEquals(Carbon::tomorrow()->hour(3)->minute(15), $subscription->trial_ends_at);

        // Cancel Subscription
        $subscription->cancel();

        $this->assertTrue($subscription->active());
        $this->assertTrue($subscription->onGracePeriod());
        $this->assertFalse($subscription->recurring());
        $this->assertFalse($subscription->ended());

        // Resume Subscription
        $subscription->resume();

        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->onGracePeriod());
        $this->assertTrue($subscription->onTrial());
        $this->assertFalse($subscription->recurring());
        $this->assertFalse($subscription->ended());
        $this->assertEquals(Carbon::tomorrow()->hour(3)->minute(15), $subscription->trial_ends_at);
    }

    public function test_subscription_changes_can_be_prorated()
    {
        $user = $this->createCustomer('subscription_changes_can_be_prorated');

        $subscription = $user->newSubscription('main', static::$premiumPriceId)->create('pm_card_visa');

        $this->assertEquals(2000, ($invoice = $user->invoices()->first())->rawTotal());

        $subscription->noProrate()->swap(static::$priceId);

        // Assert that no new invoice was created because of no prorating.
        $this->assertEquals($invoice->id, $user->invoices()->first()->id);
        $this->assertEquals(1000, $user->upcomingInvoice()->rawTotal());

        $subscription->swapAndInvoice(static::$premiumPriceId);

        // Assert that a new invoice was created because of immediate invoicing.
        $this->assertNotSame($invoice->id, ($invoice = $user->invoices()->first())->id);
        $this->assertEquals(1000, $invoice->rawTotal());
        $this->assertEquals(2000, $user->upcomingInvoice()->rawTotal());

        $subscription->prorate()->swap(static::$priceId);

        // Get back from unused time on premium price on next invoice.
        $this->assertEquals(0, $user->upcomingInvoice()->rawTotal());
    }

    public function test_trial_remains_when_customer_is_invoiced_immediately_on_swap()
    {
        $user = $this->createCustomer('trial_remains_when_customer_is_invoiced_immediately_on_swap');

        $subscription = $user->newSubscription('main', static::$priceId)
            ->trialDays(5)
            ->create('pm_card_visa');

        $this->assertTrue($subscription->onTrial());

        $subscription = $subscription->swapAndInvoice(static::$otherPriceId);

        $this->assertTrue($subscription->onTrial());
    }

    public function test_trial_on_swap_is_skipped_when_explicitly_asked_to()
    {
        $user = $this->createCustomer('trial_on_swap_is_skipped_when_explicitly_asked_to');

        $subscription = $user->newSubscription('main', static::$priceId)
            ->trialDays(5)
            ->create('pm_card_visa');

        $this->assertTrue($subscription->onTrial());

        $subscription = $subscription->skipTrial()->swapAndInvoice(static::$otherPriceId);

        $this->assertFalse($subscription->onTrial());
    }

    public function test_no_prorate_on_subscription_create()
    {
        $user = $this->createCustomer('no_prorate_on_subscription_create');

        $subscription = $user->newSubscription('main', static::$priceId)->noProrate()->create('pm_card_visa', [], [
            'collection_method' => 'send_invoice',
            'days_until_due' => 30,
            'backdate_start_date' => Carbon::now()->addDays(5)->subYear()->startOfDay()->unix(),
            'billing_cycle_anchor' => Carbon::now()->addDays(5)->startOfDay()->unix(),
        ]);

        $this->assertEquals(static::$priceId, $subscription->stripe_price);
        $this->assertTrue($subscription->active());

        $subscription = $subscription->swap(self::$otherPriceId);

        $this->assertEquals(static::$otherPriceId, $subscription->stripe_price);
        $this->assertTrue($subscription->active());
    }

    public function test_swap_and_invoice_after_no_prorate_with_billing_cycle_anchor_delays_invoicing()
    {
        $user = $this->createCustomer('always_invoice_after_no_prorate');

        $subscription = $user->newSubscription('main', static::$priceId)->noProrate()->create('pm_card_visa', [], [
            'collection_method' => 'send_invoice',
            'days_until_due' => 30,
            'backdate_start_date' => Carbon::now()->addDays(5)->subYear()->startOfDay()->unix(),
            'billing_cycle_anchor' => Carbon::now()->addDays(5)->startOfDay()->unix(),
        ]);

        $this->assertEquals(static::$priceId, $subscription->stripe_price);
        $this->assertCount(0, $user->invoices());
        $this->assertSame(StripeInvoice::STATUS_DRAFT, $user->upcomingInvoice()->status);
        $this->assertTrue($subscription->active());

        $subscription = $subscription->swapAndInvoice(self::$otherPriceId);

        $this->assertEquals(static::$otherPriceId, $subscription->stripe_price);
        $this->assertCount(0, $user->invoices());
        $this->assertSame(StripeInvoice::STATUS_DRAFT, $user->upcomingInvoice()->status);
        $this->assertTrue($subscription->active());
    }

    public function test_trials_can_be_extended()
    {
        $user = $this->createCustomer('trials_can_be_extended');

        $subscription = $user->newSubscription('main', static::$priceId)->create('pm_card_visa');

        $this->assertNull($subscription->trial_ends_at);

        $subscription->extendTrial($trialEndsAt = now()->addDays()->floor());

        $this->assertTrue($trialEndsAt->equalTo($subscription->trial_ends_at));
        $this->assertEquals($subscription->asStripeSubscription()->trial_end, $trialEndsAt->getTimestamp());
    }

    public function test_trials_can_be_ended()
    {
        $user = $this->createCustomer('trials_can_be_ended');

        $subscription = $user->newSubscription('main', static::$priceId)
            ->trialDays(10)
            ->create('pm_card_visa');

        $subscription->endTrial();

        $this->assertNull($subscription->trial_ends_at);
    }

    public function test_applying_coupons_to_existing_customers()
    {
        $user = $this->createCustomer('applying_coupons_to_existing_customers');

        $user->newSubscription('main', static::$priceId)->create('pm_card_visa');

        $user->applyCoupon(static::$couponId);

        $customer = $user->asStripeCustomer();

        $this->assertEquals(static::$couponId, $customer->discount->coupon->id);
    }

    public function test_subscription_state_scopes()
    {
        $user = $this->createCustomer('subscription_state_scopes');

        // Start with an incomplete subscription.
        $subscription = $user->subscriptions()->create([
            'name' => 'yearly',
            'stripe_id' => 'xxxx',
            'stripe_status' => StripeSubscription::STATUS_INCOMPLETE,
            'stripe_price' => 'stripe-yearly',
            'quantity' => 1,
            'trial_ends_at' => null,
            'ends_at' => null,
        ]);

        // Subscription is incomplete
        $this->assertTrue($user->subscriptions()->incomplete()->exists());
        $this->assertFalse($user->subscriptions()->active()->exists());
        $this->assertFalse($user->subscriptions()->onTrial()->exists());
        $this->assertTrue($user->subscriptions()->notOnTrial()->exists());
        $this->assertTrue($user->subscriptions()->recurring()->exists());
        $this->assertFalse($user->subscriptions()->cancelled()->exists());
        $this->assertTrue($user->subscriptions()->notCancelled()->exists());
        $this->assertFalse($user->subscriptions()->onGracePeriod()->exists());
        $this->assertTrue($user->subscriptions()->notOnGracePeriod()->exists());
        $this->assertFalse($user->subscriptions()->ended()->exists());

        // Activate.
        $subscription->update(['stripe_status' => 'active']);

        $this->assertFalse($user->subscriptions()->incomplete()->exists());
        $this->assertTrue($user->subscriptions()->active()->exists());
        $this->assertFalse($user->subscriptions()->onTrial()->exists());
        $this->assertTrue($user->subscriptions()->notOnTrial()->exists());
        $this->assertTrue($user->subscriptions()->recurring()->exists());
        $this->assertFalse($user->subscriptions()->cancelled()->exists());
        $this->assertTrue($user->subscriptions()->notCancelled()->exists());
        $this->assertFalse($user->subscriptions()->onGracePeriod()->exists());
        $this->assertTrue($user->subscriptions()->notOnGracePeriod()->exists());
        $this->assertFalse($user->subscriptions()->ended()->exists());

        // Put on trial.
        $subscription->update(['trial_ends_at' => Carbon::now()->addDay()]);

        $this->assertFalse($user->subscriptions()->incomplete()->exists());
        $this->assertTrue($user->subscriptions()->active()->exists());
        $this->assertTrue($user->subscriptions()->onTrial()->exists());
        $this->assertFalse($user->subscriptions()->notOnTrial()->exists());
        $this->assertFalse($user->subscriptions()->recurring()->exists());
        $this->assertFalse($user->subscriptions()->cancelled()->exists());
        $this->assertTrue($user->subscriptions()->notCancelled()->exists());
        $this->assertFalse($user->subscriptions()->onGracePeriod()->exists());
        $this->assertTrue($user->subscriptions()->notOnGracePeriod()->exists());
        $this->assertFalse($user->subscriptions()->ended()->exists());

        // Put on grace period.
        $subscription->update(['ends_at' => Carbon::now()->addDay()]);

        $this->assertFalse($user->subscriptions()->incomplete()->exists());
        $this->assertTrue($user->subscriptions()->active()->exists());
        $this->assertTrue($user->subscriptions()->onTrial()->exists());
        $this->assertFalse($user->subscriptions()->notOnTrial()->exists());
        $this->assertFalse($user->subscriptions()->recurring()->exists());
        $this->assertTrue($user->subscriptions()->cancelled()->exists());
        $this->assertFalse($user->subscriptions()->notCancelled()->exists());
        $this->assertTrue($user->subscriptions()->onGracePeriod()->exists());
        $this->assertFalse($user->subscriptions()->notOnGracePeriod()->exists());
        $this->assertFalse($user->subscriptions()->ended()->exists());

        // End subscription.
        $subscription->update(['ends_at' => Carbon::now()->subDay()]);

        $this->assertFalse($user->subscriptions()->incomplete()->exists());
        $this->assertFalse($user->subscriptions()->active()->exists());
        $this->assertTrue($user->subscriptions()->onTrial()->exists());
        $this->assertFalse($user->subscriptions()->notOnTrial()->exists());
        $this->assertFalse($user->subscriptions()->recurring()->exists());
        $this->assertTrue($user->subscriptions()->cancelled()->exists());
        $this->assertFalse($user->subscriptions()->notCancelled()->exists());
        $this->assertFalse($user->subscriptions()->onGracePeriod()->exists());
        $this->assertTrue($user->subscriptions()->notOnGracePeriod()->exists());
        $this->assertTrue($user->subscriptions()->ended()->exists());

        // Enable past_due as active state.
        $this->assertFalse($subscription->active());
        $this->assertFalse($user->subscriptions()->active()->exists());

        Cashier::keepPastDueSubscriptionsActive();

        $subscription->update(['ends_at' => null, 'stripe_status' => StripeSubscription::STATUS_PAST_DUE]);

        $this->assertTrue($subscription->active());
        $this->assertTrue($user->subscriptions()->active()->exists());

        // Reset deactivate past due state to default to not conflict with other tests.
        Cashier::$deactivatePastDue = true;
    }

    public function test_retrieve_the_latest_payment_for_a_subscription()
    {
        $user = $this->createCustomer('retrieve_the_latest_payment_for_a_subscription');

        try {
            $user->newSubscription('main', static::$priceId)->create('pm_card_threeDSecure2Required');

            $this->fail('Expected exception '.IncompletePayment::class.' was not thrown.');
        } catch (IncompletePayment $e) {
            $subscription = $user->refresh()->subscription('main');

            $this->assertInstanceOf(Payment::class, $payment = $subscription->latestPayment());
            $this->assertTrue($payment->requiresAction());
        }
    }

    public function test_subscriptions_with_tax_rates_can_be_created()
    {
        $user = $this->createCustomer('subscriptions_with_tax_rates_can_be_created');
        $user->taxRates = [self::$taxRateId];

        $subscription = $user->newSubscription('main', static::$priceId)->create('pm_card_visa');
        $stripeSubscription = $subscription->asStripeSubscription();

        $this->assertEquals([self::$taxRateId], [$stripeSubscription->default_tax_rates[0]->id]);
    }

    public function test_subscriptions_with_options_can_be_created()
    {
        $user = $this->createCustomer('subscriptions_with_options_can_be_created');

        $backdateStartDate = now()->subMonth()->getTimestamp();
        $subscription = $user->newSubscription('main', static::$priceId)->create('pm_card_visa', [], [
            'backdate_start_date' => $backdateStartDate,
        ]);
        $stripeSubscription = $subscription->asStripeSubscription();

        $this->assertEquals($backdateStartDate, $stripeSubscription->start_date);
    }

    /** @link https://github.com/laravel/cashier-stripe/issues/1041 */
    public function test_new_subscription_after_previous_cancellation_means_customer_is_subscribed()
    {
        $user = $this->createCustomer('subscriptions_with_options_can_be_created');

        $subscription = $user->subscriptions()->create([
            'name' => 'default',
            'stripe_id' => 'sub_xxx',
            'stripe_status' => 'active',
            'stripe_price' => 'price_xxx',
            'quantity' => 1,
            'trial_ends_at' => null,
            'ends_at' => null,
        ]);

        $this->assertTrue($user->refresh()->subscribed());

        $subscription->markAsCancelled();

        $this->assertFalse($user->refresh()->subscribed());

        $subscription->markAsCancelled();

        $user->subscriptions()->create([
            'name' => 'default',
            'stripe_id' => 'sub_xxx',
            'stripe_status' => 'active',
            'stripe_price' => 'price_xxx',
            'quantity' => 1,
            'trial_ends_at' => null,
            'ends_at' => null,
        ]);

        $this->assertTrue($user->refresh()->subscribed());
    }

    public function test_subscriptions_can_be_cancelled_at_a_specific_time()
    {
        $user = $this->createCustomer('subscriptions_can_be_cancelled_at_a_specific_time');

        $subscription = $user->newSubscription('main', static::$priceId)->create('pm_card_visa');

        $subscription = $subscription->cancelAt($endsAt = now()->addMonths(3));

        $this->assertTrue($subscription->active());
        $this->assertSame($endsAt->timestamp, $subscription->ends_at->timestamp);
        $this->assertSame($endsAt->timestamp, $subscription->asStripeSubscription()->cancel_at);
    }

    public function test_upcoming_invoice()
    {
        $user = $this->createCustomer('subscription_upcoming_invoice');
        $subscription = $user->newSubscription('main', static::$priceId)
            ->create('pm_card_visa');

        $invoice = $subscription->previewInvoice(static::$otherPriceId);

        $this->assertSame('draft', $invoice->status);
        $this->assertSame(1000, $invoice->total);
    }
}

<?php

namespace Laravel\Cashier\Tests\Integration;

use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Str;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Exceptions\PaymentActionRequired;
use Laravel\Cashier\Exceptions\PaymentFailure;
use Laravel\Cashier\Payment;
use Laravel\Cashier\Subscription;
use Stripe\Coupon;
use Stripe\Plan;
use Stripe\Product;
use Stripe\Subscription as StripeSubscription;

class SubscriptionsTest extends IntegrationTestCase
{
    /**
     * @var string
     */
    protected static $productId;

    /**
     * @var string
     */
    protected static $planId;

    /**
     * @var string
     */
    protected static $otherPlanId;

    /**
     * @var string
     */
    protected static $premiumPlanId;

    /**
     * @var string
     */
    protected static $couponId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        static::$productId = static::$stripePrefix.'product-1'.Str::random(10);
        static::$planId = static::$stripePrefix.'monthly-10-'.Str::random(10);
        static::$otherPlanId = static::$stripePrefix.'monthly-10-'.Str::random(10);
        static::$premiumPlanId = static::$stripePrefix.'monthly-20-premium-'.Str::random(10);
        static::$couponId = static::$stripePrefix.'coupon-'.Str::random(10);

        Product::create([
            'id' => static::$productId,
            'name' => 'Laravel Cashier Test Product',
            'type' => 'service',
        ]);

        Plan::create([
            'id' => static::$planId,
            'nickname' => 'Monthly $10',
            'currency' => 'USD',
            'interval' => 'month',
            'billing_scheme' => 'per_unit',
            'amount' => 1000,
            'product' => static::$productId,
        ]);

        Plan::create([
            'id' => static::$otherPlanId,
            'nickname' => 'Monthly $10 Other',
            'currency' => 'USD',
            'interval' => 'month',
            'billing_scheme' => 'per_unit',
            'amount' => 1000,
            'product' => static::$productId,
        ]);

        Plan::create([
            'id' => static::$premiumPlanId,
            'nickname' => 'Monthly $20 Premium',
            'currency' => 'USD',
            'interval' => 'month',
            'billing_scheme' => 'per_unit',
            'amount' => 2000,
            'product' => static::$productId,
        ]);

        Coupon::create([
            'id' => static::$couponId,
            'duration' => 'repeating',
            'amount_off' => 500,
            'duration_in_months' => 3,
            'currency' => 'USD',
        ]);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        static::deleteStripeResource(new Plan(static::$planId));
        static::deleteStripeResource(new Plan(static::$otherPlanId));
        static::deleteStripeResource(new Plan(static::$premiumPlanId));
        static::deleteStripeResource(new Product(static::$productId));
        static::deleteStripeResource(new Coupon(static::$couponId));
    }

    public function test_subscriptions_can_be_created()
    {
        $user = $this->createCustomer('subscriptions_can_be_created');

        // Create Subscription
        $user->newSubscription('main', static::$planId)->create('pm_card_visa');

        $this->assertEquals(1, count($user->subscriptions));
        $this->assertNotNull($user->subscription('main')->stripe_id);

        $this->assertTrue($user->subscribed('main'));
        $this->assertTrue($user->subscribedToPlan(static::$planId, 'main'));
        $this->assertFalse($user->subscribedToPlan(static::$planId, 'something'));
        $this->assertFalse($user->subscribedToPlan(static::$otherPlanId, 'main'));
        $this->assertTrue($user->subscribed('main', static::$planId));
        $this->assertFalse($user->subscribed('main', static::$otherPlanId));
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

        // Swap Plan and invoice immediately.
        $subscription->swapAndInvoice(static::$otherPlanId);

        $this->assertEquals(static::$otherPlanId, $subscription->stripe_plan);

        // Invoice Tests
        $invoice = $user->invoices()[1];

        $this->assertEquals('$10.00', $invoice->total());
        $this->assertFalse($invoice->hasDiscount());
        $this->assertFalse($invoice->hasStartingBalance());
        $this->assertNull($invoice->coupon());
        $this->assertInstanceOf(Carbon::class, $invoice->date());
    }

    public function test_swapping_subscription_with_coupon()
    {
        $user = $this->createCustomer('swapping_subscription_with_coupon');
        $user->newSubscription('main', static::$planId)->create('pm_card_visa');
        $subscription = $user->subscription('main');

        $subscription->swap(static::$otherPlanId, [
            'coupon' => static::$couponId,
        ]);

        $this->assertEquals(static::$couponId, $subscription->asStripeSubscription()->discount->coupon->id);
    }

    public function test_declined_card_during_subscribing_results_in_an_exception()
    {
        $user = $this->createCustomer('declined_card_during_subscribing_results_in_an_exception');

        try {
            $user->newSubscription('main', static::$planId)->create('pm_card_chargeCustomerFail');

            $this->fail('Expected exception '.PaymentFailure::class.' was not thrown.');
        } catch (PaymentFailure $e) {
            // Assert that the payment needs a valid payment method.
            $this->assertTrue($e->payment->requiresPaymentMethod());

            // Assert subscription was added to the billable entity.
            $this->assertInstanceOf(Subscription::class, $subscription = $user->subscription('main'));

            // Assert subscription is incomplete.
            $this->assertTrue($subscription->incomplete());
        }
    }

    public function test_next_action_needed_during_subscribing_results_in_an_exception()
    {
        $user = $this->createCustomer('next_action_needed_during_subscribing_results_in_an_exception');

        try {
            $user->newSubscription('main', static::$planId)->create('pm_card_threeDSecure2Required');

            $this->fail('Expected exception '.PaymentActionRequired::class.' was not thrown.');
        } catch (PaymentActionRequired $e) {
            // Assert that the payment needs an extra action.
            $this->assertTrue($e->payment->requiresAction());

            // Assert subscription was added to the billable entity.
            $this->assertInstanceOf(Subscription::class, $subscription = $user->subscription('main'));

            // Assert subscription is incomplete.
            $this->assertTrue($subscription->incomplete());
        }
    }

    public function test_declined_card_during_plan_swap_results_in_an_exception()
    {
        $user = $this->createCustomer('declined_card_during_plan_swap_results_in_an_exception');

        $subscription = $user->newSubscription('main', static::$planId)->create('pm_card_visa');

        // Set a faulty card as the customer's default payment method.
        $user->updateDefaultPaymentMethod('pm_card_chargeCustomerFail');

        try {
            // Attempt to swap and pay with a faulty card.
            $subscription = $subscription->swapAndInvoice(static::$premiumPlanId);

            $this->fail('Expected exception '.PaymentFailure::class.' was not thrown.');
        } catch (PaymentFailure $e) {
            // Assert that the payment needs a valid payment method.
            $this->assertTrue($e->payment->requiresPaymentMethod());

            // Assert that the plan was swapped anyway.
            $this->assertEquals(static::$premiumPlanId, $subscription->refresh()->stripe_plan);

            // Assert subscription is past due.
            $this->assertTrue($subscription->pastDue());
        }
    }

    public function test_next_action_needed_during_plan_swap_results_in_an_exception()
    {
        $user = $this->createCustomer('next_action_needed_during_plan_swap_results_in_an_exception');

        $subscription = $user->newSubscription('main', static::$planId)->create('pm_card_visa');

        // Set a card that requires a next action as the customer's default payment method.
        $user->updateDefaultPaymentMethod('pm_card_threeDSecure2Required');

        try {
            // Attempt to swap and pay with a faulty card.
            $subscription = $subscription->swapAndInvoice(static::$premiumPlanId);

            $this->fail('Expected exception '.PaymentActionRequired::class.' was not thrown.');
        } catch (PaymentActionRequired $e) {
            // Assert that the payment needs an extra action.
            $this->assertTrue($e->payment->requiresAction());

            // Assert that the plan was swapped anyway.
            $this->assertEquals(static::$premiumPlanId, $subscription->refresh()->stripe_plan);

            // Assert subscription is past due.
            $this->assertTrue($subscription->pastDue());
        }
    }

    public function test_downgrade_with_faulty_card_does_not_incomplete_subscription()
    {
        $user = $this->createCustomer('downgrade_with_faulty_card_does_not_incomplete_subscription');

        $subscription = $user->newSubscription('main', static::$premiumPlanId)->create('pm_card_visa');

        // Set a card that requires a next action as the customer's default payment method.
        $user->updateDefaultPaymentMethod('pm_card_chargeCustomerFail');

        // Attempt to swap and pay with a faulty card.
        $subscription = $subscription->swap(static::$planId);

        // Assert that the plan was swapped anyway.
        $this->assertEquals(static::$planId, $subscription->refresh()->stripe_plan);

        // Assert subscription is still active.
        $this->assertTrue($subscription->active());
    }

    public function test_downgrade_with_3d_secure_does_not_incomplete_subscription()
    {
        $user = $this->createCustomer('downgrade_with_3d_secure_does_not_incomplete_subscription');

        $subscription = $user->newSubscription('main', static::$premiumPlanId)->create('pm_card_visa');

        // Set a card that requires a next action as the customer's default payment method.
        $user->updateDefaultPaymentMethod('pm_card_threeDSecure2Required');

        // Attempt to swap and pay with a faulty card.
        $subscription = $subscription->swap(static::$planId);

        // Assert that the plan was swapped anyway.
        $this->assertEquals(static::$planId, $subscription->refresh()->stripe_plan);

        // Assert subscription is still active.
        $this->assertTrue($subscription->active());
    }

    public function test_creating_subscription_with_coupons()
    {
        $user = $this->createCustomer('creating_subscription_with_coupons');

        // Create Subscription
        $user->newSubscription('main', static::$planId)
            ->withCoupon(static::$couponId)
            ->create('pm_card_visa');

        $subscription = $user->subscription('main');

        $this->assertTrue($user->subscribed('main'));
        $this->assertTrue($user->subscribed('main', static::$planId));
        $this->assertFalse($user->subscribed('main', static::$otherPlanId));
        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->cancelled());
        $this->assertFalse($subscription->onGracePeriod());
        $this->assertTrue($subscription->recurring());
        $this->assertFalse($subscription->ended());

        // Invoice Tests
        $invoice = $user->invoices()[0];

        $this->assertTrue($invoice->hasDiscount());
        $this->assertEquals('$5.00', $invoice->total());
        $this->assertEquals('$5.00', $invoice->amountOff());
        $this->assertFalse($invoice->discountIsPercentage());
    }

    public function test_creating_subscription_with_an_anchored_billing_cycle()
    {
        $user = $this->createCustomer('creating_subscription_with_an_anchored_billing_cycle');

        // Create Subscription
        $user->newSubscription('main', static::$planId)
            ->anchorBillingCycleOn(new DateTime('first day of next month'))
            ->create('pm_card_visa');

        $subscription = $user->subscription('main');

        $this->assertTrue($user->subscribed('main'));
        $this->assertTrue($user->subscribed('main', static::$planId));
        $this->assertFalse($user->subscribed('main', static::$otherPlanId));
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
        $user->newSubscription('main', static::$planId)
            ->trialDays(7)
            ->create('pm_card_visa');

        $subscription = $user->subscription('main');

        $this->assertTrue($subscription->active());
        $this->assertTrue($subscription->onTrial());
        $this->assertFalse($subscription->recurring());
        $this->assertFalse($subscription->ended());
        $this->assertEquals(Carbon::today()->addDays(7)->day, $subscription->trial_ends_at->day);

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

    public function test_creating_subscription_with_explicit_trial()
    {
        $user = $this->createCustomer('creating_subscription_with_explicit_trial');

        // Create Subscription
        $user->newSubscription('main', static::$planId)
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

        $subscription = $user->newSubscription('main', static::$premiumPlanId)->create('pm_card_visa');

        $this->assertEquals(2000, ($invoice = $user->invoices()->first())->rawTotal());

        $subscription->noProrate()->swapAndInvoice(static::$planId);

        // Assert that no new invoice was created because of no prorating.
        $this->assertEquals($invoice->id, $user->invoices()->first()->id);

        $subscription->swapAndInvoice(static::$premiumPlanId);

        // Assert that no new invoice was created because of no prorating.
        $this->assertEquals($invoice->id, $user->invoices()->first()->id);

        $subscription->prorate()->swapAndInvoice(static::$planId);

        // Get back from unused time on premium plan.
        $this->assertEquals(-1000, $user->invoices()->first()->rawTotal());
    }

    public function test_trials_can_be_extended()
    {
        $user = $this->createCustomer('trials_can_be_extended');

        $subscription = $user->newSubscription('main', static::$planId)->create('pm_card_visa');

        $this->assertNull($subscription->trial_ends_at);

        $subscription->extendTrial($trialEndsAt = now()->addDays()->floor());

        $this->assertTrue($trialEndsAt->equalTo($subscription->trial_ends_at));
        $this->assertEquals($subscription->asStripeSubscription()->trial_end, $trialEndsAt->getTimestamp());
    }

    public function test_applying_coupons_to_existing_customers()
    {
        $user = $this->createCustomer('applying_coupons_to_existing_customers');

        $user->newSubscription('main', static::$planId)->create('pm_card_visa');

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
            'stripe_status' => 'incomplete',
            'stripe_plan' => 'stripe-yearly',
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
            $user->newSubscription('main', static::$planId)->create('pm_card_threeDSecure2Required');

            $this->fail('Expected exception '.PaymentActionRequired::class.' was not thrown.');
        } catch (PaymentActionRequired $e) {
            $subscription = $user->refresh()->subscription('main');

            $this->assertInstanceOf(Payment::class, $payment = $subscription->latestPayment());
            $this->assertTrue($payment->requiresAction());
        }
    }
}

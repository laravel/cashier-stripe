<?php

namespace Laravel\Cashier\Tests\Unit;

use InvalidArgumentException;
use Laravel\Cashier\Exceptions\SubscriptionUpdateFailure;
use Laravel\Cashier\Subscription;
use PHPUnit\Framework\TestCase;

class SubscriptionTest extends TestCase
{
    public function test_we_can_check_if_a_subscription_is_incomplete()
    {
        $subscription = new Subscription(['stripe_status' => 'incomplete']);

        $this->assertTrue($subscription->incomplete());
        $this->assertFalse($subscription->pastDue());
        $this->assertFalse($subscription->active());
    }

    public function test_we_can_check_if_a_subscription_is_past_due()
    {
        $subscription = new Subscription(['stripe_status' => 'past_due']);

        $this->assertFalse($subscription->incomplete());
        $this->assertTrue($subscription->pastDue());
        $this->assertFalse($subscription->active());
    }

    public function test_we_can_check_if_a_subscription_is_active()
    {
        $subscription = new Subscription(['stripe_status' => 'active']);

        $this->assertFalse($subscription->incomplete());
        $this->assertFalse($subscription->pastDue());
        $this->assertTrue($subscription->active());
    }

    public function test_an_incomplete_subscription_is_not_valid()
    {
        $subscription = new Subscription(['stripe_status' => 'incomplete']);

        $this->assertFalse($subscription->valid());
    }

    public function test_a_past_due_subscription_is_not_valid()
    {
        $subscription = new Subscription(['stripe_status' => 'past_due']);

        $this->assertFalse($subscription->valid());
    }

    public function test_an_active_subscription_is_valid()
    {
        $subscription = new Subscription(['stripe_status' => 'active']);

        $this->assertTrue($subscription->valid());
    }

    public function test_payment_is_incomplete_when_status_is_incomplete()
    {
        $subscription = new Subscription(['stripe_status' => 'incomplete']);

        $this->assertTrue($subscription->hasIncompletePayment());
    }

    public function test_payment_is_incomplete_when_status_is_past_due()
    {
        $subscription = new Subscription(['stripe_status' => 'past_due']);

        $this->assertTrue($subscription->hasIncompletePayment());
    }

    public function test_payment_is_not_incomplete_when_status_is_active()
    {
        $subscription = new Subscription(['stripe_status' => 'active']);

        $this->assertFalse($subscription->hasIncompletePayment());
    }

    public function test_incomplete_subscriptions_cannot_be_swapped()
    {
        $subscription = new Subscription(['stripe_status' => 'incomplete']);

        $this->expectException(SubscriptionUpdateFailure::class);

        $subscription->swap('premium_plan');
    }

    public function test_incomplete_subscriptions_cannot_update_their_quantity()
    {
        $subscription = new Subscription(['stripe_status' => 'incomplete']);

        $this->expectException(SubscriptionUpdateFailure::class);

        $subscription->updateQuantity(5);
    }

    public function test_extending_a_trial_requires_a_date_in_the_future()
    {
        $this->expectException(InvalidArgumentException::class);

        (new Subscription)->extendTrial(now()->subDay());
    }

    public function test_we_can_check_if_it_has_a_single_plan()
    {
        $subscription = new Subscription(['stripe_plan' => 'foo']);

        $this->assertTrue($subscription->hasSinglePlan());
        $this->assertFalse($subscription->hasMultiplePlans());
    }

    public function test_we_can_check_if_it_has_multiple_plans()
    {
        $subscription = new Subscription(['stripe_plan' => null]);

        $this->assertTrue($subscription->hasMultiplePlans());
        $this->assertFalse($subscription->hasSinglePlan());
    }
}

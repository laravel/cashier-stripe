<?php

namespace Laravel\Cashier\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Laravel\Cashier\Subscription;

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
        $this->assertTrue($subscription->active());
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

    public function test_a_past_due_subscription_is_valid()
    {
        $subscription = new Subscription(['stripe_status' => 'past_due']);

        $this->assertTrue($subscription->valid());
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
}

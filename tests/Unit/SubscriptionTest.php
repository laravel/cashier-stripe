<?php

namespace Laravel\Cashier\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Laravel\Cashier\Subscription;

class SubscriptionTest extends TestCase
{
    public function test_we_can_check_if_a_subscription_is_incomplete()
    {
        $subscription = new Subscription(['status' => 'incomplete']);

        $this->assertTrue($subscription->incomplete());
        $this->assertFalse($subscription->active());
    }

    public function test_we_can_check_if_a_subscription_is_active()
    {
        $subscription = new Subscription(['status' => 'active']);

        $this->assertFalse($subscription->incomplete());
        $this->assertTrue($subscription->active());
    }

    public function test_an_incomplete_subscription_is_not_valid()
    {
        $subscription = new Subscription(['status' => 'incomplete']);

        $this->assertFalse($subscription->valid());
    }

    public function test_an_active_subscription_is_valid()
    {
        $subscription = new Subscription(['status' => 'active']);

        $this->assertTrue($subscription->valid());
    }
}

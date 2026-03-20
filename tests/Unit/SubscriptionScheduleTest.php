<?php

namespace Laravel\Cashier\Tests\Unit;

use Laravel\Cashier\Cashier;
use Laravel\Cashier\SubscriptionSchedule;
use PHPUnit\Framework\TestCase;

class SubscriptionScheduleTest extends TestCase
{
    protected function tearDown(): void
    {
        Cashier::$defaultBillingMode = 'classic';

        parent::tearDown();
    }

    public function test_schedule_can_determine_status_not_started()
    {
        $schedule = new SubscriptionSchedule(['stripe_status' => 'not_started']);

        $this->assertTrue($schedule->notStarted());
        $this->assertFalse($schedule->active());
        $this->assertFalse($schedule->completed());
        $this->assertFalse($schedule->released());
        $this->assertFalse($schedule->canceled());
    }

    public function test_schedule_can_determine_status_active()
    {
        $schedule = new SubscriptionSchedule(['stripe_status' => 'active']);

        $this->assertFalse($schedule->notStarted());
        $this->assertTrue($schedule->active());
        $this->assertFalse($schedule->completed());
        $this->assertFalse($schedule->released());
        $this->assertFalse($schedule->canceled());
    }

    public function test_schedule_can_determine_status_completed()
    {
        $schedule = new SubscriptionSchedule(['stripe_status' => 'completed']);

        $this->assertTrue($schedule->completed());
        $this->assertFalse($schedule->active());
    }

    public function test_schedule_can_determine_status_released()
    {
        $schedule = new SubscriptionSchedule(['stripe_status' => 'released']);

        $this->assertTrue($schedule->released());
        $this->assertFalse($schedule->active());
    }

    public function test_schedule_can_determine_status_canceled()
    {
        $schedule = new SubscriptionSchedule(['stripe_status' => 'canceled']);

        $this->assertTrue($schedule->canceled());
        $this->assertFalse($schedule->active());
    }

    public function test_schedule_returns_null_subscription_when_no_subscription_id()
    {
        $schedule = new SubscriptionSchedule([
            'stripe_status' => 'not_started',
            'subscription_id' => null,
        ]);

        $this->assertNull($schedule->subscription());
    }
}

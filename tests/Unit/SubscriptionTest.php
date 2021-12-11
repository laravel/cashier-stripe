<?php

namespace Laravel\Cashier\Tests\Unit;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Laravel\Cashier\Exceptions\SubscriptionUpdateFailure;
use Laravel\Cashier\Subscription;
use PHPUnit\Framework\TestCase;
use Stripe\Subscription as StripeSubscription;

class SubscriptionTest extends TestCase
{
    public function test_we_can_check_if_a_subscription_is_incomplete()
    {
        $subscription = new Subscription([
            'stripe_status' => StripeSubscription::STATUS_INCOMPLETE,
        ]);

        $this->assertTrue($subscription->incomplete());
        $this->assertFalse($subscription->pastDue());
        $this->assertFalse($subscription->active());
    }

    public function test_we_can_check_if_a_subscription_is_past_due()
    {
        $subscription = new Subscription([
            'stripe_status' => StripeSubscription::STATUS_PAST_DUE,
        ]);

        $this->assertFalse($subscription->incomplete());
        $this->assertTrue($subscription->pastDue());
        $this->assertFalse($subscription->active());
    }

    public function test_we_can_check_if_a_subscription_is_active()
    {
        $subscription = new Subscription([
            'stripe_status' => StripeSubscription::STATUS_ACTIVE,
        ]);

        $this->assertFalse($subscription->incomplete());
        $this->assertFalse($subscription->pastDue());
        $this->assertTrue($subscription->active());
    }

    public function test_an_incomplete_subscription_is_not_valid()
    {
        $subscription = new Subscription([
            'stripe_status' => StripeSubscription::STATUS_INCOMPLETE,
        ]);

        $this->assertFalse($subscription->valid());
    }

    public function test_a_past_due_subscription_is_not_valid()
    {
        $subscription = new Subscription([
            'stripe_status' => StripeSubscription::STATUS_PAST_DUE,
        ]);

        $this->assertFalse($subscription->valid());
    }

    public function test_an_active_subscription_is_valid()
    {
        $subscription = new Subscription([
            'stripe_status' => StripeSubscription::STATUS_ACTIVE,
        ]);

        $this->assertTrue($subscription->valid());
    }

    public function test_payment_is_incomplete_when_status_is_incomplete()
    {
        $subscription = new Subscription([
            'stripe_status' => StripeSubscription::STATUS_INCOMPLETE,
        ]);

        $this->assertTrue($subscription->hasIncompletePayment());
    }

    public function test_payment_is_incomplete_when_status_is_past_due()
    {
        $subscription = new Subscription([
            'stripe_status' => StripeSubscription::STATUS_PAST_DUE,
        ]);

        $this->assertTrue($subscription->hasIncompletePayment());
    }

    public function test_payment_is_not_incomplete_when_status_is_active()
    {
        $subscription = new Subscription([
            'stripe_status' => StripeSubscription::STATUS_ACTIVE,
        ]);

        $this->assertFalse($subscription->hasIncompletePayment());
    }

    public function test_incomplete_subscriptions_cannot_be_swapped()
    {
        $subscription = new Subscription([
            'stripe_status' => StripeSubscription::STATUS_INCOMPLETE,
        ]);

        $this->expectException(SubscriptionUpdateFailure::class);

        $subscription->swap('premium_price');
    }

    public function test_incomplete_subscriptions_cannot_update_their_quantity()
    {
        $subscription = new Subscription([
            'stripe_status' => StripeSubscription::STATUS_INCOMPLETE,
        ]);

        $this->expectException(SubscriptionUpdateFailure::class);

        $subscription->updateQuantity(5);
    }

    public function test_extending_a_trial_requires_a_date_in_the_future()
    {
        $this->expectException(InvalidArgumentException::class);

        (new Subscription)->extendTrial(now()->subDay());
    }

    public function test_we_can_check_if_it_has_a_single_price()
    {
        $subscription = new Subscription(['stripe_price' => 'foo']);

        $this->assertTrue($subscription->hasSinglePrice());
        $this->assertFalse($subscription->hasMultiplePrices());
    }

    public function test_we_can_check_if_it_has_multiple_prices()
    {
        $subscription = new Subscription(['stripe_price' => null]);

        $this->assertTrue($subscription->hasMultiplePrices());
        $this->assertFalse($subscription->hasSinglePrice());
    }

    public function test_we_can_check_if_a_subscription_is_not_paused()
    {
        $subscription = new Subscription(['pause_behavior' => null]);

        // Generally ...
        $this->assertFalse($subscription->paused());
        $this->assertTrue($subscription->notPaused());
        $this->assertNull($subscription->pauseResumesAt());

        // Behavior "void" ...
        $this->assertFalse($subscription->paused(Subscription::PAUSE_BEHAVIOR_VOID));
        $this->assertTrue($subscription->notPaused(Subscription::PAUSE_BEHAVIOR_VOID));
        $this->assertNull($subscription->pauseResumesAt(Subscription::PAUSE_BEHAVIOR_VOID));

        // Behavior "mark_uncollectible" ...
        $this->assertFalse($subscription->paused(Subscription::PAUSE_BEHAVIOR_MARK_UNCOLLECTIBLE));
        $this->assertTrue($subscription->notPaused(Subscription::PAUSE_BEHAVIOR_MARK_UNCOLLECTIBLE));
        $this->assertNull($subscription->pauseResumesAt(Subscription::PAUSE_BEHAVIOR_MARK_UNCOLLECTIBLE));

        // Behavior "keep_as_draft" ...
        $this->assertFalse($subscription->paused(Subscription::PAUSE_BEHAVIOR_KEEP_AS_DRAFT));
        $this->assertTrue($subscription->notPaused(Subscription::PAUSE_BEHAVIOR_KEEP_AS_DRAFT));
        $this->assertNull($subscription->pauseResumesAt(Subscription::PAUSE_BEHAVIOR_KEEP_AS_DRAFT));
    }

    public function test_we_can_check_if_a_subscription_is_paused()
    {
        $resumesAt = Carbon::now()->addWeek();
        $subscription = (new Subscription())
            // Prevent call database connection.
            ->setDateFormat('Y-m-d H:i:s')
            ->fill([
                'pause_behavior' => Subscription::PAUSE_BEHAVIOR_MARK_UNCOLLECTIBLE,
                'resumes_at' => $resumesAt,
            ]);

        // Generally ...
        $this->assertTrue($subscription->paused());
        $this->assertFalse($subscription->notPaused());
        $this->assertEquals($resumesAt->timestamp, $subscription->pauseResumesAt()->timestamp);

        // Behavior "void" ...
        $this->assertFalse($subscription->paused(Subscription::PAUSE_BEHAVIOR_VOID));
        $this->assertTrue($subscription->notPaused(Subscription::PAUSE_BEHAVIOR_VOID));
        $this->assertNull($subscription->pauseResumesAt(Subscription::PAUSE_BEHAVIOR_KEEP_AS_DRAFT));

        // Behavior "mark_uncollectible" ...
        $this->assertTrue($subscription->paused(Subscription::PAUSE_BEHAVIOR_MARK_UNCOLLECTIBLE));
        $this->assertFalse($subscription->notPaused(Subscription::PAUSE_BEHAVIOR_MARK_UNCOLLECTIBLE));
        $this->assertEquals($resumesAt->timestamp, $subscription->pauseResumesAt(Subscription::PAUSE_BEHAVIOR_MARK_UNCOLLECTIBLE)->timestamp);

        // Behavior "keep_as_draft" ...
        $this->assertFalse($subscription->paused(Subscription::PAUSE_BEHAVIOR_KEEP_AS_DRAFT));
        $this->assertTrue($subscription->notPaused(Subscription::PAUSE_BEHAVIOR_KEEP_AS_DRAFT));
        $this->assertNull($subscription->pauseResumesAt(Subscription::PAUSE_BEHAVIOR_KEEP_AS_DRAFT));
    }
}

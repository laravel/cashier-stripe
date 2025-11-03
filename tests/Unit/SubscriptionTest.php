<?php

namespace Laravel\Cashier\Tests\Unit;

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

    public function test_it_can_determine_if_the_subscription_is_on_trial()
    {
        $subscription = new Subscription();
        $subscription->setDateFormat('Y-m-d H:i:s');
        $subscription->trial_ends_at = now()->addDay();

        $this->assertTrue($subscription->onTrial());

        $subscription = new Subscription();
        $subscription->setDateFormat('Y-m-d H:i:s');
        $subscription->trial_ends_at = now()->subDay();

        $this->assertFalse($subscription->onTrial());
    }

    public function test_it_can_determine_if_a_trial_has_expired()
    {
        $subscription = new Subscription();
        $subscription->setDateFormat('Y-m-d H:i:s');
        $subscription->trial_ends_at = now()->subDay();

        $this->assertTrue($subscription->hasExpiredTrial());

        $subscription = new Subscription();
        $subscription->setDateFormat('Y-m-d H:i:s');
        $subscription->trial_ends_at = now()->addDay();

        $this->assertFalse($subscription->hasExpiredTrial());
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

    public function test_canceled_returns_true_when_stripe_status_is_canceled()
    {
        $subscription = new Subscription([
            'stripe_status' => StripeSubscription::STATUS_CANCELED,
            'ends_at' => null,
        ]);

        $this->assertTrue($subscription->canceled());
    }

    public function test_canceled_returns_true_when_ends_at_is_set()
    {
        $subscription = new Subscription();
        $subscription->setDateFormat('Y-m-d H:i:s');
        $subscription->stripe_status = StripeSubscription::STATUS_ACTIVE;
        $subscription->ends_at = now()->addDay();

        $this->assertTrue($subscription->canceled());
    }

    public function test_canceled_returns_true_when_both_status_and_ends_at_are_set()
    {
        $subscription = new Subscription();
        $subscription->setDateFormat('Y-m-d H:i:s');
        $subscription->stripe_status = StripeSubscription::STATUS_CANCELED;
        $subscription->ends_at = now()->addDay();

        $this->assertTrue($subscription->canceled());
    }

    public function test_active_returns_false_when_stripe_status_is_canceled()
    {
        $subscription = new Subscription([
            'stripe_status' => StripeSubscription::STATUS_CANCELED,
            'ends_at' => null,
        ]);

        $this->assertFalse($subscription->active());
    }

    public function test_active_returns_false_when_stripe_status_is_canceled_with_ends_at()
    {
        $subscription = new Subscription();
        $subscription->setDateFormat('Y-m-d H:i:s');
        $subscription->stripe_status = StripeSubscription::STATUS_CANCELED;
        $subscription->ends_at = now()->addDay();

        $this->assertFalse($subscription->active());
    }

    public function test_on_trial_returns_false_when_stripe_status_is_canceled()
    {
        $subscription = new Subscription();
        $subscription->setDateFormat('Y-m-d H:i:s');
        $subscription->stripe_status = StripeSubscription::STATUS_CANCELED;
        $subscription->trial_ends_at = now()->addDay();

        $this->assertFalse($subscription->onTrial());
    }

    public function test_on_trial_returns_false_when_stripe_status_is_canceled_even_without_trial()
    {
        $subscription = new Subscription([
            'stripe_status' => StripeSubscription::STATUS_CANCELED,
            'trial_ends_at' => null,
        ]);

        $this->assertFalse($subscription->onTrial());
    }

    public function test_on_grace_period_returns_false_when_stripe_status_is_canceled()
    {
        $subscription = new Subscription();
        $subscription->setDateFormat('Y-m-d H:i:s');
        $subscription->stripe_status = StripeSubscription::STATUS_CANCELED;
        $subscription->ends_at = now()->addDay();

        $this->assertFalse($subscription->onGracePeriod());
    }

    public function test_on_grace_period_returns_false_when_stripe_status_is_canceled_even_without_ends_at()
    {
        $subscription = new Subscription([
            'stripe_status' => StripeSubscription::STATUS_CANCELED,
            'ends_at' => null,
        ]);

        $this->assertFalse($subscription->onGracePeriod());
    }

    public function test_on_grace_period_returns_true_when_ends_at_is_future_and_status_is_active()
    {
        $subscription = new Subscription();
        $subscription->setDateFormat('Y-m-d H:i:s');
        $subscription->stripe_status = StripeSubscription::STATUS_ACTIVE;
        $subscription->ends_at = now()->addDay();

        // Should be on grace period when ends_at is in future and not STATUS_CANCELED
        $this->assertTrue($subscription->onGracePeriod());
        $this->assertTrue($subscription->canceled()); // Scheduled for cancellation
        $this->assertTrue($subscription->valid()); // But still valid due to grace period
    }

    public function test_on_trial_returns_false_when_subscription_is_canceled_with_past_ends_at()
    {
        $subscription = new Subscription();
        $subscription->setDateFormat('Y-m-d H:i:s');
        $subscription->stripe_status = StripeSubscription::STATUS_ACTIVE;
        $subscription->trial_ends_at = now()->addDay();
        $subscription->ends_at = now()->subDay(); // Canceled in the past

        // Should not be on trial if canceled (ends_at in past)
        $this->assertFalse($subscription->onTrial());
        $this->assertTrue($subscription->canceled());
        $this->assertFalse($subscription->valid());
    }

    public function test_on_trial_returns_false_when_canceled_now_with_trial()
    {
        $subscription = new Subscription();
        $subscription->setDateFormat('Y-m-d H:i:s');
        $subscription->stripe_status = StripeSubscription::STATUS_CANCELED;
        $subscription->trial_ends_at = now()->addDay();
        $subscription->ends_at = now(); // Canceled now (current time)

        // Should not be on trial if canceled immediately
        $this->assertFalse($subscription->onTrial());
        $this->assertTrue($subscription->canceled());
        $this->assertFalse($subscription->valid());
    }

    public function test_valid_returns_false_when_stripe_status_is_canceled()
    {
        $subscription = new Subscription([
            'stripe_status' => StripeSubscription::STATUS_CANCELED,
            'ends_at' => null,
        ]);

        $this->assertFalse($subscription->valid());
    }

    public function test_valid_returns_false_when_stripe_status_is_canceled_with_ends_at()
    {
        $subscription = new Subscription();
        $subscription->setDateFormat('Y-m-d H:i:s');
        $subscription->stripe_status = StripeSubscription::STATUS_CANCELED;
        $subscription->ends_at = now()->addDay();

        $this->assertFalse($subscription->valid());
    }

    public function test_valid_returns_false_when_stripe_status_is_canceled_with_trial()
    {
        $subscription = new Subscription();
        $subscription->setDateFormat('Y-m-d H:i:s');
        $subscription->stripe_status = StripeSubscription::STATUS_CANCELED;
        $subscription->trial_ends_at = now()->addDay();

        $this->assertFalse($subscription->valid());
    }

    public function test_ended_returns_true_when_stripe_status_is_canceled()
    {
        $subscription = new Subscription([
            'stripe_status' => StripeSubscription::STATUS_CANCELED,
            'ends_at' => null,
        ]);

        $this->assertTrue($subscription->ended());
    }

    public function test_ended_returns_true_when_stripe_status_is_canceled_with_past_ends_at()
    {
        $subscription = new Subscription();
        $subscription->setDateFormat('Y-m-d H:i:s');
        $subscription->stripe_status = StripeSubscription::STATUS_CANCELED;
        $subscription->ends_at = now()->subDay();

        $this->assertTrue($subscription->ended());
    }

    public function test_ended_returns_false_when_canceled_via_ends_at_but_still_on_grace_period()
    {
        $subscription = new Subscription();
        $subscription->setDateFormat('Y-m-d H:i:s');
        $subscription->stripe_status = StripeSubscription::STATUS_ACTIVE;
        $subscription->ends_at = now()->addDay();

        // Should not be ended because it's on grace period (canceled via ends_at, not STATUS_CANCELED)
        $this->assertFalse($subscription->ended());
    }
}

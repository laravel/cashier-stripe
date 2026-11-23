<?php

namespace Laravel\Cashier\Tests\Unit;

use Laravel\Cashier\Subscription;
use Laravel\Cashier\Tests\TestCase;
use Stripe\Subscription as StripeSubscription;

class SubscriptionUpcomingInvoiceTest extends TestCase
{
    public function test_upcoming_invoice_is_available_during_the_grace_period()
    {
        $owner = new class
        {
            public bool $called = false;

            /** @var array<string, mixed> */
            public array $options = [];

            public function upcomingInvoice(array $options = [])
            {
                $this->called = true;
                $this->options = $options;

                return null;
            }
        };

        $subscription = new Subscription([
            'stripe_id' => 'sub_test',
            'stripe_status' => StripeSubscription::STATUS_ACTIVE,
            'ends_at' => now()->addDays(5),
        ]);
        $subscription->setRelation('owner', $owner);

        $this->assertTrue($subscription->canceled());
        $this->assertTrue($subscription->onGracePeriod());
        $this->assertFalse($subscription->ended());
        $this->assertNull($subscription->upcomingInvoice());
        $this->assertTrue($owner->called);
        $this->assertSame('sub_test', $owner->options['subscription']);
    }

    public function test_upcoming_invoice_is_null_after_the_subscription_has_ended()
    {
        $owner = new class
        {
            public bool $called = false;

            public function upcomingInvoice(array $options = [])
            {
                $this->called = true;

                return null;
            }
        };

        $subscription = new Subscription([
            'stripe_id' => 'sub_test',
            'stripe_status' => StripeSubscription::STATUS_CANCELED,
            'ends_at' => now()->subDay(),
        ]);
        $subscription->setRelation('owner', $owner);

        $this->assertTrue($subscription->ended());
        $this->assertNull($subscription->upcomingInvoice());
        $this->assertFalse($owner->called);
    }
}

<?php

namespace Laravel\Cashier\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Stripe\Subscription as StripeSubscription;

class SubscriptionBuilderTest extends FeatureTestCase
{
    public function test_subscription_stored_by_webhook_while_creating_is_returned()
    {
        $user = $this->createCustomer('subscription_stored_by_webhook_while_creating_is_returned');

        $stripeSubscription = StripeSubscription::constructFrom([
            'id' => 'sub_123',
            'status' => 'active',
            'items' => ['object' => 'list', 'data' => [
                ['id' => 'si_123', 'quantity' => 1, 'price' => ['id' => 'price_123', 'product' => 'prod_123']],
            ]],
        ]);

        // The "customer.subscription.created" webhook stores the subscription right after Cashier looked for it...
        $stored = false;

        DB::listen(function ($query) use ($user, &$stored) {
            if (! $stored && str_starts_with($query->sql, 'select * from "subscriptions"')) {
                $stored = true;

                DB::table('subscriptions')->insert([
                    'user_id' => $user->id,
                    'type' => 'main',
                    'stripe_id' => 'sub_123',
                    'stripe_status' => 'active',
                ]);
            }
        });

        $builder = $user->newSubscription('main', 'price_123');

        $subscription = (fn () => $this->createSubscription($stripeSubscription))->call($builder);

        $this->assertSame('sub_123', $subscription->stripe_id);
        $this->assertDatabaseCount('subscriptions', 1);
    }
}

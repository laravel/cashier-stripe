<?php

namespace Laravel\Cashier\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Laravel\Cashier\SubscriptionBuilder;
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

        // Simulate the "customer.subscription.created" webhook concurrently inserting the
        // subscription right after Cashier has looked it up and before Cashier inserts it...
        $webhookSubscriptionId = null;

        DB::listen(function ($query) use ($user, &$webhookSubscriptionId) {
            $sql = strtolower(str_replace(['"', '`', '[', ']'], '', $query->sql));

            if (is_null($webhookSubscriptionId) &&
                str_starts_with($sql, 'select') &&
                str_contains($sql, 'from subscriptions') &&
                str_contains($sql, 'stripe_id')) {
                $webhookSubscriptionId = DB::table('subscriptions')->insertGetId([
                    'user_id' => $user->id,
                    'type' => 'main',
                    'stripe_id' => 'sub_123',
                    'stripe_status' => 'active',
                ]);
            }
        });

        $builder = new class($user, 'main', 'price_123') extends SubscriptionBuilder
        {
            public function createSubscription(StripeSubscription $stripeSubscription)
            {
                return parent::createSubscription($stripeSubscription);
            }
        };

        $subscription = $builder->createSubscription($stripeSubscription);

        $this->assertNotNull($webhookSubscriptionId);
        $this->assertSame($webhookSubscriptionId, $subscription->id);
        $this->assertSame('sub_123', $subscription->stripe_id);
        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertDatabaseCount('subscription_items', 0);
    }
}

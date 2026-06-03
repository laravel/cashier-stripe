<?php

namespace Laravel\Cashier\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Cashier\Http\Controllers\WebhookController;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\SubscriptionItem;
use Laravel\Cashier\Tests\TestCase;
use Orchestra\Testbench\Attributes\WithMigration;
use Orchestra\Testbench\Concerns\WithLaravelMigrations;
use Stripe\Subscription as StripeSubscription;

#[WithMigration]
class WebhookConcurrencyTest extends TestCase
{
    use RefreshDatabase;
    use WithLaravelMigrations;

    public function test_subscription_created_webhook_handles_concurrent_subscription_and_item_inserts()
    {
        $user = User::forceCreate([
            'email' => 'concurrent-webhook@cashier-test.com',
            'name' => 'Taylor Otwell',
            'password' => 'password',
            'stripe_id' => 'cus_foo',
        ]);

        $subscriptionCreated = false;
        $itemCreated = false;

        Event::listen('eloquent.creating: '.Subscription::class, function (Subscription $subscription) use (&$subscriptionCreated, $user) {
            if ($subscriptionCreated || $subscription->stripe_id !== 'sub_foo') {
                return;
            }

            $subscriptionCreated = true;

            Subscription::withoutEvents(function () use ($user) {
                $user->subscriptions()->create([
                    'type' => 'main',
                    'stripe_id' => 'sub_foo',
                    'stripe_price' => 'price_bar',
                    'stripe_status' => StripeSubscription::STATUS_INCOMPLETE,
                    'quantity' => 1,
                ]);
            });
        });

        Event::listen('eloquent.creating: '.SubscriptionItem::class, function (SubscriptionItem $item) use (&$itemCreated) {
            if ($itemCreated || $item->stripe_id !== 'bar') {
                return;
            }

            $itemCreated = true;

            SubscriptionItem::withoutEvents(function () use ($item) {
                SubscriptionItem::query()->create([
                    'subscription_id' => $item->subscription_id,
                    'stripe_id' => 'bar',
                    'stripe_product' => 'prod_old',
                    'stripe_price' => 'price_old',
                    'quantity' => 1,
                ]);
            });
        });

        $response = (new WebhookConcurrencyController)->handleCustomerSubscriptionCreated([
            'id' => 'foo',
            'type' => 'customer.subscription.created',
            'data' => [
                'object' => [
                    'id' => 'sub_foo',
                    'customer' => 'cus_foo',
                    'cancel_at_period_end' => false,
                    'quantity' => 10,
                    'items' => [
                        'data' => [[
                            'id' => 'bar',
                            'price' => ['id' => 'price_foo', 'product' => 'prod_bar'],
                            'quantity' => 10,
                        ]],
                    ],
                    'status' => 'active',
                ],
            ],
        ]);

        $this->assertSame(200, $response->getStatusCode());

        $this->assertDatabaseHas('subscriptions', [
            'type' => 'default',
            'user_id' => $user->id,
            'stripe_id' => 'sub_foo',
            'stripe_status' => 'active',
            'stripe_price' => 'price_foo',
            'quantity' => 10,
        ]);

        $this->assertDatabaseHas('subscription_items', [
            'stripe_id' => 'bar',
            'stripe_product' => 'prod_bar',
            'stripe_price' => 'price_foo',
            'quantity' => 10,
        ]);

        $this->assertSame(1, $user->subscriptions()->where('stripe_id', 'sub_foo')->count());
        $this->assertSame(1, SubscriptionItem::query()->where('stripe_id', 'bar')->count());
    }
}

class WebhookConcurrencyController extends WebhookController
{
    public function __construct()
    {
        //
    }

    public function handleCustomerSubscriptionCreated(array $payload)
    {
        return parent::handleCustomerSubscriptionCreated($payload);
    }
}

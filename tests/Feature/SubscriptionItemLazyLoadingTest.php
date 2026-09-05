<?php

namespace Laravel\Cashier\Tests\Feature;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\Tests\TestCase;
use Orchestra\Testbench\Attributes\WithMigration;
use Orchestra\Testbench\Concerns\WithLaravelMigrations;
use Stripe\Subscription as StripeSubscription;

#[WithMigration]
class SubscriptionItemLazyLoadingTest extends TestCase
{
    use RefreshDatabase;
    use WithLaravelMigrations;

    protected function tearDown(): void
    {
        Model::preventLazyLoading(false);

        parent::tearDown();
    }

    public function test_subscription_items_are_given_their_parent_subscription()
    {
        $this->createSubscriptionWithItems();

        Model::preventLazyLoading();

        $subscription = Subscription::firstOrFail();

        foreach ($subscription->items as $item) {
            $this->assertTrue($item->relationLoaded('subscription'));
            $this->assertTrue($subscription->is($item->subscription));
        }
    }

    public function test_subscription_items_do_not_query_for_their_parent_subscription()
    {
        $this->createSubscriptionWithItems();

        $subscription = Subscription::firstOrFail();

        $queries = 0;

        $this->app['db']->listen(function () use (&$queries) {
            $queries++;
        });

        foreach ($subscription->items as $item) {
            $item->subscription;
        }

        $this->assertSame(0, $queries);
    }

    public function test_subscription_items_are_given_their_parent_subscription_when_loaded_lazily()
    {
        $this->createSubscriptionWithItems();

        $subscription = Subscription::firstOrFail();

        $subscription->unsetRelation('items');

        foreach ($subscription->items as $item) {
            $this->assertTrue($subscription->is($item->subscription));
        }
    }

    public function test_the_parent_subscription_is_not_serialized_onto_its_items()
    {
        $this->createSubscriptionWithItems();

        $subscription = Subscription::firstOrFail();

        $array = $subscription->toArray();

        $this->assertArrayHasKey('items', $array);
        $this->assertArrayNotHasKey('subscription', $array['items'][0]);

        // A circular reference would recurse forever on Laravel 10, which has
        // no PreventsCircularRecursion guard, so assert the shape stays flat:
        // the items collection must appear exactly once in the payload.
        $this->assertSame(1, substr_count($subscription->toJson(), '"items":'));
    }

    protected function createSubscriptionWithItems(): Subscription
    {
        $user = User::forceCreate([
            'email' => 'taylor@cashier-test.com',
            'name' => 'Taylor Otwell',
            'password' => 'secret',
        ]);

        $subscription = $user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_foo',
            'stripe_status' => StripeSubscription::STATUS_ACTIVE,
        ]);

        foreach (['si_foo', 'si_bar'] as $stripeId) {
            $subscription->items()->create([
                'stripe_id' => $stripeId,
                'stripe_product' => 'prod_foo',
                'stripe_price' => 'price_foo',
                'quantity' => 1,
            ]);
        }

        return $subscription;
    }
}

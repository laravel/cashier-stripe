<?php

namespace Laravel\Cashier\Tests\Unit;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\SubscriptionItem;
use Laravel\Cashier\Tests\TestCase;
use Stripe\SubscriptionItem as StripeSubscriptionItem;

class SubscriptionCurrentPeriodTest extends TestCase
{
    protected function tearDown(): void
    {
        Model::preventLazyLoading(false);

        parent::tearDown();
    }

    public function test_current_period_start_does_not_lazy_load_subscription_on_items()
    {
        Model::preventLazyLoading();

        [$subscription, $item] = $this->subscriptionWithUnchaperonedItem();

        $start = $subscription->currentPeriodStart();

        $this->assertNotNull($start);
        $this->assertTrue($item->relationLoaded('subscription'));
        $this->assertTrue($item->retrievedStripeItem);
    }

    public function test_current_period_end_does_not_lazy_load_subscription_on_items()
    {
        Model::preventLazyLoading();

        [$subscription, $item] = $this->subscriptionWithUnchaperonedItem();

        $end = $subscription->currentPeriodEnd();

        $this->assertNotNull($end);
        $this->assertTrue($item->relationLoaded('subscription'));
        $this->assertTrue($item->retrievedStripeItem);
    }

    public function test_current_period_helpers_work_across_multiple_subscriptions_with_lazy_loading_disabled()
    {
        Model::preventLazyLoading();

        $subscriptions = Collection::make([
            $this->subscriptionWithUnchaperonedItem()[0],
            $this->subscriptionWithUnchaperonedItem()[0],
        ]);

        $subscriptions->each->currentPeriodStart();
        $subscriptions->each->currentPeriodEnd();

        $this->assertTrue(true);
    }

    /**
     * @return array{0: \Laravel\Cashier\Subscription, 1: \Laravel\Cashier\SubscriptionItem}
     */
    protected function subscriptionWithUnchaperonedItem(): array
    {
        $owner = new User;
        $owner->id = 1;
        $owner->exists = true;

        $subscription = new Subscription;
        $subscription->id = 1;
        $subscription->exists = true;
        $subscription->setRelation('owner', $owner);

        $item = new class extends SubscriptionItem
        {
            public bool $retrievedStripeItem = false;

            public function asStripeSubscriptionItem(array $expand = [])
            {
                $this->retrievedStripeItem = true;

                // Touches inverse relations the same way production code does.
                $this->subscription->owner;

                return StripeSubscriptionItem::constructFrom([
                    'id' => 'si_test',
                    'object' => 'subscription_item',
                    'current_period_start' => now()->subDay()->getTimestamp(),
                    'current_period_end' => now()->addDay()->getTimestamp(),
                ]);
            }
        };
        $item->id = 1;
        $item->exists = true;
        $item->stripe_id = 'si_test';

        // Mimic eager-loaded items without the inverse subscription relation.
        $subscription->setRelation('items', new Collection([$item]));

        return [$subscription, $item];
    }
}

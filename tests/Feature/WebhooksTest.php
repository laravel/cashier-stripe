<?php

namespace Laravel\Cashier\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Cashier\Exceptions\IncompletePayment;
use Laravel\Cashier\Notifications\ConfirmPayment;
use Stripe\Subscription as StripeSubscription;

class WebhooksTest extends FeatureTestCase
{
    /**
     * @var string
     */
    protected static $productId;

    /**
     * @var string
     */
    protected static $priceId;

    public static function setUpBeforeClass(): void
    {
        if (! getenv('STRIPE_SECRET')) {
            return;
        }

        parent::setUpBeforeClass();

        static::$productId = self::stripe()->products->create([
            'name' => 'Laravel Cashier Test Product',
            'type' => 'service',
        ])->id;

        static::$priceId = self::stripe()->prices->create([
            'product' => static::$productId,
            'nickname' => 'Monthly $10',
            'currency' => 'USD',
            'recurring' => [
                'interval' => 'month',
            ],
            'billing_scheme' => 'per_unit',
            'unit_amount' => 1000,
        ])->id;
    }

    public function test_subscriptions_are_created()
    {
        $user = $this->createCustomer('subscriptions_are_created', ['stripe_id' => 'cus_foo']);

        $this->postJson('stripe/webhook', [
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
        ])->assertOk();

        $this->assertDatabaseHas('subscriptions', [
            'name' => 'default',
            'user_id' => $user->id,
            'stripe_id' => 'sub_foo',
            'stripe_status' => 'active',
            'quantity' => 10,
        ]);

        $this->assertDatabaseHas('subscription_items', [
            'stripe_id' => 'bar',
            'stripe_product' => 'prod_bar',
            'stripe_price' => 'price_foo',
            'quantity' => 10,
        ]);
    }

    public function test_subscriptions_are_updated()
    {
        $user = $this->createCustomer('subscriptions_are_updated', ['stripe_id' => 'cus_foo']);

        $subscription = $user->subscriptions()->create([
            'name' => 'main',
            'stripe_id' => 'sub_foo',
            'stripe_price' => 'price_foo',
            'stripe_status' => StripeSubscription::STATUS_ACTIVE,
        ]);

        $item = $subscription->items()->create([
            'stripe_id' => 'it_'.Str::random(10),
            'stripe_product' => 'prod_bar',
            'stripe_price' => 'price_bar',
            'quantity' => 1,
        ]);

        $this->postJson('stripe/webhook', [
            'id' => 'foo',
            'type' => 'customer.subscription.updated',
            'data' => [
                'object' => [
                    'id' => $subscription->stripe_id,
                    'customer' => 'cus_foo',
                    'cancel_at_period_end' => false,
                    'items' => [
                        'data' => [[
                            'id' => 'bar',
                            'price' => ['id' => 'price_foo', 'product' => 'prod_bar'],
                            'quantity' => 5,
                        ]],
                    ],
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'user_id' => $user->id,
            'stripe_id' => 'sub_foo',
            'quantity' => 5,
        ]);

        $this->assertDatabaseHas('subscription_items', [
            'subscription_id' => $subscription->id,
            'stripe_id' => 'bar',
            'stripe_product' => 'prod_bar',
            'stripe_price' => 'price_foo',
            'quantity' => 5,
        ]);

        $this->assertDatabaseMissing('subscription_items', [
            'id' => $item->id,
        ]);
    }

    public function test_subscriptions_on_update_cancel_at_date_is_correct()
    {
        $user = $this->createCustomer('subscriptions_on_update_cancel_at_date_is_correct', ['stripe_id' => 'cus_foo']);
        $cancelDate = Carbon::now()->addMonths(6);

        $subscription = $user->subscriptions()->create([
            'name' => 'main',
            'stripe_id' => 'sub_foo',
            'stripe_price' => 'price_foo',
            'stripe_status' => StripeSubscription::STATUS_ACTIVE,
        ]);

        $item = $subscription->items()->create([
            'stripe_id' => 'it_'.Str::random(10),
            'stripe_product' => 'prod_bar',
            'stripe_price' => 'price_bar',
            'quantity' => 1,
        ]);

        $this->postJson('stripe/webhook', [
            'id' => 'foo',
            'type' => 'customer.subscription.updated',
            'data' => [
                'object' => [
                    'id' => $subscription->stripe_id,
                    'customer' => 'cus_foo',
                    'cancel_at' => $cancelDate->timestamp,
                    'cancel_at_period_end' => false,
                    'items' => [
                        'data' => [[
                            'id' => 'bar',
                            'price' => ['id' => 'price_foo', 'product' => 'prod_bar'],
                            'quantity' => 5,
                        ]],
                    ],
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'user_id' => $user->id,
            'stripe_id' => 'sub_foo',
            'quantity' => 5,
            'ends_at' => $cancelDate->format('Y-m-d H:i:s'),
        ]);

        $this->assertDatabaseHas('subscription_items', [
            'subscription_id' => $subscription->id,
            'stripe_id' => 'bar',
            'stripe_product' => 'prod_bar',
            'stripe_price' => 'price_foo',
            'quantity' => 5,
        ]);

        $this->assertDatabaseMissing('subscription_items', [
            'id' => $item->id,
        ]);
    }

    public function test_canceled_subscription_is_properly_reactivated()
    {
        $user = $this->createCustomer('canceled_subscription_is_properly_reactivated');
        $subscription = $user->newSubscription('main', static::$priceId)->create('pm_card_visa')->cancel();

        $this->assertTrue($subscription->canceled());

        $this->postJson('stripe/webhook', [
            'id' => 'foo',
            'type' => 'customer.subscription.updated',
            'data' => [
                'object' => [
                    'id' => $subscription->stripe_id,
                    'customer' => $user->stripe_id,
                    'cancel_at_period_end' => false,
                    'items' => [
                        'data' => [[
                            'id' => $subscription->items()->first()->stripe_id,
                            'price' => ['id' => static::$priceId, 'product' => static::$productId],
                            'quantity' => 1,
                        ]],
                    ],
                ],
            ],
        ])->assertOk();

        $this->assertFalse($subscription->fresh()->canceled(), 'Subscription is still canceled.');
    }

    public function test_subscription_is_marked_as_canceled_when_deleted_in_stripe()
    {
        $user = $this->createCustomer('subscription_is_marked_as_canceled_when_deleted_in_stripe');
        $subscription = $user->newSubscription('main', static::$priceId)->create('pm_card_visa');

        $this->assertFalse($subscription->canceled());

        $this->postJson('stripe/webhook', [
            'id' => 'foo',
            'type' => 'customer.subscription.deleted',
            'data' => [
                'object' => [
                    'id' => $subscription->stripe_id,
                    'customer' => $user->stripe_id,
                    'quantity' => 1,
                ],
            ],
        ])->assertOk();

        $this->assertTrue($subscription->fresh()->canceled(), 'Subscription is still active.');
    }

    public function test_subscription_is_deleted_when_status_is_incomplete_expired()
    {
        $user = $this->createCustomer('subscription_is_deleted_when_status_is_incomplete_expired');
        $subscription = $user->newSubscription('main', static::$priceId)->create('pm_card_visa');

        $this->assertCount(1, $user->subscriptions);

        $this->postJson('stripe/webhook', [
            'id' => 'foo',
            'type' => 'customer.subscription.updated',
            'data' => [
                'object' => [
                    'id' => $subscription->stripe_id,
                    'customer' => $user->stripe_id,
                    'status' => StripeSubscription::STATUS_INCOMPLETE_EXPIRED,
                    'quantity' => 1,
                ],
            ],
        ])->assertOk();

        $this->assertEmpty($user->refresh()->subscriptions, 'Subscription was not deleted.');
    }

    public function test_payment_action_required_email_is_sent()
    {
        $user = $this->createCustomer('payment_action_required_email_is_sent');

        try {
            $user->newSubscription('main', static::$priceId)->create('pm_card_threeDSecure2Required');

            $this->fail('Expected exception '.IncompletePayment::class.' was not thrown.');
        } catch (IncompletePayment $exception) {
            Notification::fake();

            $this->postJson('stripe/webhook', [
                'id' => 'foo',
                'type' => 'invoice.payment_action_required',
                'data' => [
                    'object' => [
                        'id' => 'foo',
                        'customer' => $user->stripe_id,
                        'payment_intent' => $exception->payment->id,
                    ],
                ],
            ])->assertOk();

            Notification::assertSentTo($user, ConfirmPayment::class, function (ConfirmPayment $notification) use ($exception) {
                return $notification->paymentId === $exception->payment->id &&
                    $notification->amount === $exception->payment->amount();
            });
        }
    }
}

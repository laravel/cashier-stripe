<?php

namespace Laravel\Cashier\Tests\Feature;

use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Cashier\Exceptions\PaymentActionRequired;
use Laravel\Cashier\Notifications\ConfirmPayment;
use Stripe\Plan;
use Stripe\Product;
use Stripe\Subscription;

class WebhooksTest extends FeatureTestCase
{
    /**
     * @var string
     */
    protected static $productId;

    /**
     * @var string
     */
    protected static $planId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        static::$productId = static::$stripePrefix.'product-1'.Str::random(10);
        static::$planId = static::$stripePrefix.'monthly-10-'.Str::random(10);

        Product::create([
            'id' => static::$productId,
            'name' => 'Laravel Cashier Test Product',
            'type' => 'service',
        ]);

        Plan::create([
            'id' => static::$planId,
            'nickname' => 'Monthly $10',
            'currency' => 'USD',
            'interval' => 'month',
            'billing_scheme' => 'per_unit',
            'amount' => 1000,
            'product' => static::$productId,
        ]);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        static::deleteStripeResource(new Plan(static::$planId));
        static::deleteStripeResource(new Product(static::$productId));
    }

    public function test_subscriptions_are_created()
    {
        $user = $this->createCustomer('subscriptions_are_updated', ['stripe_id' => 'cus_foo']);

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
                            'plan' => ['id' => 'plan_foo'],
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
            'stripe_plan' => 'plan_foo',
            'quantity' => 10,
        ]);
    }

    public function test_subscriptions_are_updated()
    {
        $user = $this->createCustomer('subscriptions_are_updated', ['stripe_id' => 'cus_foo']);

        $subscription = $user->subscriptions()->create([
            'name' => 'main',
            'stripe_id' => 'sub_foo',
            'stripe_plan' => 'plan_foo',
            'stripe_status' => Subscription::STATUS_ACTIVE,
        ]);

        $item = $subscription->items()->create([
            'stripe_id' => 'it_foo',
            'stripe_plan' => 'plan_bar',
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
                            'plan' => ['id' => 'plan_foo'],
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
            'stripe_plan' => 'plan_foo',
            'quantity' => 5,
        ]);

        $this->assertDatabaseMissing('subscription_items', [
            'id' => $item->id,
        ]);
    }

    public function test_cancelled_subscription_is_properly_reactivated()
    {
        $user = $this->createCustomer('cancelled_subscription_is_properly_reactivated');
        $subscription = $user->newSubscription('main', static::$planId)->create('pm_card_visa');
        $subscription->cancel();

        $this->assertTrue($subscription->cancelled());

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
                            'plan' => ['id' => static::$planId],
                            'quantity' => 1,
                        ]],
                    ],
                ],
            ],
        ])->assertOk();

        $this->assertFalse($subscription->fresh()->cancelled(), 'Subscription is still cancelled.');
    }

    public function test_subscription_is_marked_as_cancelled_when_deleted_in_stripe()
    {
        $user = $this->createCustomer('subscription_is_marked_as_cancelled_when_deleted_in_stripe');
        $subscription = $user->newSubscription('main', static::$planId)->create('pm_card_visa');

        $this->assertFalse($subscription->cancelled());

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

        $this->assertTrue($subscription->fresh()->cancelled(), 'Subscription is still active.');
    }

    public function test_subscription_is_deleted_when_status_is_incomplete_expired()
    {
        $user = $this->createCustomer('subscription_is_deleted_when_status_is_incomplete_expired');
        $subscription = $user->newSubscription('main', static::$planId)->create('pm_card_visa');

        $this->assertCount(1, $user->subscriptions);

        $this->postJson('stripe/webhook', [
            'id' => 'foo',
            'type' => 'customer.subscription.updated',
            'data' => [
                'object' => [
                    'id' => $subscription->stripe_id,
                    'customer' => $user->stripe_id,
                    'status' => Subscription::STATUS_INCOMPLETE_EXPIRED,
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
            $user->newSubscription('main', static::$planId)->create('pm_card_threeDSecure2Required');

            $this->fail('Expected exception '.PaymentActionRequired::class.' was not thrown.');
        } catch (PaymentActionRequired $exception) {
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

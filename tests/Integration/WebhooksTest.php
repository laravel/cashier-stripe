<?php

namespace Laravel\Cashier\Tests\Integration;

use Stripe\Plan;
use Stripe\Product;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Laravel\Cashier\Mail\ConfirmPayment;
use Laravel\Cashier\Exceptions\PaymentActionRequired;

class WebhooksTest extends IntegrationTestCase
{
    /**
     * @var string
     */
    protected static $productId;

    /**
     * @var string
     */
    protected static $planId;

    public static function setUpBeforeClass()
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

    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();

        static::deleteStripeResource(new Plan(static::$planId));
        static::deleteStripeResource(new Product(static::$productId));
    }

    public function test_cancelled_subscription_is_properly_reactivated()
    {
        $user = $this->createCustomer('cancelled_subscription_is_properly_reactivated');
        $subscription = $user->newSubscription('main', static::$planId)->create('tok_visa');
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
                ],
            ],
        ])->assertOk();

        $this->assertFalse($subscription->fresh()->cancelled(), 'Subscription is still cancelled.');
    }

    public function test_subscription_is_marked_as_cancelled_when_deleted_in_stripe()
    {
        $user = $this->createCustomer('subscription_is_marked_as_cancelled_when_deleted_in_stripe');
        $subscription = $user->newSubscription('main', static::$planId)->create('tok_visa');

        $this->assertFalse($subscription->cancelled());

        $this->postJson('stripe/webhook', [
            'id' => 'foo',
            'type' => 'customer.subscription.deleted',
            'data' => [
                'object' => [
                    'id' => $subscription->stripe_id,
                    'customer' => $user->stripe_id,
                ],
            ],
        ])->assertOk();

        $this->assertTrue($subscription->fresh()->cancelled(), 'Subscription is still active.');
    }

    public function test_payment_action_required_email_is_sent()
    {
        config(['cashier.payment_emails' => true]);

        $user = $this->createCustomer('payment_action_required_email_is_sent');

        try {
            $user->newSubscription('main', static::$planId)->create('tok_threeDSecure2Required');

            $this->fail('Expected exception '.PaymentActionRequired::class.' was not thrown.');
        } catch (PaymentActionRequired $exception) {
            Mail::fake();

            $this->postJson('stripe/webhook', [
                'id' => 'foo',
                'type' => 'invoice.payment_action_required',
                'data' => [
                    'object' => [
                        'id' => 'foo',
                        'customer' => $user->stripe_id,
                        'payment_intent' => $exception->payment->id(),
                    ],
                ],
            ])->assertOk();

            Mail::assertSent(ConfirmPayment::class, function (ConfirmPayment $mail) use ($user) {
                return $mail->hasTo($user->email);
            });
        }
    }
}

<?php

namespace Laravel\Cashier\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\SubscriptionBuilder;
use Laravel\Cashier\Tests\TestCase;
use Orchestra\Testbench\Attributes\WithMigration;
use Orchestra\Testbench\Concerns\WithLaravelMigrations;
use Stripe\Subscription as StripeSubscription;

#[WithMigration]
class SubscriptionCreationOrderTest extends TestCase
{
    use RefreshDatabase;
    use WithLaravelMigrations;

    public function test_subscription_creation_converges_for_both_orderings_and_replays()
    {
        $applicationFirstUser = $this->createCustomer('cus_application_first');
        $applicationFirstBuilder = new TestSubscriptionBuilder($applicationFirstUser, 'premium');
        $applicationFirstBuilder->createFromStripe($this->stripeSubscription('sub_application_first'));

        $applicationFirstPayload = $this->subscriptionCreatedPayload('cus_application_first', 'sub_application_first');

        $this->postJson('stripe/webhook', $applicationFirstPayload)->assertOk();
        $this->postJson('stripe/webhook', $applicationFirstPayload)->assertOk();

        $webhookFirstUser = $this->createCustomer('cus_webhook_first');
        $webhookFirstPayload = $this->subscriptionCreatedPayload('cus_webhook_first', 'sub_webhook_first');

        $this->postJson('stripe/webhook', $webhookFirstPayload)->assertOk();
        $this->postJson('stripe/webhook', $webhookFirstPayload)->assertOk();

        $webhookFirstBuilder = new TestSubscriptionBuilder($webhookFirstUser, 'premium');
        $stripeSubscription = $this->stripeSubscription('sub_webhook_first');

        $webhookFirstBuilder->createFromStripe($stripeSubscription);
        $webhookFirstBuilder->createFromStripe($stripeSubscription);
        $this->postJson('stripe/webhook', $webhookFirstPayload)->assertOk();

        $this->assertDatabaseCount('subscriptions', 2);
        $this->assertDatabaseHas('subscriptions', [
            'stripe_id' => 'sub_application_first',
            'type' => 'premium',
        ]);
        $this->assertDatabaseHas('subscriptions', [
            'stripe_id' => 'sub_webhook_first',
            'type' => 'premium',
        ]);
    }

    private function createCustomer(string $stripeId): User
    {
        return User::forceCreate([
            'email' => $stripeId.'@cashier-test.com',
            'name' => 'Taylor Otwell',
            'password' => 'password',
            'stripe_id' => $stripeId,
        ]);
    }

    private function stripeSubscription(string $stripeId): StripeSubscription
    {
        return StripeSubscription::constructFrom([
            'id' => $stripeId,
            'status' => StripeSubscription::STATUS_ACTIVE,
            'items' => [
                'object' => 'list',
                'data' => [[
                    'id' => 'si_'.$stripeId,
                    'price' => [
                        'id' => 'price_foo',
                        'product' => 'prod_foo',
                        'recurring' => [],
                    ],
                    'quantity' => 1,
                ]],
            ],
        ]);
    }

    private function subscriptionCreatedPayload(string $customerId, string $subscriptionId): array
    {
        return [
            'id' => 'evt_'.$subscriptionId,
            'type' => 'customer.subscription.created',
            'data' => [
                'object' => [
                    'id' => $subscriptionId,
                    'customer' => $customerId,
                    'items' => [
                        'data' => [[
                            'id' => 'si_'.$subscriptionId,
                            'price' => ['id' => 'price_foo', 'product' => 'prod_foo'],
                            'quantity' => 1,
                        ]],
                    ],
                    'status' => StripeSubscription::STATUS_ACTIVE,
                ],
            ],
        ];
    }
}

class TestSubscriptionBuilder extends SubscriptionBuilder
{
    public function createFromStripe(StripeSubscription $stripeSubscription): Subscription
    {
        return $this->createSubscription($stripeSubscription);
    }
}

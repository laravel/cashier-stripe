<?php

namespace Laravel\Cashier\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Tests\TestCase;
use Orchestra\Testbench\Concerns\WithLaravelMigrations;
use Stripe\Subscription as StripeSubscription;

class WebhookConcurrencyTest extends TestCase
{
    use RefreshDatabase;
    use WithLaravelMigrations;

    public function test_created_webhook_is_idempotent_when_subscription_already_exists()
    {
        $user = User::forceCreate([
            'email' => 'webhook-created@cashier-test.com',
            'name' => 'Taylor Otwell',
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'stripe_id' => 'cus_webhook_created',
        ]);

        $user->subscriptions()->create([
            'type' => 'main',
            'stripe_id' => 'sub_webhook_created',
            'stripe_status' => StripeSubscription::STATUS_INCOMPLETE,
            'stripe_price' => 'price_old',
            'quantity' => 1,
        ]);

        $this->postJson('stripe/webhook', [
            'id' => 'evt_created',
            'type' => 'customer.subscription.created',
            'data' => [
                'object' => [
                    'id' => 'sub_webhook_created',
                    'customer' => 'cus_webhook_created',
                    'cancel_at_period_end' => false,
                    'items' => [
                        'data' => [[
                            'id' => 'si_webhook_created',
                            'price' => ['id' => 'price_new', 'product' => 'prod_new'],
                            'quantity' => 2,
                        ]],
                    ],
                    'status' => 'active',
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'stripe_id' => 'sub_webhook_created',
            'type' => 'main',
            'stripe_status' => 'active',
            'stripe_price' => 'price_new',
            'quantity' => 2,
        ]);
    }

    public function test_updated_webhook_creates_then_updates_without_duplicate_rows()
    {
        $user = User::forceCreate([
            'email' => 'webhook-updated@cashier-test.com',
            'name' => 'Taylor Otwell',
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'stripe_id' => 'cus_webhook_updated',
        ]);

        $payload = [
            'id' => 'evt_updated',
            'type' => 'customer.subscription.updated',
            'data' => [
                'object' => [
                    'id' => 'sub_webhook_updated',
                    'customer' => 'cus_webhook_updated',
                    'cancel_at_period_end' => false,
                    'items' => [
                        'data' => [[
                            'id' => 'si_webhook_updated',
                            'price' => ['id' => 'price_updated', 'product' => 'prod_updated'],
                            'quantity' => 3,
                        ]],
                    ],
                    'status' => 'active',
                ],
            ],
        ];

        $this->postJson('stripe/webhook', $payload)->assertOk();
        $this->postJson('stripe/webhook', $payload)->assertOk();

        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'stripe_id' => 'sub_webhook_updated',
            'stripe_status' => 'active',
            'quantity' => 3,
        ]);
    }
}

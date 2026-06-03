<?php

namespace Laravel\Cashier\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Tests\TestCase;
use Orchestra\Testbench\Concerns\WithLaravelMigrations;
use Stripe\Subscription as StripeSubscription;

class SubscriptionStatusTest extends TestCase
{
    use RefreshDatabase;
    use WithLaravelMigrations;

    public function test_canceled_status_subscriptions_are_not_active()
    {
        $user = User::forceCreate([
            'email' => 'canceled_status_subscriptions_are_not_active@cashier-test.com',
            'name' => 'Taylor Otwell',
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        ]);

        $subscription = $user->subscriptions()->create([
            'type' => 'yearly',
            'stripe_id' => 'sub_canceled',
            'stripe_status' => StripeSubscription::STATUS_CANCELED,
            'stripe_price' => 'stripe-yearly',
            'quantity' => 1,
            'trial_ends_at' => null,
            'ends_at' => null,
        ]);

        $this->assertFalse($subscription->active());
        $this->assertFalse($subscription->valid());
        $this->assertFalse($user->subscriptions()->active()->exists());
    }
}

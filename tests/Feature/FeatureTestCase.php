<?php

namespace Laravel\Cashier\Tests\Feature;

use Laravel\Cashier\Cashier;
use Laravel\Cashier\Tests\Fixtures\User;
use Laravel\Cashier\Tests\TestCase;
use Stripe\StripeClient;

abstract class FeatureTestCase extends TestCase
{
    protected function setUp(): void
    {
        // Delay consecutive tests to prevent Stripe rate limiting issues.
        sleep(2);

        parent::setUp();

        $this->loadLaravelMigrations();

        $this->artisan('migrate')->run();
    }

    protected static function stripe(array $options = []): StripeClient
    {
        return Cashier::stripe(array_merge(['api_key' => getenv('STRIPE_SECRET')], $options));
    }

    protected function createCustomer($description = 'taylor', array $options = []): User
    {
        return User::create(array_merge([
            'email' => "{$description}@cashier-test.com",
            'name' => 'Taylor Otwell',
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        ], $options));
    }
}

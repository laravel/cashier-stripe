<?php

namespace Laravel\Cashier\Tests\Integration;

use Stripe\Stripe;
use Stripe\ApiResource;
use Stripe\Error\InvalidRequest;
use Laravel\Cashier\Tests\TestCase;
use Laravel\Cashier\Tests\Fixtures\User;
use Illuminate\Database\Eloquent\Model as Eloquent;

abstract class IntegrationTestCase extends TestCase
{
    /**
     * @var string
     */
    protected static $stripePrefix = 'cashier-test-';

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        Stripe::setApiKey(getenv('STRIPE_SECRET'));
    }

    public function setUp(): void
    {
        parent::setUp();

        Eloquent::unguard();

        $this->loadLaravelMigrations();

        $this->artisan('migrate')->run();
    }

    protected static function deleteStripeResource(ApiResource $resource)
    {
        try {
            $resource->delete();
        } catch (InvalidRequest $e) {
            //
        }
    }

    protected function createCustomer($description = 'taylor'): User
    {
        return User::create([
            'email' => "{$description}@cashier-test.com",
            'name' => 'Taylor Otwell',
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        ]);
    }
}

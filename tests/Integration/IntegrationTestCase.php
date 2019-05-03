<?php

namespace Laravel\Cashier\Tests\Integration;

use Stripe\Token;
use Stripe\Stripe;
use Stripe\ApiResource;
use Stripe\Error\InvalidRequest;
use Orchestra\Testbench\TestCase;
use Laravel\Cashier\Tests\Fixtures\User;
use Laravel\Cashier\CashierServiceProvider;
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

    protected function getPackageProviders($app)
    {
        return [CashierServiceProvider::class];
    }

    protected static function deleteStripeResource(ApiResource $resource)
    {
        try {
            $resource->delete();
        } catch (InvalidRequest $e) {
            //
        }
    }

    protected function getTestToken($cardNumber = null)
    {
        return Token::create([
            'card' => [
                'number' => $cardNumber ?? '4242424242424242',
                'exp_month' => 5,
                'exp_year' => date('Y') + 1,
                'cvc' => '123',
            ],
        ])->id;
    }

    protected function getInvalidCardToken()
    {
        return $this->getTestToken('4000 0000 0000 0341');
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

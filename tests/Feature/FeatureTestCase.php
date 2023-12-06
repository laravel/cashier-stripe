<?php

namespace Laravel\Cashier\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Tests\Fixtures\User;
use Laravel\Cashier\Tests\TestCase;
use Orchestra\Testbench\Concerns\WithLaravelMigrations;
use Stripe\ApiRequestor as StripeApiRequestor;
use Stripe\HttpClient\CurlClient as StripeCurlClient;
use Stripe\StripeClient;

abstract class FeatureTestCase extends TestCase
{
    use RefreshDatabase, WithLaravelMigrations;

    protected function setUp(): void
    {
        if (! getenv('STRIPE_SECRET')) {
            $this->markTestSkipped('Stripe secret key not set.');
        }

        parent::setUp();

        $curl = new StripeCurlClient([CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1]);
        $curl->setEnableHttp2(false);
        StripeApiRequestor::setHttpClient($curl);
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

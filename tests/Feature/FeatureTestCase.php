<?php

namespace Laravel\Cashier\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Tests\TestCase;
use Orchestra\Testbench\Attributes\RequiresEnv;
use Orchestra\Testbench\Concerns\WithLaravelMigrations;
use Stripe\ApiRequestor as StripeApiRequestor;
use Stripe\HttpClient\CurlClient as StripeCurlClient;
use Stripe\StripeClient;

#[RequiresEnv('STRIPE_SECRET')]
abstract class FeatureTestCase extends TestCase
{
    use RefreshDatabase;
    use WithLaravelMigrations;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (($apiKey = getenv('STRIPE_SECRET')) && ! Str::startsWith($apiKey, 'sk_test_')) {
            throw new InvalidArgumentException('Tests may not be run with a production Stripe key.');
        }
    }

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
        return User::forceCreate(array_merge([
            'email' => "{$description}@cashier-test.com",
            'name' => 'Taylor Otwell',
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        ], $options));
    }
}

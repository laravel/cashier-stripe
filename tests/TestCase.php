<?php

namespace Laravel\Cashier\Tests;

use Illuminate\Support\Str;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\CashierServiceProvider;
use Laravel\Cashier\Exceptions\StripeSecretKeyException;
use Laravel\Cashier\Tests\Fixtures\User;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getEnvironmentSetUp($app)
    {
        $apiKey = getenv('STRIPE_SECRET');
        if ($apiKey && ! Str::startsWith($apiKey, 'sk_test_')) {
            throw StripeSecretKeyException::invalidEnvironment($app->environment());
        }
        Cashier::useCustomerModel(User::class);
    }

    protected function getPackageProviders($app)
    {
        return [CashierServiceProvider::class];
    }
}

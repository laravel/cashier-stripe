<?php

namespace Laravel\Cashier\Tests;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\CashierServiceProvider;
use Laravel\Cashier\Tests\Fixtures\User;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getEnvironmentSetUp($app)
    {
        $apiKey = getenv('STRIPE_SECRET');

        if ($apiKey && ! Str::startsWith($apiKey, 'sk_test_')) {
            throw new InvalidArgumentException("Tests may not be run with a production Stripe key.");
        }

        Cashier::useCustomerModel(User::class);
    }

    protected function getPackageProviders($app)
    {
        return [CashierServiceProvider::class];
    }
}

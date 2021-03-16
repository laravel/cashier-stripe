<?php

namespace Laravel\Cashier\Tests;

use Laravel\Cashier\Cashier;
use Laravel\Cashier\CashierServiceProvider;
use Laravel\Cashier\Tests\Fixtures\User;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getEnvironmentSetUp($app)
    {
        Cashier::useCustomerModel(User::class);
    }

    protected function getPackageProviders($app)
    {
        return [CashierServiceProvider::class];
    }
}

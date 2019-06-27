<?php

namespace Laravel\Cashier\Tests;

use Laravel\Cashier\CashierServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app)
    {
        return [CashierServiceProvider::class];
    }
}

<?php

namespace Laravel\Cashier\Tests;

use Stripe\Stripe;
use Laravel\Cashier\Cashier;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp()
    {
        Stripe::setApiVersion(Cashier::$stripeVersion);
    }
}

<?php

namespace Laravel\Cashier\Tests;

use Stripe\Stripe;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp()
    {
        Stripe::setApiVersion('2018-11-08');
    }
}

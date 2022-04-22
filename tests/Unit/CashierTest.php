<?php

namespace Laravel\Cashier\Tests\Unit;

use Laravel\Cashier\Cashier;
use Laravel\Cashier\Tests\TestCase;

class CashierTest extends TestCase
{
    public function test_it_can_format_an_amount()
    {
        $this->assertSame('$10.00', Cashier::formatAmount(1000));
    }

    public function test_it_can_format_an_amount_without_digits()
    {
        $this->assertSame('$10', Cashier::formatAmount(1000, null, null, ['min_fraction_digits' => 0]));
    }
}

<?php

use Mockery as m;
use Laravel\Cashier\LineItem;

class LineItemTest extends PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        m::close();
    }

    public function testReceivingDollarTotal()
    {
        $line = new LineItem($billable = m::mock('Laravel\Cashier\Contracts\Billable'), (object) ['amount' => 10000]);
        $billable->shouldReceive('formatCurrency')->andReturn(100.00);
        $this->assertEquals(100.00, $line->total());
    }
}

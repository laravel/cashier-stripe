<?php

use Mockery as m;
use Laravel\Cashier\LineItem;

class LineItemTest extends PHPUnit_Framework_TestCase {

	public function tearDown()
	{
		m::close();
	}


	public function testReceivingDollarTotal()
	{
		$line = new LineItem((object) ['amount' => 10000]);
		$this->assertEquals(100.00, $line->total());
	}

}

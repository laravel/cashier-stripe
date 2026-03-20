<?php

namespace Laravel\Cashier\Tests\Unit;

use Laravel\Cashier\Quote;
use PHPUnit\Framework\TestCase;

class QuoteTest extends TestCase
{
    public function test_quote_can_determine_draft_status()
    {
        $quote = new Quote(['status' => 'draft']);

        $this->assertTrue($quote->draft());
        $this->assertFalse($quote->open());
        $this->assertFalse($quote->accepted());
        $this->assertFalse($quote->canceled());
    }

    public function test_quote_can_determine_open_status()
    {
        $quote = new Quote(['status' => 'open']);

        $this->assertFalse($quote->draft());
        $this->assertTrue($quote->open());
        $this->assertFalse($quote->accepted());
        $this->assertFalse($quote->canceled());
    }

    public function test_quote_can_determine_accepted_status()
    {
        $quote = new Quote(['status' => 'accepted']);

        $this->assertFalse($quote->draft());
        $this->assertFalse($quote->open());
        $this->assertTrue($quote->accepted());
        $this->assertFalse($quote->canceled());
    }

    public function test_quote_can_determine_canceled_status()
    {
        $quote = new Quote(['status' => 'canceled']);

        $this->assertFalse($quote->draft());
        $this->assertFalse($quote->open());
        $this->assertFalse($quote->accepted());
        $this->assertTrue($quote->canceled());
    }

    public function test_quote_uses_cashier_quotes_table()
    {
        $quote = new Quote;

        $this->assertSame('cashier_quotes', $quote->getTable());
    }

    public function test_quote_casts_amounts_to_integer()
    {
        $quote = new Quote([
            'amount_subtotal' => '1000',
            'amount_total' => '1050',
        ]);

        $this->assertIsInt($quote->amount_subtotal);
        $this->assertIsInt($quote->amount_total);
        $this->assertSame(1000, $quote->amount_subtotal);
        $this->assertSame(1050, $quote->amount_total);
    }
}

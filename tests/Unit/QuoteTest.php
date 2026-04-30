<?php

namespace Laravel\Cashier\Tests\Unit;

use Laravel\Cashier\Quote;
use PHPUnit\Framework\TestCase;

class QuoteTest extends TestCase
{
    public function test_quote_with_billing_mode()
    {
        $quote = new Quote();
        $result = $quote->withBillingMode('flexible');

        // Should return quote for method chaining
        $this->assertSame($quote, $result);

        // Use reflection to access protected property
        $reflection = new \ReflectionClass($quote);
        $property = $reflection->getProperty('billingMode');

        $this->assertEquals(['type' => 'flexible'], $property->getValue($quote));
    }

    public function test_quote_billing_mode_payload_omits_classic()
    {
        $quote = new Quote();
        $quote->withBillingMode('classic');

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($quote);
        $method = $reflection->getMethod('getBillingModeForPayload');

        $this->assertNull($method->invoke($quote));
    }

    public function test_quote_effective_billing_mode()
    {
        $quote = new Quote();
        $quote->withBillingMode('flexible');

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($quote);
        $method = $reflection->getMethod('getEffectiveBillingMode');

        $this->assertEquals('flexible', $method->invoke($quote));
    }

    public function test_quote_default_billing_mode_parameter()
    {
        $quote = new Quote();
        $result = $quote->withBillingMode(); // No parameter should default to 'flexible'

        // Should return quote for method chaining
        $this->assertSame($quote, $result);

        // Use reflection to access protected property
        $reflection = new \ReflectionClass($quote);
        $property = $reflection->getProperty('billingMode');

        $this->assertEquals(['type' => 'flexible'], $property->getValue($quote));
    }

    public function test_quote_status_checks()
    {
        $quote = new Quote();

        // Test draft status
        $quote->status = 'draft';
        $this->assertTrue($quote->draft());
        $this->assertFalse($quote->open());
        $this->assertFalse($quote->accepted());
        $this->assertFalse($quote->canceled());

        // Test open status
        $quote->status = 'open';
        $this->assertFalse($quote->draft());
        $this->assertTrue($quote->open());
        $this->assertFalse($quote->accepted());
        $this->assertFalse($quote->canceled());

        // Test accepted status
        $quote->status = 'accepted';
        $this->assertFalse($quote->draft());
        $this->assertFalse($quote->open());
        $this->assertTrue($quote->accepted());
        $this->assertFalse($quote->canceled());

        // Test canceled status
        $quote->status = 'canceled';
        $this->assertFalse($quote->draft());
        $this->assertFalse($quote->open());
        $this->assertFalse($quote->accepted());
        $this->assertTrue($quote->canceled());
    }

    public function test_quote_casts_attributes_correctly()
    {
        $quote = new Quote();
        $casts = $quote->getCasts();

        $this->assertEquals('integer', $casts['amount_subtotal']);
        $this->assertEquals('integer', $casts['amount_total']);
        $this->assertEquals('datetime', $casts['created_at']);
        $this->assertEquals('datetime', $casts['updated_at']);
        $this->assertEquals('datetime', $casts['expires_at']);
        $this->assertEquals('datetime', $casts['status_transitions_finalized_at']);
        $this->assertEquals('datetime', $casts['status_transitions_accepted_at']);
        $this->assertEquals('datetime', $casts['status_transitions_canceled_at']);
    }

    public function test_quote_has_correct_table_name()
    {
        $quote = new Quote();
        $this->assertEquals('quotes', $quote->getTable());
    }

    public function test_quote_has_no_guarded_attributes()
    {
        $quote = new Quote();
        $this->assertEquals([], $quote->getGuarded());
    }

    public function test_quote_number_methods()
    {
        $quote = new Quote();

        // Mock asStripeQuote method
        $mockStripeQuote = new TestStripeQuote();
        $mockStripeQuote->number = 'QT001';

        $quote = $this->getMockBuilder(Quote::class)
            ->onlyMethods(['asStripeQuote'])
            ->getMock();

        $quote->expects($this->any())
            ->method('asStripeQuote')
            ->willReturn($mockStripeQuote);

        // Test number() method
        $this->assertEquals('QT001', $quote->number());

        // Test formattedNumber() method with default format
        $this->assertEquals('QT-QT001', $quote->formattedNumber());

        // Test formattedNumber() method with custom format
        $this->assertEquals('Quote #QT001', $quote->formattedNumber('Quote #%s'));

        // Test hasCustomNumber() method
        $this->assertTrue($quote->hasCustomNumber());
    }

    public function test_quote_number_methods_with_null_number()
    {
        $mockStripeQuote = new TestStripeQuote();
        $mockStripeQuote->number = null;

        $quote = $this->getMockBuilder(Quote::class)
            ->onlyMethods(['asStripeQuote'])
            ->getMock();

        $quote->expects($this->any())
            ->method('asStripeQuote')
            ->willReturn($mockStripeQuote);

        // Test number() method returns null
        $this->assertNull($quote->number());

        // Test formattedNumber() method returns null
        $this->assertNull($quote->formattedNumber());

        // Test hasCustomNumber() method returns false
        $this->assertFalse($quote->hasCustomNumber());
    }

    public function test_quote_generate_reference()
    {
        $quote = new Quote();
        $quote->id = 123;
        $quote->stripe_id = 'qt_1234567890abcdef';

        $reference = $quote->generateReference();

        $this->assertEquals('quote_123_90abcdef', $reference);
    }

    public function test_quote_conversion_methods_when_not_accepted()
    {
        $quote = new Quote();
        $quote->status = 'draft';

        $this->assertNull($quote->subscription());
        $this->assertNull($quote->subscriptionSchedule());
        $this->assertNull($quote->invoice());
    }

    public function test_quote_find_by_number()
    {
        $mockOwner = new class()
        {
            public $quotes;
        };

        $quote1 = $this->getMockBuilder(Quote::class)
            ->onlyMethods(['number'])
            ->getMock();
        $quote1->method('number')->willReturn('QT001');

        $quote2 = $this->getMockBuilder(Quote::class)
            ->onlyMethods(['number'])
            ->getMock();
        $quote2->method('number')->willReturn('QT002');

        $quotes = collect([$quote1, $quote2]);
        $mockOwner->quotes = $quotes;

        // Test finding existing quote
        $foundQuote = Quote::findByNumber($mockOwner, 'QT002');
        $this->assertSame($quote2, $foundQuote);

        // Test finding non-existing quote
        $notFoundQuote = Quote::findByNumber($mockOwner, 'QT999');
        $this->assertNull($notFoundQuote);
    }
}

class TestStripeQuote extends \Stripe\Quote
{
    public $number;

    public function __construct()
    {
        // Skip parent constructor to avoid API calls
    }
}

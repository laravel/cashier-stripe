<?php

namespace Laravel\Cashier\Tests\Unit;

use Mockery as m;
use Carbon\Carbon;
use Stripe\Discount;
use Carbon\CarbonTimeZone;
use Laravel\Cashier\Invoice;
use PHPUnit\Framework\TestCase;
use Stripe\Invoice as StripeInvoice;
use Laravel\Cashier\Tests\Fixtures\User;

class InvoiceTest extends TestCase
{
    public function tearDown()
    {
        m::close();

        parent::tearDown();
    }

    public function test_it_can_return_the_invoice_date()
    {
        $stripeInvoice = new StripeInvoice();
        $stripeInvoice->created = 1560541724;
        $invoice = new Invoice(new User(), $stripeInvoice);

        $date = $invoice->date();

        $this->assertInstanceOf(Carbon::class, $date);
        $this->assertEquals(1560541724, $date->unix());
    }

    public function test_it_can_return_the_invoice_date_with_a_timezone()
    {
        $stripeInvoice = new StripeInvoice();
        $stripeInvoice->created = 1560541724;
        $invoice = new Invoice(new User(), $stripeInvoice);

        $date = $invoice->date('CET');

        $this->assertInstanceOf(CarbonTimeZone::class, $timezone = $date->getTimezone());
        $this->assertEquals('CET', $timezone->getName());
    }

    public function test_it_can_return_its_total()
    {
        $stripeInvoice = new StripeInvoice();
        $stripeInvoice->total = 1000;
        $stripeInvoice->currency = 'USD';
        $invoice = new Invoice(new User(), $stripeInvoice);

        $total = $invoice->total();

        $this->assertEquals('$10.00', $total);
    }

    public function test_it_can_return_its_raw_total()
    {
        $stripeInvoice = new StripeInvoice();
        $stripeInvoice->total = 1000;
        $stripeInvoice->currency = 'USD';
        $invoice = new Invoice(new User(), $stripeInvoice);

        $total = $invoice->rawTotal();

        $this->assertEquals(1000, $total);
    }

    public function test_it_returns_a_lower_total_when_there_was_a_starting_balance()
    {
        $stripeInvoice = new StripeInvoice();
        $stripeInvoice->total = 1000;
        $stripeInvoice->currency = 'USD';
        $stripeInvoice->starting_balance = -450;
        $invoice = new Invoice(new User(), $stripeInvoice);

        $total = $invoice->total();

        $this->assertEquals('$5.50', $total);
    }

    public function test_it_can_return_its_subtotal()
    {
        $stripeInvoice = new StripeInvoice();
        $stripeInvoice->subtotal = 500;
        $stripeInvoice->currency = 'USD';
        $invoice = new Invoice(new User(), $stripeInvoice);

        $subtotal = $invoice->subtotal();

        $this->assertEquals('$5.00', $subtotal);
    }

    public function test_it_can_determine_when_the_customer_has_a_starting_balance()
    {
        $stripeInvoice = new StripeInvoice();
        $stripeInvoice->starting_balance = -450;
        $invoice = new Invoice(new User(), $stripeInvoice);

        $this->assertTrue($invoice->hasStartingBalance());
    }

    public function test_it_can_determine_when_the_customer_does_not_have_a_starting_balance()
    {
        $stripeInvoice = new StripeInvoice();
        $stripeInvoice->starting_balance = 0;
        $invoice = new Invoice(new User(), $stripeInvoice);

        $this->assertFalse($invoice->hasStartingBalance());
    }

    public function test_it_can_return_its_starting_balance()
    {
        $stripeInvoice = new StripeInvoice();
        $stripeInvoice->starting_balance = -450;
        $stripeInvoice->currency = 'USD';
        $invoice = new Invoice(new User(), $stripeInvoice);

        $startingBalance = $invoice->startingBalance();

        $this->assertEquals('-$4.50', $startingBalance);
    }

    public function test_it_can_return_its_raw_starting_balance()
    {
        $stripeInvoice = new StripeInvoice();
        $stripeInvoice->starting_balance = -450;
        $invoice = new Invoice(new User(), $stripeInvoice);

        $startingBalance = $invoice->rawStartingBalance();

        $this->assertEquals(-450, $startingBalance);
    }

    public function test_it_can_determine_if_it_has_a_discount_applied()
    {
        $stripeInvoice = new StripeInvoice();
        $stripeInvoice->subtotal = 450;
        $stripeInvoice->total = 500;
        $stripeInvoice->discount = new Discount();
        $invoice = new Invoice(new User(), $stripeInvoice);

        $this->assertTrue($invoice->hasDiscount());
    }

    public function test_it_can_return_its_tax()
    {
        $stripeInvoice = new StripeInvoice();
        $stripeInvoice->tax = 50;
        $stripeInvoice->currency = 'USD';
        $invoice = new Invoice(new User(), $stripeInvoice);

        $tax = $invoice->tax();

        $this->assertEquals('$0.50', $tax);
    }
}

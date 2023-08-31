<?php

namespace Laravel\Cashier\Tests\Unit;

use Carbon\Carbon;
use Carbon\CarbonTimeZone;
use Laravel\Cashier\Discount;
use Laravel\Cashier\Invoice;
use Laravel\Cashier\Tests\Fixtures\User;
use Laravel\Cashier\Tests\TestCase;
use Mockery as m;
use stdClass;
use Stripe\Customer as StripeCustomer;
use Stripe\Discount as StripeDiscount;
use Stripe\Invoice as StripeInvoice;

class InvoiceTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();

        parent::tearDown();
    }

    public function test_it_can_return_the_invoice_date()
    {
        $stripeInvoice = new StripeInvoice();
        $stripeInvoice->customer = 'foo';
        $stripeInvoice->created = 1560541724;

        $user = new User();
        $user->stripe_id = 'foo';

        $invoice = new Invoice($user, $stripeInvoice);

        $date = $invoice->date();

        $this->assertInstanceOf(Carbon::class, $date);
        $this->assertEquals(1560541724, $date->unix());
    }

    public function test_it_can_return_the_invoice_date_with_a_timezone()
    {
        $stripeInvoice = new StripeInvoice();
        $stripeInvoice->customer = 'foo';
        $stripeInvoice->created = 1560541724;

        $user = new User();
        $user->stripe_id = 'foo';

        $invoice = new Invoice($user, $stripeInvoice);

        $date = $invoice->date('CET');

        $this->assertInstanceOf(CarbonTimeZone::class, $timezone = $date->getTimezone());
        $this->assertEquals('CET', $timezone->getName());
    }

    public function test_it_can_return_its_total()
    {
        $stripeInvoice = new StripeInvoice();
        $stripeInvoice->customer = 'foo';
        $stripeInvoice->total = 1000;
        $stripeInvoice->currency = 'USD';

        $user = new User();
        $user->stripe_id = 'foo';

        $invoice = new Invoice($user, $stripeInvoice);

        $total = $invoice->realTotal();

        $this->assertEquals('$10.00', $total);
    }

    public function test_it_can_return_its_raw_total()
    {
        $stripeInvoice = new StripeInvoice();
        $stripeInvoice->customer = 'foo';
        $stripeInvoice->total = 1000;
        $stripeInvoice->currency = 'USD';

        $user = new User();
        $user->stripe_id = 'foo';

        $invoice = new Invoice($user, $stripeInvoice);

        $total = $invoice->rawRealTotal();

        $this->assertEquals(1000, $total);
    }

    public function test_it_returns_a_lower_total_when_there_was_a_starting_balance()
    {
        $stripeInvoice = new StripeInvoice();
        $stripeInvoice->customer = 'foo';
        $stripeInvoice->total = 1000;
        $stripeInvoice->currency = 'USD';
        $stripeInvoice->starting_balance = -450;

        $user = new User();
        $user->stripe_id = 'foo';

        $invoice = new Invoice($user, $stripeInvoice);

        $total = $invoice->total();

        $this->assertEquals('$5.50', $total);
    }

    public function test_it_can_return_its_subtotal()
    {
        $stripeInvoice = new StripeInvoice();
        $stripeInvoice->customer = 'foo';
        $stripeInvoice->subtotal = 500;
        $stripeInvoice->currency = 'USD';

        $user = new User();
        $user->stripe_id = 'foo';

        $invoice = new Invoice($user, $stripeInvoice);

        $subtotal = $invoice->subtotal();

        $this->assertEquals('$5.00', $subtotal);
    }

    public function test_it_can_determine_when_the_customer_has_a_starting_balance()
    {
        $stripeInvoice = new StripeInvoice();
        $stripeInvoice->customer = 'foo';
        $stripeInvoice->starting_balance = -450;

        $user = new User();
        $user->stripe_id = 'foo';

        $invoice = new Invoice($user, $stripeInvoice);

        $this->assertTrue($invoice->hasStartingBalance());
    }

    public function test_it_can_determine_when_the_customer_does_not_have_a_starting_balance()
    {
        $stripeInvoice = new StripeInvoice();
        $stripeInvoice->customer = 'foo';
        $stripeInvoice->starting_balance = 0;

        $user = new User();
        $user->stripe_id = 'foo';

        $invoice = new Invoice($user, $stripeInvoice);

        $this->assertFalse($invoice->hasStartingBalance());
    }

    public function test_it_can_return_its_starting_balance()
    {
        $stripeInvoice = new StripeInvoice();
        $stripeInvoice->customer = 'foo';
        $stripeInvoice->starting_balance = -450;
        $stripeInvoice->currency = 'USD';

        $user = new User();
        $user->stripe_id = 'foo';

        $invoice = new Invoice($user, $stripeInvoice);

        $this->assertEquals('-$4.50', $invoice->startingBalance());
        $this->assertEquals(-450, $invoice->rawStartingBalance());
    }

    public function test_it_can_return_its_ending_balance()
    {
        $stripeInvoice = new StripeInvoice();
        $stripeInvoice->customer = 'foo';
        $stripeInvoice->ending_balance = -450;
        $stripeInvoice->currency = 'USD';

        $user = new User();
        $user->stripe_id = 'foo';

        $invoice = new Invoice($user, $stripeInvoice);

        $this->assertEquals('-$4.50', $invoice->endingBalance());
        $this->assertEquals(-450, $invoice->rawEndingBalance());
    }

    public function test_it_can_return_its_applied_balance()
    {
        $stripeInvoice = new StripeInvoice();
        $stripeInvoice->customer = 'foo';
        $stripeInvoice->ending_balance = -350;
        $stripeInvoice->starting_balance = -500;
        $stripeInvoice->currency = 'USD';

        $user = new User();
        $user->stripe_id = 'foo';

        $invoice = new Invoice($user, $stripeInvoice);

        $this->assertTrue($invoice->hasAppliedBalance());
        $this->assertEquals('-$1.50', $invoice->appliedBalance());
        $this->assertEquals(-150, $invoice->rawAppliedBalance());
    }

    public function test_it_can_return_its_applied_balance_when_depleted()
    {
        $stripeInvoice = new StripeInvoice();
        $stripeInvoice->customer = 'foo';
        $stripeInvoice->ending_balance = 0;
        $stripeInvoice->starting_balance = -500;
        $stripeInvoice->currency = 'USD';

        $user = new User();
        $user->stripe_id = 'foo';

        $invoice = new Invoice($user, $stripeInvoice);

        $this->assertTrue($invoice->hasAppliedBalance());
        $this->assertEquals('-$5.00', $invoice->appliedBalance());
        $this->assertEquals(-500, $invoice->rawAppliedBalance());
    }

    public function test_it_can_determine_if_it_has_a_discount_applied()
    {
        $discountAmount = new stdClass();
        $discountAmount->amount = 50;
        $discountAmount->discount = $discount = new StripeDiscount('foo');

        $otherDiscountAmount = new stdClass();
        $otherDiscountAmount->amount = 100;
        $otherDiscountAmount->discount = $otherDiscount = new StripeDiscount('bar');

        $stripeInvoice = new StripeInvoice();
        $stripeInvoice->total_discount_amounts = [$discountAmount, $otherDiscountAmount];
        $stripeInvoice->customer = 'foo';
        $stripeInvoice->discounts = [$discount, $otherDiscount];

        $user = new User();
        $user->stripe_id = 'foo';

        $invoice = new Invoice($user, $stripeInvoice);

        $this->assertTrue($invoice->hasDiscount());
        $this->assertSame(150, $invoice->rawDiscount());
        $this->assertSame(50, $invoice->rawDiscountFor(new Discount($discount)));
        $this->assertNull($invoice->rawDiscountFor(new Discount(new StripeDiscount('baz'))));
    }

    public function test_it_can_return_its_tax()
    {
        $stripeInvoice = new StripeInvoice();
        $stripeInvoice->customer = 'foo';
        $stripeInvoice->tax = 50;
        $stripeInvoice->currency = 'USD';

        $user = new User();
        $user->stripe_id = 'foo';

        $invoice = new Invoice($user, $stripeInvoice);

        $tax = $invoice->tax();

        $this->assertEquals('$0.50', $tax);

        // No tax...
        $stripeInvoice = new StripeInvoice();
        $stripeInvoice->customer = 'foo';
        $stripeInvoice->tax = null;
        $stripeInvoice->currency = 'USD';

        $user = new User();
        $user->stripe_id = 'foo';

        $invoice = new Invoice($user, $stripeInvoice);

        $tax = $invoice->tax();

        $this->assertEquals('$0.00', $tax);
    }

    public function test_it_can_determine_if_the_customer_was_exempt_from_taxes()
    {
        $stripeInvoice = new StripeInvoice();
        $stripeInvoice->customer = 'foo';
        $stripeInvoice->customer_tax_exempt = StripeCustomer::TAX_EXEMPT_EXEMPT;

        $user = new User();
        $user->stripe_id = 'foo';

        $invoice = new Invoice($user, $stripeInvoice);

        $this->assertTrue($invoice->isTaxExempt());
    }

    public function test_it_can_determine_if_reverse_charge_applies()
    {
        $stripeInvoice = new StripeInvoice();
        $stripeInvoice->customer = 'foo';
        $stripeInvoice->customer_tax_exempt = StripeCustomer::TAX_EXEMPT_REVERSE;

        $user = new User();
        $user->stripe_id = 'foo';

        $invoice = new Invoice($user, $stripeInvoice);

        $this->assertTrue($invoice->reverseChargeApplies());
    }
}

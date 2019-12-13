<?php

declare(strict_types=1);

namespace Laravel\Cashier\Tests\Unit;

use Laravel\Cashier\Invoice;
use Laravel\Cashier\InvoiceLineItem;
use Laravel\Cashier\Tests\Fixtures\User;
use PHPUnit\Framework\TestCase;
use Stripe\Customer as StripeCustomer;
use Stripe\Invoice as StripeInvoice;
use Stripe\InvoiceLineItem as StripeInvoiceLineItem;
use Stripe\TaxRate as StripeTaxRate;

class InvoiceLineItemTest extends TestCase
{
    public function test_we_can_calculate_the_inclusive_tax_percentage()
    {
        $customer = new User();
        $customer->stripe_id = 'foo';

        $stripeInvoice = new StripeInvoice();
        $stripeInvoice->customer_tax_exempt = StripeCustomer::TAX_EXEMPT_NONE;
        $stripeInvoice->customer = 'foo';

        $invoice = new Invoice($customer, $stripeInvoice);

        $stripeInvoiceLineItem = new StripeInvoiceLineItem();
        $stripeInvoiceLineItem->tax_amounts = [
            ['inclusive' => true, 'tax_rate' => $this->inclusiveTaxRate(5.0)],
            ['inclusive' => true, 'tax_rate' => $this->inclusiveTaxRate(15.0)],
            ['inclusive' => false, 'tax_rate' => $this->exclusiveTaxRate(21.0)],
        ];

        $item = new InvoiceLineItem($invoice, $stripeInvoiceLineItem);

        $result = $item->inclusiveTaxPercentage();

        $this->assertSame(20, $result);
    }

    public function test_we_can_calculate_the_exclusive_tax_percentage()
    {
        $customer = new User();
        $customer->stripe_id = 'foo';

        $stripeInvoice = new StripeInvoice();
        $stripeInvoice->customer_tax_exempt = StripeCustomer::TAX_EXEMPT_NONE;
        $stripeInvoice->customer = 'foo';

        $invoice = new Invoice($customer, $stripeInvoice);

        $stripeInvoiceLineItem = new StripeInvoiceLineItem();
        $stripeInvoiceLineItem->tax_amounts = [
            ['inclusive' => true, 'tax_rate' => $this->inclusiveTaxRate(5.0)],
            ['inclusive' => false, 'tax_rate' => $this->exclusiveTaxRate(15.0)],
            ['inclusive' => false, 'tax_rate' => $this->exclusiveTaxRate(21.0)],
        ];

        $item = new InvoiceLineItem($invoice, $stripeInvoiceLineItem);

        $result = $item->exclusiveTaxPercentage();

        $this->assertSame(36, $result);
    }

    /**
     * Get a test inclusive Tax Rate.
     *
     * @param  float  $percentage
     * @return \Stripe\TaxRate
     */
    protected function inclusiveTaxRate($percentage)
    {
        return $this->taxRate($percentage);
    }

    /**
     * Get a test exclusive Tax Rate.
     *
     * @param  float  $percentage
     * @return \Stripe\TaxRate
     */
    protected function exclusiveTaxRate($percentage)
    {
        return $this->taxRate($percentage, false);
    }

    /**
     * Get a test exclusive Tax Rate.
     *
     * @param  float  $percentage
     * @param  bool  $inclusive
     * @return \Stripe\TaxRate
     */
    protected function taxRate($percentage, $inclusive = true)
    {
        $inclusiveTaxRate = new StripeTaxRate;
        $inclusiveTaxRate->inclusive = $inclusive;
        $inclusiveTaxRate->percentage = $percentage;

        return $inclusiveTaxRate;
    }
}

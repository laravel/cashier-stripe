<?php

declare(strict_types=1);

namespace Laravel\Cashier\Tests\Unit;

use App\Models\User;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Invoice;
use Laravel\Cashier\InvoiceLineItem;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Stripe\Customer as StripeCustomer;
use Stripe\Invoice as StripeInvoice;
use Stripe\InvoiceLineItem as StripeInvoiceLineItem;
use Stripe\TaxRate as StripeTaxRate;

class InvoiceLineItemTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cashier::formatCurrencyUsing(fn ($amount) => '$'.number_format($amount / 100, 2));
    }

    protected function tearDown(): void
    {
        $property = new ReflectionProperty(Cashier::class, 'formatCurrencyUsing');
        $property->setValue(null, null);

        parent::tearDown();
    }

    public function test_we_can_calculate_the_inclusive_tax_percentage()
    {
        $customer = new User();
        $customer->stripe_id = 'foo';

        $stripeInvoice = new StripeInvoice();
        $stripeInvoice->customer_tax_exempt = StripeCustomer::TAX_EXEMPT_NONE;
        $stripeInvoice->customer = 'foo';

        $invoice = new Invoice($customer, $stripeInvoice);

        $stripeInvoiceLineItem = new StripeInvoiceLineItem();
        $stripeInvoiceLineItem->taxes = [
            $this->createTaxObject(100, $this->inclusiveTaxRate(5.24)),
            $this->createTaxObject(200, $this->inclusiveTaxRate(15.92)),
            $this->createTaxObject(300, $this->exclusiveTaxRate(21.12)),
        ];

        $item = new InvoiceLineItem($invoice, $stripeInvoiceLineItem);

        $result = $item->inclusiveTaxPercentage();

        $this->assertSame(21.16, $result);
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
        $stripeInvoiceLineItem->taxes = [
            $this->createTaxObject(100, $this->inclusiveTaxRate(5.54)),
            $this->createTaxObject(200, $this->exclusiveTaxRate(15.28)),
            $this->createTaxObject(300, $this->exclusiveTaxRate(21.85)),
        ];

        $item = new InvoiceLineItem($invoice, $stripeInvoiceLineItem);

        $result = $item->exclusiveTaxPercentage();

        $this->assertSame(37.13, $result);
    }

    public function test_unit_amount_excluding_tax_with_no_taxes()
    {
        $customer = new User();
        $customer->stripe_id = 'foo';

        $stripeInvoice = new StripeInvoice();
        $stripeInvoice->customer = 'foo';

        $invoice = new Invoice($customer, $stripeInvoice);

        $stripeInvoiceLineItem = StripeInvoiceLineItem::constructFrom([
            'currency' => 'usd',
            'quantity' => 1,
            'taxes' => [],
            'pricing' => [
                'type' => 'price_details',
                'unit_amount_decimal' => '1000',
            ],
        ]);

        $item = new InvoiceLineItem($invoice, $stripeInvoiceLineItem);

        $this->assertSame('$10.00', $item->unitAmountExcludingTax());
    }

    public function test_unit_amount_excluding_tax_with_inclusive_tax()
    {
        $customer = new User();
        $customer->stripe_id = 'foo';

        $stripeInvoice = new StripeInvoice();
        $stripeInvoice->customer = 'foo';

        $invoice = new Invoice($customer, $stripeInvoice);

        $stripeInvoiceLineItem = StripeInvoiceLineItem::constructFrom([
            'currency' => 'usd',
            'quantity' => 1,
            'taxes' => [
                [
                    'type' => 'tax_rate_details',
                    'amount' => 9,
                    'tax_behavior' => 'inclusive',
                    'taxable_amount' => 91,
                    'tax_rate_details' => ['tax_rate' => 'txr_test'],
                ],
            ],
        ]);

        $item = new InvoiceLineItem($invoice, $stripeInvoiceLineItem);

        $this->assertSame('$0.91', $item->unitAmountExcludingTax());
    }

    public function test_unit_amount_excluding_tax_with_exclusive_tax()
    {
        $customer = new User();
        $customer->stripe_id = 'foo';

        $stripeInvoice = new StripeInvoice();
        $stripeInvoice->customer = 'foo';

        $invoice = new Invoice($customer, $stripeInvoice);

        $stripeInvoiceLineItem = StripeInvoiceLineItem::constructFrom([
            'currency' => 'usd',
            'quantity' => 1,
            'taxes' => [
                [
                    'type' => 'tax_rate_details',
                    'amount' => 200,
                    'tax_behavior' => 'exclusive',
                    'taxable_amount' => 1000,
                    'tax_rate_details' => ['tax_rate' => 'txr_test'],
                ],
            ],
            'pricing' => [
                'type' => 'price_details',
                'unit_amount_decimal' => '1000',
            ],
        ]);

        $item = new InvoiceLineItem($invoice, $stripeInvoiceLineItem);

        $this->assertSame('$10.00', $item->unitAmountExcludingTax());
    }

    public function test_unit_amount_excluding_tax_divides_by_quantity_for_inclusive_tax()
    {
        $customer = new User();
        $customer->stripe_id = 'foo';

        $stripeInvoice = new StripeInvoice();
        $stripeInvoice->customer = 'foo';

        $invoice = new Invoice($customer, $stripeInvoice);

        $stripeInvoiceLineItem = StripeInvoiceLineItem::constructFrom([
            'currency' => 'usd',
            'quantity' => 2,
            'taxes' => [
                [
                    'type' => 'tax_rate_details',
                    'amount' => 18,
                    'tax_behavior' => 'inclusive',
                    'taxable_amount' => 182,
                    'tax_rate_details' => ['tax_rate' => 'txr_test'],
                ],
            ],
        ]);

        $item = new InvoiceLineItem($invoice, $stripeInvoiceLineItem);

        $this->assertSame('$0.91', $item->unitAmountExcludingTax());
    }

    public function test_price_uses_expanded_object_when_available()
    {
        $customer = new User();
        $customer->stripe_id = 'foo';

        $stripeInvoice = new StripeInvoice();
        $stripeInvoice->customer = 'foo';

        $invoice = new Invoice($customer, $stripeInvoice);

        $expandedPrice = (object) ['id' => 'price_test'];

        $stripeInvoiceLineItem = new StripeInvoiceLineItem();
        $stripeInvoiceLineItem->price = $expandedPrice;

        $item = new InvoiceLineItem($invoice, $stripeInvoiceLineItem);

        $this->assertSame($expandedPrice, $item->price());
    }

    /**
     * Create a tax object in the new structure.
     *
     * @param  int  $amount
     * @param  \Stripe\TaxRate  $taxRate
     * @return object
     */
    protected function createTaxObject($amount, StripeTaxRate $taxRate)
    {
        return (object) [
            'type' => 'tax_rate_details',
            'amount' => $amount,
            'tax_rate_details' => (object) [
                'tax_rate' => $taxRate,
            ],
        ];
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
        return StripeTaxRate::constructFrom([
            'id' => 'txr_test_'.uniqid(),
            'inclusive' => $inclusive,
            'percentage' => $percentage,
            'display_name' => 'Test Tax',
            'object' => 'tax_rate',
        ]);
    }
}

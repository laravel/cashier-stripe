<?php

use Mockery as m;
use Laravel\Cashier\Invoice;

class InvoiceTest extends PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        m::close();
    }

    public function testGettingDollarTotalOfInvoice()
    {
        $invoice = new Invoice($billable = m::mock('Laravel\Cashier\Contracts\Billable'), (object) ['total' => 10000, 'currency' => 'usd']);
        $billable->shouldReceive('formatCurrency')->andReturn(100.00);
        $billable->shouldReceive('addCurrencySymbol')->andReturn('$100.00');
        $this->assertEquals('$100.00', $invoice->dollars());
    }

    public function testGettingSubtotal()
    {
        $invoice = new Invoice($billable = m::mock('Laravel\Cashier\Contracts\Billable'), (object) ['subtotal' => 10000]);
        $billable->shouldReceive('formatCurrency')->andReturn(100.00);
        $this->assertEquals(100.00, $invoice->subtotal());
    }

    public function testGettingLineItemsByType()
    {
        $invoice = new Invoice(m::mock('Laravel\Cashier\Contracts\Billable'), (object) [
            'lines' => (object) [
                'data' => [
                    (object) ['type' => 'foo', 'name' => 'taylor'],
                    (object) ['type' => 'foo', 'name' => 'dayle'],
                    (object) ['type' => 'bar', 'name' => 'sally'],
                ],
            ],
        ]);

        $lines = $invoice->lineItemsByType('foo');

        $this->assertEquals(2, count($lines));
        $this->assertInstanceOf('Laravel\Cashier\LineItem', $lines[0]);
        $this->assertInstanceOf('Laravel\Cashier\LineItem', $lines[1]);
        $this->assertEquals('taylor', $lines[0]->name);
        $this->assertEquals('dayle', $lines[1]->name);
    }

    public function testHasDiscountIndicatesIfDiscountWasApplied()
    {
        $invoice = new Invoice(m::mock('Laravel\Cashier\Contracts\Billable'), (object) ['total' => 10000, 'subtotal' => 20000, 'discount' => 100]);
        $this->assertTrue($invoice->hasDiscount());
    }

    public function testHasDiscountIndicatesIfTotalDiscountWasApplied()
    {
        $invoice = new Invoice(m::mock('Laravel\Cashier\Contracts\Billable'), (object) ['total' => 0, 'subtotal' => 20000, 'discount' => 100]);
        $this->assertTrue($invoice->hasDiscount());
    }

    public function testDiscountCanBeRetrieved()
    {
        $invoice = new Invoice($billable = m::mock('Laravel\Cashier\Contracts\Billable'), (object) ['total' => 10000, 'subtotal' => 20000, 'currency' => 'usd']);
        $billable->shouldReceive('addCurrencySymbol')->andReturn('$100');
        $billable->shouldReceive('getCurrencyLocale')->andReturn('en_US');
        $this->assertEquals(100.00, $invoice->discount());
        $this->assertEquals('$100', $invoice->discountCurrency());
    }

    public function testCouponCanBeRetrieved()
    {
        $invoice = new Invoice(m::mock('Laravel\Cashier\Contracts\Billable'), (object) ['discount' => (object) ['coupon' => (object) ['id' => 'coupon-code']]]);
        $this->assertEquals('coupon-code', $invoice->coupon());
    }

    public function testDownloadingInvoiceReturnsResponse()
    {
        $data = ['vendor' => 'Vendor', 'product' => 'Product'];
        $invoice = m::mock('Laravel\Cashier\Invoice[render,getPhantomProcess]', [m::mock('Laravel\Cashier\Contracts\Billable'), new StdClass]);
        $invoice->id = 'id';
        $invoice->date = time();
        $workPath = realpath(__DIR__.'/../src/Laravel/Cashier/work').'/'.md5('id').'.pdf';
        $invoice->shouldReceive('render')->once()->with($data)->andReturn('rendered');
        $invoice->setFiles($files = m::mock('Illuminate\Filesystem\Filesystem'));
        $files->shouldReceive('put')->once()->with($workPath, 'rendered');
        $invoice->shouldReceive('getPhantomProcess')->once()->andReturn($process = m::mock('StdClass'));
        $process->shouldReceive('setTimeout')->once()->andReturn($process);
        $process->shouldReceive('mustRun')->once();
        $files->shouldReceive('get')->once()->with($workPath)->andReturn('pdf-content');
        $files->shouldReceive('delete')->once()->with($workPath);

        $invoice->download($data, realpath(__DIR__.'/../src/Laravel/Cashier/work'));
    }

    public function testViewCanBeRendered()
    {
        @unlink(__DIR__.'/receipt.blade.php');

        $vendor = 'Taylor';
        $product = 'Laravel';
        $street = 'street';
        $location = 'location';
        $phone = 'phone';
        $url = 'url';
        $__stripeInvoice = (object) ['id' => 1, 'date' => $date = time()];

        $invoice = m::mock('Laravel\Cashier\Invoice', [m::mock('Laravel\Cashier\Contracts\Billable'), $__stripeInvoice]);
        $invoice->shouldReceive('date')->andReturn(Carbon\Carbon::createFromTimestamp($date));

        /*
         * Invoice items...
         */
        $invoice->shouldReceive('invoiceItems')->andReturn([
            $invoiceItem = m::mock('StdClass'),
        ]);
        $invoiceItem->description = 'foo';
        $invoiceItem->shouldReceive('total')->andReturn('total');
        $invoiceItem->shouldReceive('dollars')->andReturn('total');

        /*
         * Subscription...
         */
        $invoice->shouldReceive('subscriptions')->andReturn([
            $subscription = m::mock('StdClass'),
        ]);
        $subscription->quantity = 1;
        $subscription->shouldReceive('total');
        $subscription->shouldReceive('dollars')->andReturn('dollars');
        $subscription->shouldReceive('startDateString');
        $subscription->shouldReceive('endDateString');

        /*
         * Discounts...
         */
        $invoice->shouldReceive('hasDiscount')->andReturn(true);
        $invoice->shouldReceive('discountIsPercentage')->andReturn(true);
        $invoice->shouldReceive('coupon')->andReturn('coupon');
        $invoice->shouldReceive('percentOff')->andReturn('percent-off');
        $invoice->shouldReceive('discount')->andReturn('discount');

        /*
         * Final total...
         */
        $invoice->shouldReceive('total')->andReturn('total');
        $invoice->shouldReceive('dollars')->andReturn('total');

        /*
         * Billable instance...
         */
        $billable = m::mock('StdClass');
        $billable->shouldReceive('getBillableName')->andReturn('name');

        $compiler = new Illuminate\View\Compilers\BladeCompiler(new Illuminate\Filesystem\Filesystem, null);
        $compiled = $compiler->compileString(file_get_contents(__DIR__.'/../src/views/receipt.blade.php'));
        file_put_contents(__DIR__.'/receipt.blade.php', $compiled);

        ob_start();
        include __DIR__.'/receipt.blade.php';
        $contents = ob_get_clean();

        @unlink(__DIR__.'/receipt.blade.php');
    }
}

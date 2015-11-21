<?php

use Mockery as m;
use Illuminate\Support\Facades\Config;

class BillableTraitTest extends PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        m::close();
    }

    public function testFindInvoiceOrFailReturnsInvoice()
    {
        $billable = m::mock('BillableTraitTestStub[findInvoice]');
        $billable->shouldReceive('findInvoice')->once()->with('id')->andReturn('foo');

        $this->assertEquals('foo', $billable->findInvoiceOrFail('id'));
    }

    /**
     * @expectedException Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function testFindInvoiceOrFailsThrowsExceptionWhenNotFound()
    {
        $billable = m::mock('BillableTraitTestStub[findInvoice]');
        $billable->shouldReceive('findInvoice')->once()->with('id')->andReturn(null);

        $billable->findInvoiceOrFail('id');
    }

    public function testDownloadCallsDownloadOnInvoice()
    {
        $billable = m::mock('BillableTraitTestStub[findInvoice]');
        $billable->shouldReceive('findInvoice')->once()->with('id')->andReturn($invoice = m::mock('StdClass'));
        $invoice->shouldReceive('download')->once()->with(['foo'], null);

        $billable->downloadInvoice('id', ['foo']);
    }

    public function testDownloadWithStoragePathCallsDownloadOnInvoice()
    {
        $billable = m::mock('BillableTraitTestStub[findInvoice]');
        $billable->shouldReceive('findInvoice')->once()->with('id')->andReturn($invoice = m::mock('StdClass'));
        $invoice->shouldReceive('download')->once()->with(['foo'], 'storagePath');

        $billable->downloadInvoice('id', ['foo'], 'storagePath');
    }

    public function testOnTrialMethodReturnsTrueIfTrialDateGreaterThanCurrentDate()
    {
        $billable = m::mock('BillableTraitTestStub[getTrialEndDate]');
        $billable->shouldReceive('getTrialEndDate')->andReturn(Carbon\Carbon::now()->addDays(5));

        $this->assertTrue($billable->onTrial());
    }

    public function testOnTrialMethodReturnsFalseIfTrialDateLessThanCurrentDate()
    {
        $billable = m::mock('BillableTraitTestStub[getTrialEndDate]');
        $billable->shouldReceive('getTrialEndDate')->andReturn(Carbon\Carbon::now()->subDays(5));

        $this->assertFalse($billable->onTrial());
    }

    public function testSubscribedChecksStripeIsActiveIfCardRequiredUpFront()
    {
        $billable = new BillableTraitCardUpFrontTestStub;
        $billable->stripe_active = true;
        $billable->subscription_ends_at = null;
        $this->assertTrue($billable->subscribed());

        $billable = new BillableTraitCardUpFrontTestStub;
        $billable->stripe_active = false;
        $billable->subscription_ends_at = null;
        $this->assertFalse($billable->subscribed());

        $billable = new BillableTraitCardUpFrontTestStub;
        $billable->stripe_active = false;
        $billable->subscription_ends_at = Carbon\Carbon::now()->addDays(5);
        $this->assertTrue($billable->subscribed());

        $billable = new BillableTraitCardUpFrontTestStub;
        $billable->stripe_active = false;
        $billable->subscription_ends_at = Carbon\Carbon::now()->subDays(5);
        $this->assertFalse($billable->subscribed());
    }

    public function testSubscribedHandlesNoCardUpFront()
    {
        $billable = new BillableTraitTestStub;
        $billable->trial_ends_at = null;
        $billable->stripe_active = null;
        $billable->subscription_ends_at = null;
        $this->assertFalse($billable->subscribed());

        $billable = new BillableTraitTestStub;
        $billable->stripe_active = 0;
        $billable->trial_ends_at = Carbon\Carbon::now()->addDays(5);
        $this->assertTrue($billable->subscribed());

        $billable = new BillableTraitTestStub;
        $billable->stripe_active = true;
        $billable->trial_ends_at = Carbon\Carbon::now()->subDays(5);
        $this->assertTrue($billable->subscribed());

        $billable = new BillableTraitTestStub;
        $billable->stripe_active = false;
        $billable->trial_ends_at = Carbon\Carbon::now()->subDays(5);
        $billable->subscription_ends_at = null;
        $this->assertFalse($billable->subscribed());

        $billable = new BillableTraitTestStub;
        $billable->trial_ends_at = null;
        $billable->stripe_active = null;
        $billable->subscription_ends_at = Carbon\Carbon::now()->addDays(5);
        $this->assertTrue($billable->subscribed());

        $billable = new BillableTraitTestStub;
        $billable->trial_ends_at = null;
        $billable->stripe_active = null;
        $billable->subscription_ends_at = Carbon\Carbon::now()->subDays(5);
        $this->assertFalse($billable->subscribed());
    }

    public function testReadyForBillingChecksStripeReadiness()
    {
        $billable = new BillableTraitTestStub;
        $billable->stripe_id = null;
        $this->assertFalse($billable->readyForBilling());

        $billable = new BillableTraitTestStub;
        $billable->stripe_id = 1;
        $this->assertTrue($billable->readyForBilling());
    }

    public function testTaxPercentZeroByDefault()
    {
        $billable = new BillableTraitTestStub;
        $taxPercent = $billable->getTaxPercent();
        $this->assertEquals(0, $taxPercent);
    }

    public function testTaxPercentCanBeOverridden()
    {
        $billable = new BillableTraitTaxTestStub;
        $taxPercent = $billable->getTaxPercent();
        $this->assertEquals(20, $taxPercent);
    }

    public function testGettingStripeKey()
    {
        Config::shouldReceive('get')->once()->with('services.stripe.secret')->andReturn('foo');
        $this->assertEquals('foo', BillableTraitTestStub::getStripeKey());
    }
}

class BillableTraitTestStub implements Laravel\Cashier\Contracts\Billable
{
    use Laravel\Cashier\Billable;
    public $cardUpFront = false;

    public function save()
    {
    }
}

class BillableTraitTaxTestStub implements Laravel\Cashier\Contracts\Billable
{
    use Laravel\Cashier\Billable;
    public $cardUpFront = false;

    public function getTaxPercent()
    {
        return 20;
    }

    public function save()
    {
    }
}

class BillableTraitCardUpFrontTestStub implements Laravel\Cashier\Contracts\Billable
{
    use Laravel\Cashier\Billable;
    public $cardUpFront = true;

    public function save()
    {
    }
}

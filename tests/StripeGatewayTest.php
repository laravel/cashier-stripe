<?php

use Mockery as m;
use Laravel\Cashier\StripeGateway;

class StripeGatewayTest extends PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        Mockery::close();
    }

    public function testCreatePassesProperOptionsToCustomer()
    {
        $billable = $this->mockBillableInterface();
        $billable->shouldReceive('getCurrency')->andReturn('gbp');
        $gateway = m::mock('Laravel\Cashier\StripeGateway[getStripeCustomer,createStripeCustomer,updateLocalStripeData]', [$billable, 'plan']);
        $gateway->shouldReceive('createStripeCustomer')->andReturn($customer = m::mock('StdClass'));
        $customer->shouldReceive('updateSubscription')->once()->with([
            'plan' => 'plan',
            'prorate' => true,
            'quantity' => 1,
            'tax_percent' => 20,
        ])->andReturn((object) ['id' => 'sub_id']);
        $customer->id = 'foo';
        $billable->shouldReceive('setStripeSubscription')->once()->with('sub_id');
        $gateway->shouldReceive('getStripeCustomer')->once()->with('foo');
        $gateway->shouldReceive('updateLocalStripeData')->once();

        $gateway->create('token', []);
    }

    public function testCreatePassesProperOptionsToCustomerForTrialEnd()
    {
        $billable = $this->mockBillableInterface();
        $billable->shouldReceive('getCurrency')->andReturn('usd');
        $gateway = m::mock('Laravel\Cashier\StripeGateway[getStripeCustomer,createStripeCustomer,updateLocalStripeData]', [$billable, 'plan']);
        $gateway->shouldReceive('createStripeCustomer')->andReturn($customer = m::mock('StdClass'));
        $customer->shouldReceive('updateSubscription')->once()->with([
            'plan' => 'plan',
            'prorate' => true,
            'quantity' => 1,
            'trial_end' => 'now',
            'tax_percent' => 20,
        ])->andReturn((object) ['id' => 'sub_id']);
        $customer->id = 'foo';
        $billable->shouldReceive('setStripeSubscription')->once()->with('sub_id');
        $gateway->shouldReceive('getStripeCustomer')->once()->with('foo');
        $gateway->shouldReceive('updateLocalStripeData')->once();

        $gateway->skipTrial();
        $gateway->create('token', []);
    }

    public function testCreateUtilizesGivenCustomerIfApplicable()
    {
        $billable = $this->mockBillableInterface();
        $billable->shouldReceive('getCurrency')->andReturn('usd');
        $gateway = m::mock('Laravel\Cashier\StripeGateway[getStripeCustomer,createStripeCustomer,updateLocalStripeData,updateCard]', [$billable, 'plan']);
        $gateway->shouldReceive('createStripeCustomer')->never();
        $customer = m::mock('StdClass');
        $customer->shouldReceive('updateSubscription')->once()->andReturn($sub = (object) ['id' => 'sub_id']);
        $billable->shouldReceive('setStripeSubscription')->with('sub_id');
        $customer->id = 'foo';
        $gateway->shouldReceive('getStripeCustomer')->once()->with('foo');
        $gateway->shouldReceive('updateCard')->once();
        $gateway->shouldReceive('updateLocalStripeData')->once();

        $gateway->create('token', [], $customer);
    }

    public function testSwapCallsCreateWithProperArguments()
    {
        $billable = $this->mockBillableInterface();
        $gateway = m::mock('Laravel\Cashier\StripeGateway[create,getStripeCustomer,maintainTrial]', [$billable, 'plan']);
        $gateway->shouldReceive('getStripeCustomer')->once()->andReturn($customer = m::mock('StdClass'));
        $gateway->shouldReceive('maintainTrial')->once();
        $gateway->shouldReceive('create')->once()->with(null, null, $customer);

        $gateway->swap();
    }

    public function testUpdateQuantity()
    {
        $customer = m::mock('StdClass');
        $customer->subscription = (object) ['plan' => (object) ['id' => 1]];
        $customer->shouldReceive('updateSubscription')->once()->with([
            'plan' => 1,
            'quantity' => 5,
        ]);

        $gateway = new StripeGateway($this->mockBillableInterface(), 'plan');
        $gateway->updateQuantity(5, $customer);
    }

    public function testUpdateQuantityWithTrialEnd()
    {
        $customer = m::mock('StdClass');
        $customer->subscription = (object) ['plan' => (object) ['id' => 1]];
        $customer->shouldReceive('updateSubscription')->once()->with([
            'plan' => 1,
            'quantity' => 5,
            'trial_end' => 'now',
        ]);

        $gateway = new StripeGateway($this->mockBillableInterface(), 'plan');
        $gateway->skipTrial();
        $gateway->updateQuantity(5, $customer);
    }

    public function testUpdateQuantityAndForceTrialEnd()
    {
        $customer = m::mock('StdClass');
        $customer->subscription = (object) ['plan' => (object) ['id' => 1]];
        $customer->shouldReceive('updateSubscription')->once()->with([
            'plan' => 1,
            'quantity' => 5,
            'trial_end' => 'now',
        ]);

        $gateway = new StripeGateway($this->mockBillableInterface(), 'plan');
        $gateway->skipTrial();
        $gateway->updateQuantity(5, $customer);
    }

    public function testCancellingOfSubscriptions()
    {
        $billable = $this->mockBillableInterface();
        $gateway = m::mock('Laravel\Cashier\StripeGateway[getStripeCustomer]', [$billable, 'plan']);
        $gateway->shouldReceive('getStripeCustomer')->andReturn($customer = m::mock('StdClass'));
        $customer->subscription = (object) ['current_period_end' => $time = time(), 'trial_end' => null];
        $billable->shouldReceive('setSubscriptionEndDate')->once()->with(m::type('Carbon\Carbon'))->andReturnUsing(function ($value) use ($time) {
            $this->assertEquals($time, $value->getTimestamp());

            return $value;
        });
        $customer->shouldReceive('cancelSubscription')->once();
        $billable->shouldReceive('setStripeIsActive')->once()->with(false)->andReturn($billable);
        $billable->shouldReceive('saveBillableInstance')->once();

        $gateway->cancel();
    }

    public function testCancellingOfSubscriptionsWithTrials()
    {
        $billable = $this->mockBillableInterface();
        $gateway = m::mock('Laravel\Cashier\StripeGateway[getStripeCustomer]', [$billable, 'plan']);
        $gateway->shouldReceive('getStripeCustomer')->andReturn($customer = m::mock('StdClass'));
        $customer->subscription = (object) ['current_period_end' => $time = time(), 'trial_end' => $trialTime = time() + 50];
        $billable->shouldReceive('setSubscriptionEndDate')->once()->with(m::type('Carbon\Carbon'))->andReturnUsing(function ($value) use ($trialTime) {
            $this->assertEquals($trialTime, $value->getTimestamp());

            return $value;
        });
        $customer->shouldReceive('cancelSubscription')->once();
        $billable->shouldReceive('setStripeIsActive')->once()->with(false)->andReturn($billable);
        $billable->shouldReceive('saveBillableInstance')->once();

        $gateway->cancel();
    }

    public function testUpdatingCreditCardData()
    {
        $billable = $this->mockBillableInterface();
        $gateway = m::mock('Laravel\Cashier\StripeGateway[getStripeCustomer,getLastFourCardDigits]', [$billable, 'plan']);
        $gateway->shouldAllowMockingProtectedMethods();
        $gateway->shouldReceive('getStripeCustomer')->andReturn($customer = m::mock('StdClass'));
        $gateway->shouldReceive('getLastFourCardDigits')->once()->andReturn('1111');
        $customer->subscription = (object) ['plan' => (object) ['id' => 1]];
        $customer->sources = m::mock('StdClass');
        $customer->sources->shouldReceive('create')->once()->with(['source' => 'token'])->andReturn($card = m::mock('StdClass'));
        $card->id = 'card_id';
        $customer->shouldReceive('save')->once();

        $billable->shouldReceive('setLastFourCardDigits')->once()->with('1111')->andReturn($billable);
        $billable->shouldReceive('saveBillableInstance')->once();

        $gateway->updateCard('token');
    }

    public function testApplyingCoupon()
    {
        $billable = $this->mockBillableInterface();
        $gateway = m::mock('Laravel\Cashier\StripeGateway[getStripeCustomer]', [$billable, 'plan']);
        $gateway->shouldReceive('getStripeCustomer')->andReturn($customer = m::mock('StdClass'));
        $customer->shouldReceive('save')->once();

        $gateway->applyCoupon('coupon-code');
        $this->assertEquals('coupon-code', $customer->coupon);
    }

    public function testRetrievingACustomersStripePlanId()
    {
        $billable = $this->mockBillableInterface();
        $gateway = m::mock('Laravel\Cashier\StripeGateway[getStripeCustomer]', [$billable, 'plan']);
        $gateway->shouldReceive('getStripeCustomer')->andReturn($customer = m::mock('StdClass'));
        $customer->subscription = (object) ['plan' => (object) ['id' => 1]];

        $this->assertEquals(1, $gateway->planId());
    }

    public function testUpdatingLocalStripeData()
    {
        $billable = $this->mockBillableInterface();
        $gateway = new StripeGateway($billable, 'plan');
        $billable->shouldReceive('setStripeId')->once()->with('id')->andReturn($billable);
        $billable->shouldReceive('setStripePlan')->once()->with('plan')->andReturn($billable);
        $billable->shouldReceive('setLastFourCardDigits')->once()->with('last-four')->andReturn($billable);
        $billable->shouldReceive('setStripeIsActive')->once()->with(true)->andReturn($billable);
        $billable->shouldReceive('setSubscriptionEndDate')->once()->with(null)->andReturn($billable);
        $billable->shouldReceive('saveBillableInstance')->once()->andReturn($billable);
        $customer = m::mock('StdClass');
        $customer->sources = m::mock('StdClass');
        $customer->id = 'id';
        $customer->shouldReceive('getSubscriptionId')->andReturn('sub_id');
        $customer->default_source = 'default-card';
        $customer->sources->shouldReceive('retrieve')->once()->with('default-card')->andReturn((object) ['last4' => 'last-four']);

        $gateway->updateLocalStripeData($customer);
    }

    public function testMaintainTrialSetsTrialToHoursLeftOnCurrentTrial()
    {
        $billable = $this->mockBillableInterface();
        $billable->shouldReceive('readyForBilling')->once()->andReturn(true);
        $gateway = m::mock('Laravel\Cashier\StripeGateway[getStripeCustomer,getTrialEndForCustomer]', [$billable, 'plan']);
        $gateway->shouldReceive('getStripeCustomer')->once()->andReturn($customer = m::mock('StdClass'));
        $gateway->shouldReceive('getTrialEndForCustomer')->once()->with($customer)->andReturn(Carbon\Carbon::now()->addHours(2));
        $gateway->maintainTrial();

        $this->assertEquals(2, Carbon\Carbon::now()->diffInHours($gateway->getTrialFor()));
    }

    public function testMaintainTrialDoesNothingIfNotOnTrial()
    {
        $billable = $this->mockBillableInterface();
        $billable->shouldReceive('readyForBilling')->once()->andReturn(true);
        $gateway = m::mock('Laravel\Cashier\StripeGateway[getStripeCustomer,getTrialEndForCustomer]', [$billable, 'plan']);
        $gateway->shouldReceive('getStripeCustomer')->once()->andReturn($customer = m::mock('StdClass'));
        $gateway->shouldReceive('getTrialEndForCustomer')->once()->with($customer)->andReturn(null);
        $gateway->maintainTrial();

        $this->assertNull($gateway->getTrialFor());
    }

    public function testGettingTheTrialEndDateForACustomer()
    {
        $time = time();
        $customer = (object) ['subscription' => (object) ['trial_end' => $time]];
        $gateway = new StripeGateway($this->mockBillableInterface(), 'plan');

        $this->assertInstanceOf('Carbon\Carbon', $gateway->getTrialEndForCustomer($customer));
        $this->assertEquals($time, $gateway->getTrialEndForCustomer($customer)->getTimestamp());
    }

    public function testCreateWithBillingCycleAnchorSetToNow()
    {
        $billable = $this->mockBillableInterface();
        $billable->shouldReceive('getCurrency')->andReturn('usd');

        $gateway = m::mock('Laravel\Cashier\StripeGateway[getStripeCustomer,createStripeCustomer,updateLocalStripeData]', [$billable, 'plan']);
        $gateway->shouldReceive('createStripeCustomer')->andReturn($customer = m::mock('StdClass'));
        $customer->shouldReceive('updateSubscription')->once()->with([
            'plan' => 'plan',
            'prorate' => true,
            'quantity' => 1,
            'tax_percent' => 20,
            'billing_cycle_anchor' => 'now',
        ])->andReturn((object) ['id' => 'sub_id']);
        $customer->id = 'foo';
        $billable->shouldReceive('setStripeSubscription')->once()->with('sub_id');
        $gateway->shouldReceive('getStripeCustomer')->once()->with('foo');
        $gateway->shouldReceive('updateLocalStripeData')->once();

        $gateway->anchorOn('now');
        $gateway->create('token', []);
    }

    public function testCreateWithBillingCycleAnchorSetToDate()
    {
        $billable = $this->mockBillableInterface();
        $billable->shouldReceive('getCurrency')->andReturn('usd');
        $twoWeeksFromNow = Carbon\Carbon::now()->addWeeks(2);
        $gateway = m::mock('Laravel\Cashier\StripeGateway[getStripeCustomer,createStripeCustomer,updateLocalStripeData]', [$billable, 'plan']);
        $gateway->shouldReceive('createStripeCustomer')->andReturn($customer = m::mock('StdClass'));
        $customer->shouldReceive('updateSubscription')->once()->with([
            'plan' => 'plan',
            'prorate' => true,
            'quantity' => 1,
            'tax_percent' => 20,
            'billing_cycle_anchor' => $twoWeeksFromNow->getTimestamp(),
        ])->andReturn((object) ['id' => 'sub_id']);
        $customer->id = 'foo';
        $billable->shouldReceive('setStripeSubscription')->once()->with('sub_id');
        $gateway->shouldReceive('getStripeCustomer')->once()->with('foo');
        $gateway->shouldReceive('updateLocalStripeData')->once();

        $gateway->anchorOn($twoWeeksFromNow);
        $gateway->create('token', []);
    }

    protected function mockBillableInterface()
    {
        $billable = m::mock('Laravel\Cashier\Contracts\Billable');
        $billable->shouldReceive('getStripeKey')->andReturn('key');
        $billable->shouldReceive('getTaxPercent')->andReturn(20);

        return $billable;
    }
}

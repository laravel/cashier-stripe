<?php

use Mockery as m;
use Laravel\Cashier\StripeGateway;
use Laravel\Cashier\BillableInterface;

class StripeGatewayTest extends PHPUnit_Framework_TestCase {

	public function tearDown()
	{
		Mockery::close();
	}


	public function testCreatePassesProperOptionsToCustomer()
	{
		$billable = m::mock('Laravel\Cashier\BillableInterface');
		$gateway = m::mock('Laravel\Cashier\StripeGateway[getStripeCustomer,createStripeCustomer,updateLocalStripeData]', array($billable, 'plan'));
		$gateway->shouldReceive('createStripeCustomer')->andReturn($customer = m::mock('StdClass'));
		$customer->shouldReceive('updateSubscription')->once()->with([
			'coupon' => null,
			'plan' => 'plan',
			'prorate' => true,
			'quantity' => 1,
			'trial_end' => null,
		]);
		$customer->id = 'foo';
		$gateway->shouldReceive('getStripeCustomer')->once()->with('foo');
		$gateway->shouldReceive('updateLocalStripeData')->once();

		$gateway->create('token', 'description');
	}


	public function testCreatePassesProperOptionsToCustomerForTrialEnd()
	{
		$billable = m::mock('Laravel\Cashier\BillableInterface');
		$gateway = m::mock('Laravel\Cashier\StripeGateway[getStripeCustomer,createStripeCustomer,updateLocalStripeData]', array($billable, 'plan'));
		$gateway->shouldReceive('createStripeCustomer')->andReturn($customer = m::mock('StdClass'));
		$customer->shouldReceive('updateSubscription')->once()->with([
			'coupon' => null,
			'plan' => 'plan',
			'prorate' => true,
			'quantity' => 1,
			'trial_end' => 'now',
		]);
		$customer->id = 'foo';
		$gateway->shouldReceive('getStripeCustomer')->once()->with('foo');
		$gateway->shouldReceive('updateLocalStripeData')->once();

		$gateway->skipTrial();
		$gateway->create('token', 'description');
	}


	public function testCreateUtilizesGivenCustomerIfApplicable()
	{
		$billable = m::mock('Laravel\Cashier\BillableInterface');
		$gateway = m::mock('Laravel\Cashier\StripeGateway[getStripeCustomer,createStripeCustomer,updateLocalStripeData]', array($billable, 'plan'));
		$gateway->shouldReceive('createStripeCustomer')->never();
		$customer = m::mock('StdClass');
		$customer->shouldReceive('updateSubscription')->once();
		$customer->id = 'foo';
		$gateway->shouldReceive('getStripeCustomer')->once()->with('foo');
		$gateway->shouldReceive('updateLocalStripeData')->once();

		$gateway->create('token', 'description', $customer);
	}


	public function testSwapCallsCreateWithProperArguments()
	{
		$billable = m::mock('Laravel\Cashier\BillableInterface');
		$gateway = m::mock('Laravel\Cashier\StripeGateway[create,getStripeCustomer,maintainTrial]', array($billable, 'plan'));
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
			'trial_end' => null,
		]);

		$gateway = new StripeGateway(m::mock('Laravel\Cashier\BillableInterface'), 'plan');
		$gateway->updateQuantity($customer, 5);
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

		$gateway = new StripeGateway(m::mock('Laravel\Cashier\BillableInterface'), 'plan');
		$gateway->skipTrial();
		$gateway->updateQuantity($customer, 5);
	}


	public function testCancellingOfSubscriptions()
	{
		$billable = m::mock('Laravel\Cashier\BillableInterface');
		$gateway = m::mock('Laravel\Cashier\StripeGateway[getStripeCustomer]', array($billable, 'plan'));
		$gateway->shouldReceive('getStripeCustomer')->andReturn($customer = m::mock('StdClass'));
		$customer->subscription = (object) ['current_period_end' => $time = time(), 'trial_end' => null];
		$billable->shouldReceive('setSubscriptionEndDate')->once()->with(m::type('Carbon\Carbon'))->andReturnUsing(function($value) use ($time)
		{
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
		$billable = m::mock('Laravel\Cashier\BillableInterface');
		$gateway = m::mock('Laravel\Cashier\StripeGateway[getStripeCustomer]', array($billable, 'plan'));
		$gateway->shouldReceive('getStripeCustomer')->andReturn($customer = m::mock('StdClass'));
		$customer->subscription = (object) ['current_period_end' => $time = time(), 'trial_end' => $trialTime = time() + 50];
		$billable->shouldReceive('setSubscriptionEndDate')->once()->with(m::type('Carbon\Carbon'))->andReturnUsing(function($value) use ($trialTime)
		{
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
		$billable = m::mock('Laravel\Cashier\BillableInterface');
		$gateway = m::mock('Laravel\Cashier\StripeGateway[getStripeCustomer,updateLocalStripeData]', array($billable, 'plan'));
		$gateway->shouldReceive('getStripeCustomer')->twice()->andReturn($customer = m::mock('StdClass'));
		$customer->subscription = (object) ['plan' => (object) ['id' => 1]];
		$customer->shouldReceive('updateSubscription')->once()->with([
			'plan' => 1,
			'card' => 'token',
		]);
		$gateway->shouldReceive('updateLocalStripeData')->once()->with($customer, 1);

		$gateway->updateCard('token');
	}


	public function testApplyingCoupon()
	{
		$billable = m::mock('Laravel\Cashier\BillableInterface');
		$gateway = m::mock('Laravel\Cashier\StripeGateway[getStripeCustomer]', array($billable, 'plan'));
		$gateway->shouldReceive('getStripeCustomer')->andReturn($customer = m::mock('StdClass'));
		$customer->shouldReceive('save')->once();

		$gateway->applyCoupon('coupon-code');
		$this->assertEquals('coupon-code', $customer->coupon);
	}


	public function testRetrievingACustomersStripePlanId()
	{
		$billable = m::mock('Laravel\Cashier\BillableInterface');
		$gateway = m::mock('Laravel\Cashier\StripeGateway[getStripeCustomer]', array($billable, 'plan'));
		$gateway->shouldReceive('getStripeCustomer')->andReturn($customer = m::mock('StdClass'));
		$customer->subscription = (object) ['plan' => (object) ['id' => 1]];

		$this->assertEquals(1, $gateway->planId());
	}


	public function testUpdatingLocalStripeData()
	{
		$billable = m::mock('Laravel\Cashier\BillableInterface');
		$gateway = new StripeGateway($billable, 'plan');
		$billable->shouldReceive('setStripeId')->once()->with('id')->andReturn($billable);
		$billable->shouldReceive('setStripePlan')->once()->with('plan')->andReturn($billable);
		$billable->shouldReceive('setLastFourCardDigits')->once()->with('last-four')->andReturn($billable);
		$billable->shouldReceive('setStripeIsActive')->once()->with(true)->andReturn($billable);
		$billable->shouldReceive('setSubscriptionEndDate')->once()->with(null)->andReturn($billable);
		$billable->shouldReceive('saveBillableInstance')->once()->andReturn($billable);
		$customer = m::mock('StdClass');
		$customer->cards = m::mock('StdClass');
		$customer->id = 'id';
		$customer->default_card = 'default-card';
		$customer->cards->shouldReceive('retrieve')->once()->with('default-card')->andReturn((object) ['last4' => 'last-four']);

		$gateway->updateLocalStripeData($customer);
	}


	public function testMaintainTrialSetsTrialToHoursLeftOnCurrentTrial()
	{
		$billable = m::mock('Laravel\Cashier\BillableInterface');
		$billable->shouldReceive('readyForBilling')->once()->andReturn(true);
		$gateway = m::mock('Laravel\Cashier\StripeGateway[getStripeCustomer,getTrialEndForCustomer]', [$billable, 'plan']);
		$gateway->shouldReceive('getStripeCustomer')->once()->andReturn($customer = m::mock('StdClass'));
		$gateway->shouldReceive('getTrialEndForCustomer')->once()->with($customer)->andReturn(Carbon\Carbon::now()->addHours(2));
		$gateway->maintainTrial();

		$this->assertEquals(2, Carbon\Carbon::now()->diffInHours($gateway->getTrialFor()));
	}


	public function testMaintainTrialDoesNothingIfNotOnTrial()
	{
		$billable = m::mock('Laravel\Cashier\BillableInterface');
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
		$gateway = new StripeGateway(m::mock('Laravel\Cashier\BillableInterface'), 'plan');

		$this->assertInstanceOf('Carbon\Carbon', $gateway->getTrialEndForCustomer($customer));
		$this->assertEquals($time, $gateway->getTrialEndForCustomer($customer)->getTimestamp());
	}

}
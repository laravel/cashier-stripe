<?php

use Illuminate\Support\Facades\Request;

class WebhookControllerTest extends PHPUnit_Framework_TestCase {

	public function setUp()
	{
		Illuminate\Support\Facades\Facade::clearResolvedInstances();
	}


	public function testProperMethodsAreCalledBasedOnStripeEvent()
	{
		$_SERVER['__received'] = false;
		Request::shouldReceive('getContent')->andReturn(json_encode(['type' => 'charge.succeeded']));
		$controller = new WebhookControllerTestStub;
		$controller->handleWebhook();

		$this->assertTrue($_SERVER['__received']);
	}


	/**
	 * @expectedException Symfony\Component\HttpKernel\Exception\NotFoundHttpException
	 */
	public function testNotFoundExceptionsAreThrownWhenMethodDoesntExists()
	{
		Request::shouldReceive('getContent')->andReturn(json_encode(['type' => 'foo.bar']));
		$controller = new WebhookControllerTestStub;
		$controller->handleWebhook();
	}

}

class WebhookControllerTestStub extends Laravel\Cashier\WebhookController {
	public function handleChargeSucceeded()
	{
		$_SERVER['__received'] = true;
	}
}
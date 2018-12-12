<?php

namespace Laravel\Cashier\Tests;

use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Laravel\Cashier\Http\Controllers\WebhookController;

class WebhookControllerTest extends TestCase
{
    public function testProperMethodsAreCalledBasedOnStripeEvent()
    {
        $_SERVER['__received'] = false;
        $request = Request::create('/', 'POST', [], [], [], [], json_encode(['type' => 'charge.succeeded', 'id' => 'event-id']));
        $controller = new WebhookControllerTestStub;
        $controller->handleWebhook($request);

        $this->assertTrue($_SERVER['__received']);
    }

    public function testNormalResponseIsReturnedIfMethodIsMissing()
    {
        $request = Request::create('/', 'POST', [], [], [], [], json_encode(['type' => 'foo.bar', 'id' => 'event-id']));
        $controller = new WebhookControllerTestStub;
        $response = $controller->handleWebhook($request);
        $this->assertEquals(200, $response->getStatusCode());
    }
}

class WebhookControllerTestStub extends WebhookController
{
    public function __construct()
    {
        // Prevent setting middleware...
    }

    public function handleChargeSucceeded()
    {
        $_SERVER['__received'] = true;
    }
}

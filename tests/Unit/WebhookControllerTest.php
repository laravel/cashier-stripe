<?php

namespace Laravel\Cashier\Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Laravel\Cashier\Events\WebhookHandled;
use Laravel\Cashier\Events\WebhookReceived;
use Laravel\Cashier\Http\Controllers\WebhookController;
use Laravel\Cashier\Tests\TestCase;
use Symfony\Component\HttpFoundation\Response;

class WebhookControllerTest extends TestCase
{
    public function test_proper_methods_are_called_based_on_stripe_event()
    {
        $request = $this->simpleRequest('charge.succeeded');

        Event::fake([
            WebhookHandled::class,
            WebhookReceived::class,
        ]);

        $response = (new WebhookControllerTestStub)->handleWebhook($request);

        Event::assertDispatched(WebhookReceived::class, function (WebhookReceived $event) use ($request) {
            return $request->getContent() == json_encode($event->payload);
        });

        Event::assertDispatched(WebhookHandled::class, function (WebhookHandled $event) use ($request) {
            return $request->getContent() == json_encode($event->payload);
        });

        $this->assertEquals('Webhook Handled', $response->getContent());
    }

    public function test_normal_response_is_returned_if_method_is_missing()
    {
        $request = $this->simpleRequest('foo.bar');

        Event::fake([
            WebhookHandled::class,
            WebhookReceived::class,
        ]);

        $response = (new WebhookControllerTestStub)->handleWebhook($request);

        Event::assertDispatched(WebhookReceived::class, function (WebhookReceived $event) use ($request) {
            return $request->getContent() == json_encode($event->payload);
        });

        Event::assertNotDispatched(WebhookHandled::class);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame('Missing event type: foo.bar', $response->getContent());
    }

    public function test_subscription_schedule_created_handler_is_callable()
    {
        $request = $this->request('subscription_schedule.created');

        Event::fake([WebhookHandled::class, WebhookReceived::class]);

        $response = (new WebhookControllerTestStub)->handleWebhook($request);

        // Without a matching customer, the handler should still return success
        Event::assertDispatched(WebhookHandled::class);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_subscription_schedule_updated_handler_is_callable()
    {
        $request = $this->request('subscription_schedule.updated');

        Event::fake([WebhookHandled::class, WebhookReceived::class]);

        $response = (new WebhookControllerTestStub)->handleWebhook($request);

        Event::assertDispatched(WebhookHandled::class);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_subscription_schedule_canceled_handler_is_callable()
    {
        $request = $this->request('subscription_schedule.canceled');

        Event::fake([WebhookHandled::class, WebhookReceived::class]);

        $response = (new WebhookControllerTestStub)->handleWebhook($request);

        Event::assertDispatched(WebhookHandled::class);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_subscription_schedule_completed_handler_is_callable()
    {
        $request = $this->request('subscription_schedule.completed');

        Event::fake([WebhookHandled::class, WebhookReceived::class]);

        $response = (new WebhookControllerTestStub)->handleWebhook($request);

        Event::assertDispatched(WebhookHandled::class);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_subscription_schedule_released_handler_is_callable()
    {
        $request = $this->request('subscription_schedule.released');

        Event::fake([WebhookHandled::class, WebhookReceived::class]);

        $response = (new WebhookControllerTestStub)->handleWebhook($request);

        Event::assertDispatched(WebhookHandled::class);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_quote_finalized_handler_is_callable()
    {
        $request = $this->request('quote.finalized');

        Event::fake([WebhookHandled::class, WebhookReceived::class]);

        $response = (new WebhookControllerTestStub)->handleWebhook($request);

        Event::assertDispatched(WebhookHandled::class);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_quote_accepted_handler_is_callable()
    {
        $request = $this->request('quote.accepted');

        Event::fake([WebhookHandled::class, WebhookReceived::class]);

        $response = (new WebhookControllerTestStub)->handleWebhook($request);

        Event::assertDispatched(WebhookHandled::class);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_quote_canceled_handler_is_callable()
    {
        $request = $this->request('quote.canceled');

        Event::fake([WebhookHandled::class, WebhookReceived::class]);

        $response = (new WebhookControllerTestStub)->handleWebhook($request);

        Event::assertDispatched(WebhookHandled::class);
        $this->assertEquals(200, $response->getStatusCode());
    }

    private function simpleRequest($event)
    {
        return Request::create(
            '/', 'POST', [], [], [], [], json_encode(['type' => $event, 'id' => 'event-id'])
        );
    }

    private function request($event)
    {
        return Request::create(
            '/', 'POST', [], [], [], [], json_encode([
                'type' => $event,
                'id' => 'event-id',
                'data' => [
                    'object' => [
                        'id' => 'sub_sched_test',
                        'customer' => 'cus_nonexistent',
                        'status' => 'not_started',
                        'subscription' => null,
                        'metadata' => [],
                        'current_phase' => null,
                        'canceled_at' => null,
                        'completed_at' => null,
                        'released_at' => null,
                    ],
                ],
            ])
        );
    }
}

class WebhookControllerTestStub extends WebhookController
{
    public function __construct()
    {
        // Don't call parent constructor to prevent setting middleware...
    }

    public function handleChargeSucceeded()
    {
        return new Response('Webhook Handled', 200);
    }

    protected function getUserByStripeId($stripeId)
    {
        // Return null so webhook handlers skip processing without database
        return null;
    }

    public function missingMethod($parameters = [])
    {
        return new Response('Missing event type: '.$parameters['type']);
    }
}

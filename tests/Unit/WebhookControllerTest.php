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
        $request = $this->request('charge.succeeded');

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
        $request = $this->request('foo.bar');

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

    public function test_quote_finalized_webhook_is_handled()
    {
        $request = $this->request('quote.finalized');

        Event::fake([
            WebhookHandled::class,
            WebhookReceived::class,
        ]);

        $response = (new WebhookControllerTestStub)->handleWebhook($request);

        Event::assertDispatched(WebhookReceived::class);
        Event::assertDispatched(WebhookHandled::class);

        $this->assertEquals('Quote Webhook Handled', $response->getContent());
    }

    public function test_quote_accepted_webhook_is_handled()
    {
        $request = $this->request('quote.accepted');

        Event::fake([
            WebhookHandled::class,
            WebhookReceived::class,
        ]);

        $response = (new WebhookControllerTestStub)->handleWebhook($request);

        Event::assertDispatched(WebhookReceived::class);
        Event::assertDispatched(WebhookHandled::class);

        $this->assertEquals('Quote Webhook Handled', $response->getContent());
    }

    public function test_quote_canceled_webhook_is_handled()
    {
        $request = $this->request('quote.canceled');

        Event::fake([
            WebhookHandled::class,
            WebhookReceived::class,
        ]);

        $response = (new WebhookControllerTestStub)->handleWebhook($request);

        Event::assertDispatched(WebhookReceived::class);
        Event::assertDispatched(WebhookHandled::class);

        $this->assertEquals('Quote Webhook Handled', $response->getContent());
    }

    public function test_subscription_schedule_created_webhook_is_handled()
    {
        $request = $this->request('subscription_schedule.created');

        Event::fake([
            WebhookHandled::class,
            WebhookReceived::class,
        ]);

        $response = (new WebhookControllerTestStub)->handleWebhook($request);

        Event::assertDispatched(WebhookReceived::class);
        Event::assertDispatched(WebhookHandled::class);

        $this->assertEquals('Subscription Schedule Webhook Handled', $response->getContent());
    }

    public function test_subscription_schedule_updated_webhook_is_handled()
    {
        $request = $this->request('subscription_schedule.updated');

        Event::fake([
            WebhookHandled::class,
            WebhookReceived::class,
        ]);

        $response = (new WebhookControllerTestStub)->handleWebhook($request);

        Event::assertDispatched(WebhookReceived::class);
        Event::assertDispatched(WebhookHandled::class);

        $this->assertEquals('Subscription Schedule Webhook Handled', $response->getContent());
    }

    public function test_subscription_schedule_canceled_webhook_is_handled()
    {
        $request = $this->request('subscription_schedule.canceled');

        Event::fake([
            WebhookHandled::class,
            WebhookReceived::class,
        ]);

        $response = (new WebhookControllerTestStub)->handleWebhook($request);

        Event::assertDispatched(WebhookReceived::class);
        Event::assertDispatched(WebhookHandled::class);

        $this->assertEquals('Subscription Schedule Webhook Handled', $response->getContent());
    }

    public function test_subscription_schedule_completed_webhook_is_handled()
    {
        $request = $this->request('subscription_schedule.completed');

        Event::fake([
            WebhookHandled::class,
            WebhookReceived::class,
        ]);

        $response = (new WebhookControllerTestStub)->handleWebhook($request);

        Event::assertDispatched(WebhookReceived::class);
        Event::assertDispatched(WebhookHandled::class);

        $this->assertEquals('Subscription Schedule Webhook Handled', $response->getContent());
    }

    public function test_subscription_schedule_released_webhook_is_handled()
    {
        $request = $this->request('subscription_schedule.released');

        Event::fake([
            WebhookHandled::class,
            WebhookReceived::class,
        ]);

        $response = (new WebhookControllerTestStub)->handleWebhook($request);

        Event::assertDispatched(WebhookReceived::class);
        Event::assertDispatched(WebhookHandled::class);

        $this->assertEquals('Subscription Schedule Webhook Handled', $response->getContent());
    }

    private function request($event)
    {
        return Request::create(
            '/', 'POST', [], [], [], [], json_encode(['type' => $event, 'id' => 'event-id'])
        );
    }
}

class WebhookControllerTestStub extends WebhookController
{
    public function __construct()
    {
        // Don't call parent constructor to prevent setting middleware...
    }

    public function handleChargeSucceeded(array $payload)
    {
        return new Response('Webhook Handled', 200);
    }

    public function handleQuoteFinalized(array $payload)
    {
        return new Response('Quote Webhook Handled', 200);
    }

    public function handleQuoteAccepted(array $payload)
    {
        return new Response('Quote Webhook Handled', 200);
    }

    public function handleQuoteCanceled(array $payload)
    {
        return new Response('Quote Webhook Handled', 200);
    }

    public function handleSubscriptionScheduleCreated(array $payload)
    {
        return new Response('Subscription Schedule Webhook Handled', 200);
    }

    public function handleSubscriptionScheduleUpdated(array $payload)
    {
        return new Response('Subscription Schedule Webhook Handled', 200);
    }

    public function handleSubscriptionScheduleCanceled(array $payload)
    {
        return new Response('Subscription Schedule Webhook Handled', 200);
    }

    public function handleSubscriptionScheduleCompleted(array $payload)
    {
        return new Response('Subscription Schedule Webhook Handled', 200);
    }

    public function handleSubscriptionScheduleReleased(array $payload)
    {
        return new Response('Subscription Schedule Webhook Handled', 200);
    }

    public function missingMethod($parameters = [])
    {
        return new Response('Missing event type: '.$parameters['type']);
    }
}

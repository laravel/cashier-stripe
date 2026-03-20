<?php

namespace Laravel\Cashier\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Laravel\Cashier\Events\WebhookHandled;
use Laravel\Cashier\Events\WebhookReceived;
use Laravel\Cashier\Http\Controllers\WebhookController;

/**
 * Webhook handler integration tests with real database.
 *
 * These tests verify that webhook handlers correctly persist data,
 * unlike the unit tests which only check the handlers are callable.
 */
class FlexibleBillingWebhookTest extends FeatureTestCase
{
    protected function webhookController(): WebhookControllerStub
    {
        return new WebhookControllerStub;
    }

    protected function webhookRequest(string $type, array $object): Request
    {
        return Request::create('/', 'POST', [], [], [], [], json_encode([
            'type' => $type,
            'id' => 'evt_test_'.uniqid(),
            'data' => ['object' => $object],
        ]));
    }

    // =========================================================================
    // Subscription Schedule Webhooks
    // =========================================================================

    public function test_subscription_schedule_created_webhook_creates_record()
    {
        $user = $this->createCustomer('webhook-sched-create');
        $user->createAsStripeCustomer();

        Event::fake([WebhookHandled::class, WebhookReceived::class]);

        $response = $this->webhookController()->handleWebhook(
            $this->webhookRequest('subscription_schedule.created', [
                'id' => 'sub_sched_test_'.uniqid(),
                'customer' => $user->stripeId(),
                'status' => 'not_started',
                'subscription' => null,
                'metadata' => ['type' => 'premium'],
                'current_phase' => null,
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        // Verify DB record was created
        $schedule = $user->subscriptionSchedules()->first();
        $this->assertNotNull($schedule);
        $this->assertSame('not_started', $schedule->stripe_status);
        $this->assertSame('premium', $schedule->type);
    }

    public function test_subscription_schedule_updated_webhook_updates_record()
    {
        $user = $this->createCustomer('webhook-sched-update');
        $user->createAsStripeCustomer();

        $stripeId = 'sub_sched_test_'.uniqid();

        // Pre-create the schedule record
        $user->subscriptionSchedules()->create([
            'type' => 'default',
            'stripe_id' => $stripeId,
            'stripe_status' => 'not_started',
        ]);

        Event::fake([WebhookHandled::class, WebhookReceived::class]);

        $now = time();
        $this->webhookController()->handleWebhook(
            $this->webhookRequest('subscription_schedule.updated', [
                'id' => $stripeId,
                'customer' => $user->stripeId(),
                'status' => 'active',
                'subscription' => 'sub_test_123',
                'current_phase' => [
                    'start_date' => $now,
                    'end_date' => $now + 2592000, // 30 days
                ],
            ])
        );

        $schedule = $user->subscriptionSchedules()->where('stripe_id', $stripeId)->first();
        $this->assertSame('active', $schedule->stripe_status);
        $this->assertSame('sub_test_123', $schedule->subscription_id);
        $this->assertNotNull($schedule->current_phase_started_at);
        $this->assertNotNull($schedule->current_phase_ends_at);
    }

    public function test_subscription_schedule_canceled_webhook_updates_record()
    {
        $user = $this->createCustomer('webhook-sched-cancel');
        $user->createAsStripeCustomer();

        $stripeId = 'sub_sched_test_'.uniqid();

        $user->subscriptionSchedules()->create([
            'type' => 'default',
            'stripe_id' => $stripeId,
            'stripe_status' => 'active',
        ]);

        Event::fake([WebhookHandled::class, WebhookReceived::class]);

        $this->webhookController()->handleWebhook(
            $this->webhookRequest('subscription_schedule.canceled', [
                'id' => $stripeId,
                'customer' => $user->stripeId(),
                'status' => 'canceled',
                'canceled_at' => time(),
            ])
        );

        $schedule = $user->subscriptionSchedules()->where('stripe_id', $stripeId)->first();
        $this->assertSame('canceled', $schedule->stripe_status);
        $this->assertNotNull($schedule->canceled_at);
    }

    public function test_subscription_schedule_completed_webhook_updates_record()
    {
        $user = $this->createCustomer('webhook-sched-complete');
        $user->createAsStripeCustomer();

        $stripeId = 'sub_sched_test_'.uniqid();

        $user->subscriptionSchedules()->create([
            'type' => 'default',
            'stripe_id' => $stripeId,
            'stripe_status' => 'active',
        ]);

        Event::fake([WebhookHandled::class, WebhookReceived::class]);

        $this->webhookController()->handleWebhook(
            $this->webhookRequest('subscription_schedule.completed', [
                'id' => $stripeId,
                'customer' => $user->stripeId(),
                'status' => 'completed',
                'completed_at' => time(),
            ])
        );

        $schedule = $user->subscriptionSchedules()->where('stripe_id', $stripeId)->first();
        $this->assertSame('completed', $schedule->stripe_status);
        $this->assertNotNull($schedule->completed_at);
    }

    public function test_subscription_schedule_released_webhook_updates_record()
    {
        $user = $this->createCustomer('webhook-sched-release');
        $user->createAsStripeCustomer();

        $stripeId = 'sub_sched_test_'.uniqid();

        $user->subscriptionSchedules()->create([
            'type' => 'default',
            'stripe_id' => $stripeId,
            'stripe_status' => 'active',
        ]);

        Event::fake([WebhookHandled::class, WebhookReceived::class]);

        $this->webhookController()->handleWebhook(
            $this->webhookRequest('subscription_schedule.released', [
                'id' => $stripeId,
                'customer' => $user->stripeId(),
                'status' => 'released',
                'released_at' => time(),
            ])
        );

        $schedule = $user->subscriptionSchedules()->where('stripe_id', $stripeId)->first();
        $this->assertSame('released', $schedule->stripe_status);
        $this->assertNotNull($schedule->released_at);
    }

    public function test_webhook_for_unknown_customer_does_not_error()
    {
        Event::fake([WebhookHandled::class, WebhookReceived::class]);

        $response = $this->webhookController()->handleWebhook(
            $this->webhookRequest('subscription_schedule.created', [
                'id' => 'sub_sched_nonexistent',
                'customer' => 'cus_nonexistent',
                'status' => 'not_started',
                'metadata' => [],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_webhook_for_nonexistent_schedule_does_not_error()
    {
        $user = $this->createCustomer('webhook-sched-missing');
        $user->createAsStripeCustomer();

        Event::fake([WebhookHandled::class, WebhookReceived::class]);

        // Update for a schedule that doesn't exist locally
        $response = $this->webhookController()->handleWebhook(
            $this->webhookRequest('subscription_schedule.updated', [
                'id' => 'sub_sched_does_not_exist',
                'customer' => $user->stripeId(),
                'status' => 'active',
                'subscription' => null,
                'current_phase' => null,
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    // =========================================================================
    // Quote Webhooks
    // =========================================================================

    public function test_quote_finalized_webhook_updates_record()
    {
        $user = $this->createCustomer('webhook-quote-final');
        $user->createAsStripeCustomer();

        $stripeId = 'qt_test_'.uniqid();

        $user->quotes()->create([
            'stripe_id' => $stripeId,
            'status' => 'draft',
            'currency' => 'usd',
        ]);

        Event::fake([WebhookHandled::class, WebhookReceived::class]);

        $this->webhookController()->handleWebhook(
            $this->webhookRequest('quote.finalized', [
                'id' => $stripeId,
                'customer' => $user->stripeId(),
                'status' => 'open',
                'number' => 'QT-0001',
                'amount_subtotal' => 1000,
                'amount_total' => 1000,
                'status_transitions' => [
                    'finalized_at' => time(),
                    'accepted_at' => null,
                    'canceled_at' => null,
                ],
            ])
        );

        $quote = $user->quotes()->where('stripe_id', $stripeId)->first();
        $this->assertSame('open', $quote->status);
        $this->assertSame('QT-0001', $quote->number);
        $this->assertSame(1000, $quote->amount_total);
        $this->assertNotNull($quote->finalized_at);
    }

    public function test_quote_accepted_webhook_updates_record()
    {
        $user = $this->createCustomer('webhook-quote-accept');
        $user->createAsStripeCustomer();

        $stripeId = 'qt_test_'.uniqid();

        $user->quotes()->create([
            'stripe_id' => $stripeId,
            'status' => 'open',
            'currency' => 'usd',
        ]);

        Event::fake([WebhookHandled::class, WebhookReceived::class]);

        $this->webhookController()->handleWebhook(
            $this->webhookRequest('quote.accepted', [
                'id' => $stripeId,
                'customer' => $user->stripeId(),
                'status' => 'accepted',
                'status_transitions' => [
                    'accepted_at' => time(),
                ],
            ])
        );

        $quote = $user->quotes()->where('stripe_id', $stripeId)->first();
        $this->assertSame('accepted', $quote->status);
        $this->assertNotNull($quote->accepted_at);
    }

    public function test_quote_canceled_webhook_updates_record()
    {
        $user = $this->createCustomer('webhook-quote-cancel');
        $user->createAsStripeCustomer();

        $stripeId = 'qt_test_'.uniqid();

        $user->quotes()->create([
            'stripe_id' => $stripeId,
            'status' => 'open',
            'currency' => 'usd',
        ]);

        Event::fake([WebhookHandled::class, WebhookReceived::class]);

        $this->webhookController()->handleWebhook(
            $this->webhookRequest('quote.canceled', [
                'id' => $stripeId,
                'customer' => $user->stripeId(),
                'status' => 'canceled',
                'status_transitions' => [
                    'canceled_at' => time(),
                ],
            ])
        );

        $quote = $user->quotes()->where('stripe_id', $stripeId)->first();
        $this->assertSame('canceled', $quote->status);
        $this->assertNotNull($quote->canceled_at);
    }
}

class WebhookControllerStub extends WebhookController
{
    public function __construct()
    {
        // Don't call parent constructor to skip middleware
    }
}

<?php

namespace Laravel\Cashier\Console;

use Illuminate\Console\Command;
use Laravel\Cashier\Cashier;
use Stripe\WebhookEndpoint;

class WebhookCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cashier:webhook
            {--disabled : Immediately disable the webhook after creation}
            {--url= : Provide the url endpoint to connect the webhook to}
            {--api_version= : Provide the Stripe API version the webhook should use}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create the Stripe webhook to interact with Cashier.';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $endpoint = WebhookEndpoint::create([
            'enabled_events' => [
                'customer.subscription.created',
                'customer.subscription.updated',
                'customer.subscription.deleted',
                'customer.updated',
                'customer.deleted',
                'invoice.payment_action_required',
            ],
            'url' => $this->option('url') ?? route('cashier.webhook'),
            'api_version' => $this->option('api_version') ?? Cashier::STRIPE_VERSION,
        ], Cashier::stripeOptions());

        $this->info('The Stripe webhook was created successfully. Make sure you look up the webhook secret in your Stripe dashboard and set it up through your app\'s environment variables.');

        if ($this->option('disabled')) {
            WebhookEndpoint::update($endpoint->id, ['disabled' => true], Cashier::stripeOptions());

            $this->info('The Stripe webhook was disabled as requested. Make sure you enable it through the Stripe dashboard when you\'re ready.');
        }
    }
}

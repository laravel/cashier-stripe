<?php

namespace Laravel\Cashier\Console;

use Illuminate\Console\Command;
use Laravel\Cashier\Cashier;

class WebhookCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cashier:webhook
            {--disabled : Immediately disable the webhook after creation}
            {--url= : The URL endpoint for the webhook}
            {--api-version= : The Stripe API version the webhook should use}';

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
        $webhookEndpoints = Cashier::stripe()->webhookEndpoints;

        $endpoint = $webhookEndpoints->create([
            'enabled_events' => [
                'customer.subscription.created',
                'customer.subscription.updated',
                'customer.subscription.deleted',
                'customer.updated',
                'customer.deleted',
                'invoice.payment_action_required',
                'invoice.payment_succeeded',
            ],
            'url' => $this->option('url') ?? route('cashier.webhook'),
            'api_version' => $this->option('api-version') ?? Cashier::STRIPE_VERSION,
        ]);

        $this->info('The Stripe webhook was created successfully. Retrieve the webhook secret in your Stripe dashboard and define it as an environment variable.');

        if ($this->option('disabled')) {
            $webhookEndpoints->update($endpoint->id, ['disabled' => true]);

            $this->info('The Stripe webhook was disabled as requested. You may enable the webhook via the Stripe dashboard when needed.');
        }
    }
}

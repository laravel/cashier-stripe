<?php

namespace Laravel\Cashier\Tests\Unit;

use Laravel\Cashier\Tests\TestCase;
use Stripe\PaymentIntent;
use Stripe\StripeClient;

class PaymentControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(StripeClient::class, fn () => new class
        {
            public $paymentIntents;

            public function __construct()
            {
                $this->paymentIntents = new class
                {
                    public function retrieve($id)
                    {
                        return PaymentIntent::constructFrom([
                            'id' => $id,
                            'status' => 'requires_payment_method',
                            'amount' => 1000,
                            'currency' => 'usd',
                            'payment_method_types' => ['card', 'ideal'],
                            'client_secret' => 'pi_123_secret',
                            'customer' => null,
                            'payment_method' => null,
                        ]);
                    }
                };
            }
        });
    }

    public function test_unsupported_source_type_is_ignored()
    {
        $this->get('/stripe/payment/pi_123?source_type='.urlencode('card\\'))
            ->assertOk()
            ->assertSee('(null || paymentMethodTypes[0])', false);
    }

    public function test_supported_source_type_is_preselected()
    {
        $this->get('/stripe/payment/pi_123?source_type=ideal')
            ->assertOk()
            ->assertSee("('ideal' || paymentMethodTypes[0])", false);
    }
}

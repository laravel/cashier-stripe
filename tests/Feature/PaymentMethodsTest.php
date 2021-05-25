<?php

namespace Laravel\Cashier\Tests\Feature;

use Laravel\Cashier\Cashier;
use Laravel\Cashier\PaymentMethod;
use Stripe\PaymentMethod as StripePaymentMethod;
use Stripe\SetupIntent as StripeSetupIntent;
use Stripe\StripeClient;

class PaymentMethodsTest extends FeatureTestCase
{
    public function test_we_can_start_a_new_setup_intent_session()
    {
        $user = $this->createCustomer('we_can_start_a_new_setup_intent_session');

        $setupIntent = $user->createSetupIntent();

        $this->assertInstanceOf(StripeSetupIntent::class, $setupIntent);
    }

    public function test_we_can_add_payment_methods()
    {
        $user = $this->createCustomer('we_can_add_payment_methods');
        $user->createAsStripeCustomer();

        $paymentMethod = $user->addPaymentMethod('pm_card_visa');

        $this->assertInstanceOf(PaymentMethod::class, $paymentMethod);
        $this->assertEquals('visa', $paymentMethod->card->brand);
        $this->assertEquals('4242', $paymentMethod->card->last4);
        $this->assertTrue($user->hasPaymentMethod());
        $this->assertFalse($user->hasDefaultPaymentMethod());
    }

    public function test_we_can_add_default_sepa_payment_method()
    {
        $user = $this->createCustomer('we_can_add_default_sepa_payment_method');
        $user->createAsStripeCustomer();

        $stripe = new StripeClient(Cashier::stripeOptions());

        $paymentMethod = $stripe->paymentMethods->create([
            'type' => 'sepa_debit',
            'billing_details' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ],
            'sepa_debit' => [
                'iban' => 'BE62510007547061',
            ],
        ]);

        $paymentMethod = $user->updateDefaultPaymentMethod($paymentMethod);

        $this->assertInstanceOf(PaymentMethod::class, $paymentMethod);
        $this->assertEquals('sepa_debit', $user->pm_type);
        $this->assertEquals('7061', $user->pm_last_four);
        $this->assertEquals('sepa_debit', $paymentMethod->type);
        $this->assertEquals('7061', $paymentMethod->sepa_debit->last4);
        $this->assertTrue($user->hasPaymentMethod('sepa_debit'));
        $this->assertTrue($user->hasDefaultPaymentMethod());
    }

    public function test_we_can_remove_payment_methods()
    {
        $user = $this->createCustomer('we_can_remove_payment_methods');
        $user->createAsStripeCustomer();

        $paymentMethod = $user->addPaymentMethod('pm_card_visa');

        $this->assertCount(1, $user->paymentMethods());
        $this->assertTrue($user->hasPaymentMethod());

        $user->removePaymentMethod($paymentMethod->asStripePaymentMethod());

        $this->assertCount(0, $user->paymentMethods());
        $this->assertFalse($user->hasPaymentMethod());
    }

    public function test_we_can_remove_the_default_payment_method()
    {
        $user = $this->createCustomer('we_can_remove_the_default_payment_method');
        $user->createAsStripeCustomer();

        $paymentMethod = $user->updateDefaultPaymentMethod('pm_card_visa');

        $this->assertCount(1, $user->paymentMethods());
        $this->assertTrue($user->hasPaymentMethod());
        $this->assertTrue($user->hasDefaultPaymentMethod());

        $user->removePaymentMethod($paymentMethod->asStripePaymentMethod());

        $this->assertCount(0, $user->paymentMethods());
        $this->assertNull($user->defaultPaymentMethod());
        $this->assertNull($user->pm_type);
        $this->assertNull($user->pm_last_four);
        $this->assertFalse($user->hasPaymentMethod());
        $this->assertFalse($user->hasDefaultPaymentMethod());
    }

    public function test_we_can_set_a_default_payment_method()
    {
        $user = $this->createCustomer('we_can_set_a_default_payment_method');
        $user->createAsStripeCustomer();

        $paymentMethod = $user->updateDefaultPaymentMethod('pm_card_visa');

        $this->assertInstanceOf(PaymentMethod::class, $paymentMethod);
        $this->assertEquals('visa', $paymentMethod->card->brand);
        $this->assertEquals('4242', $paymentMethod->card->last4);
        $this->assertTrue($user->hasDefaultPaymentMethod());

        $paymentMethod = $user->defaultPaymentMethod();

        $this->assertInstanceOf(PaymentMethod::class, $paymentMethod);
        $this->assertEquals('visa', $paymentMethod->card->brand);
        $this->assertEquals('visa', $user->pm_type);
        $this->assertEquals('4242', $paymentMethod->card->last4);
        $this->assertEquals('4242', $user->pm_last_four);
    }

    public function test_we_can_retrieve_all_payment_methods()
    {
        $user = $this->createCustomer('we_can_retrieve_all_payment_methods');
        $customer = $user->createAsStripeCustomer();

        $paymentMethod = StripePaymentMethod::retrieve('pm_card_visa', $user->stripeOptions());
        $paymentMethod->attach(['customer' => $customer->id]);

        $paymentMethod = StripePaymentMethod::retrieve('pm_card_mastercard', $user->stripeOptions());
        $paymentMethod->attach(['customer' => $customer->id]);

        $paymentMethods = $user->paymentMethods();

        $this->assertCount(2, $paymentMethods);
        $this->assertEquals('mastercard', $paymentMethods->first()->card->brand);
        $this->assertEquals('visa', $paymentMethods->last()->card->brand);
    }

    public function test_we_can_sync_the_default_payment_method_from_stripe()
    {
        $user = $this->createCustomer('we_can_sync_the_default_payment_method_from_stripe');
        $customer = $user->createAsStripeCustomer();

        $paymentMethod = StripePaymentMethod::retrieve('pm_card_visa', $user->stripeOptions());
        $paymentMethod->attach(['customer' => $customer->id]);

        $customer->invoice_settings = ['default_payment_method' => $paymentMethod->id];

        $customer->save();

        $user->refresh();

        $this->assertNull($user->pm_type);
        $this->assertNull($user->pm_last_four);

        $user = $user->updateDefaultPaymentMethodFromStripe();

        $this->assertEquals('visa', $user->pm_type);
        $this->assertEquals('4242', $user->pm_last_four);
    }

    public function test_we_delete_all_payment_methods()
    {
        $user = $this->createCustomer('we_delete_all_payment_methods');
        $customer = $user->createAsStripeCustomer();

        $paymentMethod = StripePaymentMethod::retrieve('pm_card_visa', $user->stripeOptions());
        $paymentMethod->attach(['customer' => $customer->id]);

        $paymentMethod = StripePaymentMethod::retrieve('pm_card_mastercard', $user->stripeOptions());
        $paymentMethod->attach(['customer' => $customer->id]);

        $paymentMethods = $user->paymentMethods();

        $this->assertCount(2, $paymentMethods);

        $user->deletePaymentMethods();

        $paymentMethods = $user->paymentMethods();

        $this->assertCount(0, $paymentMethods);
    }
}

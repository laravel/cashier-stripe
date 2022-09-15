<?php

namespace Laravel\Cashier\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Laravel\Cashier\CustomerBalanceTransaction;
use Stripe\TaxId as StripeTaxId;

class CustomerTest extends FeatureTestCase
{
    public function test_customers_in_stripe_can_be_updated()
    {
        $user = $this->createCustomer('customers_in_stripe_can_be_updated');

        $customer = $user->createAsStripeCustomer();

        $this->assertEquals('Main Str. 1', $customer->address->line1);
        $this->assertEquals('Little Rock', $customer->address->city);
        $this->assertEquals('72201', $customer->address->postal_code);

        $customer = $user->updateStripeCustomer(['description' => 'Mohamed Said']);

        $this->assertEquals('Mohamed Said', $customer->description);
    }

    public function test_customer_details_can_be_synced_with_stripe()
    {
        $user = $this->createCustomer('customer_details_can_be_synced_with_stripe');
        $user->createAsStripeCustomer();

        $user->name = 'Mohamed Said';
        $user->email = 'mohamed@example.com';
        $user->phone = '+32 499 00 00 00';

        $customer = $user->syncStripeCustomerDetails();

        $this->assertEquals('Mohamed Said', $customer->name);
        $this->assertEquals('mohamed@example.com', $customer->email);
        $this->assertEquals('+32 499 00 00 00', $customer->phone);
        $this->assertEquals('Main Str. 1', $customer->address->line1);
        $this->assertEquals('Little Rock', $customer->address->city);
        $this->assertEquals('72201', $customer->address->postal_code);
    }

    public function test_customers_can_generate_a_billing_portal_url()
    {
        $user = $this->createCustomer('customers_can_generate_a_billing_portal_url');
        $user->createAsStripeCustomer();

        $url = $user->billingPortalUrl('https://example.com');

        $this->assertStringStartsWith('https://billing.stripe.com/', $url);
    }

    public function test_customers_can_be_redirected_to_their_billing_portal()
    {
        $user = $this->createCustomer('customers_can_be_redirected_to_their_billing_portal');
        $user->createAsStripeCustomer();

        $response = $user->redirectToBillingPortal('https://example.com');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringStartsWith('https://billing.stripe.com/', $response->getTargetUrl());
    }

    public function test_customers_can_manage_tax_ids()
    {
        $user = $this->createCustomer('customers_can_manage_tax_ids');
        $user->createAsStripeCustomer();

        $taxId = $user->createTaxId('eu_vat', 'BE0123456789');

        $this->assertSame('eu_vat', $taxId->type);
        $this->assertSame('BE0123456789', $taxId->value);
        $this->assertSame('BE', $taxId->country);

        $taxIds = $user->taxIds();

        $this->assertCount(1, $taxIds);
        $this->assertInstanceOf(StripeTaxId::class, $taxIds->first());

        $taxId = $user->findTaxId($taxId->id);

        $this->assertSame('eu_vat', $taxId->type);
        $this->assertSame('BE0123456789', $taxId->value);

        $user->deleteTaxId($taxId->id);

        $this->assertEmpty($user->taxIds());
    }

    public function test_customers_can_manage_their_balance()
    {
        $user = $this->createCustomer('customers_can_manage_their_balance');
        $user->createAsStripeCustomer();

        $this->assertSame(0, $user->rawBalance());

        $transaction = $user->applyBalance(500, 'Top up Credit');

        $this->assertSame(500, $user->rawBalance());
        $this->assertSame('$5.00', $user->balance());
        $this->assertSame(500, $transaction->rawAmount());
        $this->assertSame('$5.00', $transaction->amount());
        $this->assertSame(500, $transaction->rawEndingBalance());

        $user->applyBalance(-200);

        /** @var \Laravel\Cashier\CustomerBalanceTransaction $transaction */
        $transaction = $user->balanceTransactions()->first();

        $this->assertInstanceOf(CustomerBalanceTransaction::class, $transaction);
        $this->assertSame(-200, $transaction->rawAmount());
        $this->assertSame('-$2.00', $transaction->amount());
        $this->assertSame(300, $transaction->rawEndingBalance());
        $this->assertSame(300, $user->rawBalance());
    }

    public function test_on_generic_trial_scopes()
    {
        $user = $this->createCustomer('on_generic_trial', ['trial_ends_at' => Carbon::tomorrow()]);

        $this->assertTrue($user->query()->onGenericTrial()->exists());
        $this->assertFalse($user->query()->hasExpiredGenericTrial()->exists());
    }

    public function test_expired_generic_trial_scopes()
    {
        $user = $this->createCustomer('on_generic_trial', ['trial_ends_at' => Carbon::yesterday()]);

        $this->assertFalse($user->query()->onGenericTrial()->exists());
        $this->assertTrue($user->query()->hasExpiredGenericTrial()->exists());
    }
}

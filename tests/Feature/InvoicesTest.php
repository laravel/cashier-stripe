<?php

namespace Laravel\Cashier\Tests\Feature;

use Laravel\Cashier\Exceptions\InvalidCustomer;
use Laravel\Cashier\Exceptions\InvalidInvoice;
use Laravel\Cashier\Invoice;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class InvoicesTest extends FeatureTestCase
{
    public function test_require_stripe_customer_for_invoicing()
    {
        $user = $this->createCustomer('require_stripe_customer_for_invoicing');

        $this->expectException(InvalidCustomer::class);

        $user->invoice();
    }

    public function test_invoicing_fails_with_nothing_to_invoice()
    {
        $user = $this->createCustomer('invoicing_fails_with_nothing_to_invoice');
        $user->createAsStripeCustomer();
        $user->updateDefaultPaymentMethod('pm_card_visa');

        $response = $user->invoice();

        $this->assertFalse($response);
    }

    public function test_customer_can_be_invoiced()
    {
        $user = $this->createCustomer('customer_can_be_invoiced');
        $user->createAsStripeCustomer();
        $user->updateDefaultPaymentMethod('pm_card_visa');

        $response = $user->invoiceFor('Laracon', 49900);

        $this->assertInstanceOf(Invoice::class, $response);
        $this->assertEquals(49900, $response->total);
    }

    public function test_find_invoice_by_id()
    {
        $user = $this->createCustomer('find_invoice_by_id');
        $user->createAsStripeCustomer();
        $user->updateDefaultPaymentMethod('pm_card_visa');
        $invoice = $user->invoiceFor('Laracon', 49900);

        $invoice = $user->findInvoice($invoice->id);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertEquals(49900, $invoice->rawTotal());
    }

    public function test_it_throws_an_exception_if_the_invoice_does_not_belong_to_the_user()
    {
        $user = $this->createCustomer('it_throws_an_exception_if_the_invoice_does_not_belong_to_the_user');
        $user->createAsStripeCustomer();
        $otherUser = $this->createCustomer('other_user');
        $otherUser->createAsStripeCustomer();
        $user->updateDefaultPaymentMethod('pm_card_visa');
        $invoice = $user->invoiceFor('Laracon', 49900);

        $this->expectException(InvalidInvoice::class);
        $this->expectExceptionMessage(
            "The invoice `{$invoice->id}` does not belong to this customer `$otherUser->stripe_id`."
        );

        $otherUser->findInvoice($invoice->id);
    }

    public function test_find_invoice_by_id_or_fail()
    {
        $user = $this->createCustomer('find_invoice_by_id_or_fail');
        $user->createAsStripeCustomer();
        $otherUser = $this->createCustomer('other_user');
        $otherUser->createAsStripeCustomer();
        $user->updateDefaultPaymentMethod('pm_card_visa');
        $invoice = $user->invoiceFor('Laracon', 49900);

        $this->expectException(AccessDeniedHttpException::class);

        $otherUser->findInvoiceOrFail($invoice->id);
    }

    public function test_customer_can_be_invoiced_with_quantity()
    {
        $user = $this->createCustomer('customer_can_be_invoiced_with_quantity');
        $user->createAsStripeCustomer();
        $user->updateDefaultPaymentMethod('pm_card_visa');

        $response = $user->invoiceFor('Laracon', 1000, ['quantity' => 5]);

        $this->assertInstanceOf(Invoice::class, $response);
        $this->assertEquals(5000, $response->total);
    }
}

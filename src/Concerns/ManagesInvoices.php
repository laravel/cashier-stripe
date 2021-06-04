<?php

namespace Laravel\Cashier\Concerns;

use Illuminate\Support\Collection;
use Laravel\Cashier\Exceptions\InvalidInvoice;
use Laravel\Cashier\Invoice;
use Laravel\Cashier\Payment;
use Stripe\Exception\CardException as StripeCardException;
use Stripe\Exception\InvalidRequestException as StripeInvalidRequestException;
use Stripe\Invoice as StripeInvoice;
use Stripe\InvoiceItem as StripeInvoiceItem;
use Stripe\PaymentIntent as StripePaymentIntent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait ManagesInvoices
{
    /**
     * Add an invoice item to the customer's upcoming invoice.
     *
     * @param  string  $description
     * @param  int  $amount
     * @param  array  $options
     * @return \Stripe\InvoiceItem
     */
    public function tab($description, $amount, array $options = [])
    {
        $this->assertCustomerExists();

        $options = array_merge([
            'customer' => $this->stripe_id,
            'currency' => $this->preferredCurrency(),
            'description' => $description,
        ], $options);

        if (array_key_exists('quantity', $options)) {
            $options['unit_amount'] = $options['unit_amount'] ?? $amount;
        } else {
            $options['amount'] = $amount;
        }

        return StripeInvoiceItem::create($options, $this->stripeOptions());
    }

    /**
     * Invoice the customer for the given amount and generate an invoice immediately.
     *
     * @param  string  $description
     * @param  int  $amount
     * @param  array  $tabOptions
     * @param  array  $invoiceOptions
     * @return \Laravel\Cashier\Invoice|bool
     *
     * @throws \Laravel\Cashier\Exceptions\PaymentActionRequired
     * @throws \Laravel\Cashier\Exceptions\PaymentFailure
     */
    public function invoiceFor($description, $amount, array $tabOptions = [], array $invoiceOptions = [])
    {
        $this->tab($description, $amount, $tabOptions);

        return $this->invoice($invoiceOptions);
    }

    /**
     * Invoice the customer outside of the regular billing cycle.
     *
     * @param  array  $options
     * @return \Laravel\Cashier\Invoice|bool
     *
     * @throws \Laravel\Cashier\Exceptions\PaymentActionRequired
     * @throws \Laravel\Cashier\Exceptions\PaymentFailure
     */
    public function invoice(array $options = [])
    {
        $this->assertCustomerExists();

        $parameters = array_merge($options, ['customer' => $this->stripe_id]);

        try {
            /** @var \Stripe\Invoice $invoice */
            $stripeInvoice = StripeInvoice::create($parameters, $this->stripeOptions());

            if ($stripeInvoice->collection_method === StripeInvoice::COLLECTION_METHOD_CHARGE_AUTOMATICALLY) {
                $stripeInvoice = $stripeInvoice->pay();
            } else {
                $stripeInvoice = $stripeInvoice->sendInvoice();
            }

            return new Invoice($this, $stripeInvoice);
        } catch (StripeInvalidRequestException $exception) {
            return false;
        } catch (StripeCardException $exception) {
            $payment = new Payment(
                StripePaymentIntent::retrieve(
                    ['id' => $stripeInvoice->refresh()->payment_intent, 'expand' => ['invoice.subscription']],
                    $this->stripeOptions()
                )
            );

            $payment->validate();
        }
    }

    /**
     * Get the customer's upcoming invoice.
     *
     * @return \Laravel\Cashier\Invoice|null
     */
    public function upcomingInvoice()
    {
        if (! $this->hasStripeId()) {
            return;
        }

        try {
            $stripeInvoice = StripeInvoice::upcoming(['customer' => $this->stripe_id], $this->stripeOptions());

            return new Invoice($this, $stripeInvoice);
        } catch (StripeInvalidRequestException $exception) {
            //
        }
    }

    /**
     * Find an invoice by ID.
     *
     * @param  string  $id
     * @return \Laravel\Cashier\Invoice|null
     */
    public function findInvoice($id)
    {
        $stripeInvoice = null;

        try {
            $stripeInvoice = StripeInvoice::retrieve(
                $id, $this->stripeOptions()
            );
        } catch (StripeInvalidRequestException $exception) {
            //
        }

        return $stripeInvoice ? new Invoice($this, $stripeInvoice) : null;
    }

    /**
     * Find an invoice or throw a 404 or 403 error.
     *
     * @param  string  $id
     * @return \Laravel\Cashier\Invoice
     *
     * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function findInvoiceOrFail($id)
    {
        try {
            $invoice = $this->findInvoice($id);
        } catch (InvalidInvoice $exception) {
            throw new AccessDeniedHttpException;
        }

        if (is_null($invoice)) {
            throw new NotFoundHttpException;
        }

        return $invoice;
    }

    /**
     * Create an invoice download Response.
     *
     * @param  string  $id
     * @param  array  $data
     * @param  string  $filename
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function downloadInvoice($id, array $data, $filename = null)
    {
        $invoice = $this->findInvoiceOrFail($id);

        return $filename ? $invoice->downloadAs($filename, $data) : $invoice->download($data);
    }

    /**
     * Get a collection of the customer's invoices.
     *
     * @param  bool  $includePending
     * @param  array  $parameters
     * @return \Illuminate\Support\Collection|\Laravel\Cashier\Invoice[]
     */
    public function invoices($includePending = false, $parameters = [])
    {
        if (! $this->hasStripeId()) {
            return collect();
        }

        $invoices = [];

        $parameters = array_merge(['limit' => 24], $parameters);

        $stripeInvoices = StripeInvoice::all(
            ['customer' => $this->stripe_id] + $parameters,
            $this->stripeOptions()
        );

        // Here we will loop through the Stripe invoices and create our own custom Invoice
        // instances that have more helper methods and are generally more convenient to
        // work with than the plain Stripe objects are. Then, we'll return the array.
        if (! is_null($stripeInvoices)) {
            foreach ($stripeInvoices->data as $invoice) {
                if ($invoice->paid || $includePending) {
                    $invoices[] = new Invoice($this, $invoice);
                }
            }
        }

        return new Collection($invoices);
    }

    /**
     * Get an array of the customer's invoices.
     *
     * @param  array  $parameters
     * @return \Illuminate\Support\Collection|\Laravel\Cashier\Invoice[]
     */
    public function invoicesIncludingPending(array $parameters = [])
    {
        return $this->invoices(true, $parameters);
    }
}

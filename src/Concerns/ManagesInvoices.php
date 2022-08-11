<?php

namespace Laravel\Cashier\Concerns;

use Illuminate\Support\Collection;
use Laravel\Cashier\Exceptions\InvalidInvoice;
use Laravel\Cashier\Invoice;
use Laravel\Cashier\Payment;
use LogicException;
use Stripe\Exception\CardException as StripeCardException;
use Stripe\Exception\InvalidRequestException as StripeInvalidRequestException;
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
        if ($this->isAutomaticTaxEnabled() && ! array_key_exists('price_data', $options)) {
            throw new LogicException('When using automatic tax calculation, you need to define the "price_data" in the options.');
        }

        $this->assertCustomerExists();

        $options = array_merge([
            'customer' => $this->stripe_id,
            'currency' => $this->preferredCurrency(),
            'description' => $description,
        ], $options);

        if (array_key_exists('price_data', $options)) {
            $options['price_data'] = array_merge([
                'unit_amount' => $amount,
                'currency' => $this->preferredCurrency(),
            ], $options['price_data']);
        } elseif (array_key_exists('quantity', $options)) {
            $options['unit_amount'] = $options['unit_amount'] ?? $amount;
        } else {
            $options['amount'] = $amount;
        }

        return $this->stripe()->invoiceItems->create($options);
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
     * @throws \Laravel\Cashier\Exceptions\IncompletePayment
     */
    public function invoiceFor($description, $amount, array $tabOptions = [], array $invoiceOptions = [])
    {
        $this->tab($description, $amount, $tabOptions);

        return $this->invoice($invoiceOptions);
    }

    /**
     * Add an invoice item for a specific Price ID to the customer's upcoming invoice.
     *
     * @param  string  $price
     * @param  int  $quantity
     * @param  array  $options
     * @return \Stripe\InvoiceItem
     */
    public function tabPrice($price, $quantity = 1, array $options = [])
    {
        $this->assertCustomerExists();

        $options = array_merge([
            'customer' => $this->stripe_id,
            'price' => $price,
            'quantity' => $quantity,
        ], $options);

        return $this->stripe()->invoiceItems->create($options);
    }

    /**
     * Invoice the customer for the given Price ID and generate an invoice immediately.
     *
     * @param  string  $price
     * @param  int  $quantity
     * @param  array  $tabOptions
     * @param  array  $invoiceOptions
     * @return \Laravel\Cashier\Invoice|bool
     *
     * @throws \Laravel\Cashier\Exceptions\IncompletePayment
     */
    public function invoicePrice($price, $quantity = 1, array $tabOptions = [], array $invoiceOptions = [])
    {
        $this->tabPrice($price, $quantity, $tabOptions);

        return $this->invoice($invoiceOptions);
    }

    /**
     * Invoice the customer outside of the regular billing cycle.
     *
     * @param  array  $options
     * @return \Laravel\Cashier\Invoice|bool
     *
     * @throws \Laravel\Cashier\Exceptions\IncompletePayment
     */
    public function invoice(array $options = [])
    {
        try {
            $invoice = $this->createInvoice(array_merge([
                'pending_invoice_items_behavior' => 'include',
            ], $options));

            return $invoice->chargesAutomatically() ? $invoice->pay() : $invoice->send();
        } catch (StripeCardException) {
            $payment = new Payment(
                $this->stripe()->paymentIntents->retrieve(
                    $invoice->asStripeInvoice()->refresh()->payment_intent,
                    ['expand' => ['invoice.subscription']]
                )
            );

            $payment->validate();
        }
    }

    /**
     * Create an invoice within Stripe.
     *
     * @param  array  $options
     * @return \Laravel\Cashier\Invoice
     */
    public function createInvoice(array $options = [])
    {
        $this->assertCustomerExists();

        $parameters = array_merge([
            'automatic_tax' => $this->automaticTaxPayload(),
            'customer' => $this->stripe_id,
        ], $options);

        $stripeInvoice = $this->stripe()->invoices->create($parameters);

        return new Invoice($this, $stripeInvoice);
    }

    /**
     * Get the customer's upcoming invoice.
     *
     * @param  array  $options
     * @return \Laravel\Cashier\Invoice|null
     */
    public function upcomingInvoice(array $options = [])
    {
        if (! $this->hasStripeId()) {
            return;
        }

        $parameters = array_merge([
            'automatic_tax' => $this->automaticTaxPayload(),
            'customer' => $this->stripe_id,
        ], $options);

        try {
            $stripeInvoice = $this->stripe()->invoices->upcoming($parameters);

            return new Invoice($this, $stripeInvoice, $parameters);
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
            $stripeInvoice = $this->stripe()->invoices->retrieve($id);
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
    public function downloadInvoice($id, array $data = [], $filename = null)
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
            return new Collection();
        }

        $invoices = [];

        $parameters = array_merge(['limit' => 24], $parameters);

        $stripeInvoices = $this->stripe()->invoices->all(
            ['customer' => $this->stripe_id] + $parameters
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
     * Get an array of the customer's invoices, including pending invoices.
     *
     * @param  array  $parameters
     * @return \Illuminate\Support\Collection|\Laravel\Cashier\Invoice[]
     */
    public function invoicesIncludingPending(array $parameters = [])
    {
        return $this->invoices(true, $parameters);
    }
}

<?php

namespace Laravel\Cashier\Concerns;

use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Laravel\Cashier\Exceptions\InvalidInvoice;
use Laravel\Cashier\Invoice;
use Laravel\Cashier\InvoicePayment;
use Laravel\Cashier\Payment;
use LogicException;
use Stripe\Exception\CardException as StripeCardException;
use Stripe\Exception\InvalidRequestException as StripeInvalidRequestException;
use Stripe\Price as StripePrice;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait ManagesInvoices
{
    use InteractsWithStripe;

    /**
     * Add an invoice item to the customer's upcoming invoice.
     *
     * @param  string  $description
     * @param  int|null  $amount
     * @param  array  $options
     * @return \Stripe\InvoiceItem
     */
    public function tab(string $description, ?int $amount, array $options = [])
    {
        if ($this->isAutomaticTaxEnabled() && ! array_key_exists('price_data', $options)) {
            throw new LogicException(
                'When using automatic tax calculation, you must include "price_data" in the provided options array.'
            );
        }

        $this->assertCustomerExists();

        $options = array_merge([
            'customer' => $this->stripe_id,
            'currency' => $this->preferredCurrency(),
            'description' => $description,
        ], $options);

        if (array_key_exists('price_data', $options)) {
            $options['price_data'] = array_merge([
                'unit_amount_decimal' => $amount,
                'currency' => $this->preferredCurrency(),
            ], $options['price_data']);
        } elseif (array_key_exists('quantity', $options)) {
            $options['unit_amount_decimal'] = $options['unit_amount_decimal'] ?? $amount;
        } else {
            $options['amount'] = $amount;
        }

        /** @var \Stripe\Service\InvoiceItemService $invoiceItems */
        $invoiceItemsService = static::stripe()->invoiceItems;

        return $invoiceItemsService->create($options);
    }

    /**
     * Invoice the customer for the given amount and generate an invoice immediately.
     *
     * @param  string  $description
     * @param  int  $amount
     * @param  array  $tabOptions
     * @param  array  $invoiceOptions
     * @return \Laravel\Cashier\Invoice
     *
     * @throws \Laravel\Cashier\Exceptions\IncompletePayment
     */
    public function invoiceFor(string $description, int $amount, array $tabOptions = [], array $invoiceOptions = []): Invoice
    {
        $this->tab($description, $amount, $tabOptions);

        return $this->invoice($invoiceOptions);
    }

    /**
     * Add an invoice item for a specific Price ID to the customer's upcoming invoice.
     *
     * @param  \Stripe\Price|string  $price
     * @param  int  $quantity
     * @param  array  $options
     * @return \Stripe\InvoiceItem
     */
    public function tabPrice(StripePrice|string $price, int $quantity = 1, array $options = [])
    {
        $this->assertCustomerExists();

        $options = array_merge([
            'customer' => $this->stripe_id,
            'pricing' => ['price' => $price],
            'quantity' => $quantity,
        ], $options);

        /** @var \Stripe\Service\InvoiceItemService $invoiceItems */
        $invoiceItemsService = static::stripe()->invoiceItems;

        return $invoiceItemsService->create($options);
    }

    /**
     * Invoice the customer for the given Price ID and generate an invoice immediately.
     *
     * @param  string  $price
     * @param  int  $quantity
     * @param  array  $tabOptions
     * @param  array  $invoiceOptions
     * @return \Laravel\Cashier\Invoice
     *
     * @throws \Laravel\Cashier\Exceptions\IncompletePayment
     */
    public function invoicePrice(StripePrice|string $price, int $quantity = 1, array $tabOptions = [], array $invoiceOptions = []): Invoice
    {
        $this->tabPrice($price, $quantity, $tabOptions);

        return $this->invoice($invoiceOptions);
    }

    /**
     * Invoice the customer outside of the regular billing cycle.
     *
     * @param  array  $options
     * @return \Laravel\Cashier\Invoice
     *
     * @throws \Laravel\Cashier\Exceptions\IncompletePayment
     */
    public function invoice(array $options = []): Invoice
    {
        try {
            $payOptions = Arr::only($options, $payOptionKeys = [
                'forgive',
                'mandate',
                'off_session',
                'payment_method',
                'source',
            ]);

            Arr::forget($options, $payOptionKeys);

            $invoice = $this->createInvoice(array_merge([
                'pending_invoice_items_behavior' => 'include',
            ], $options));

            return $invoice->chargesAutomatically() ? $invoice->pay($payOptions) : $invoice->send();
        } catch (StripeCardException $exception) {
            // Get the latest payment from the invoice payments...
            $stripeInvoice = $invoice->refresh(['payments'])->asStripeInvoice();

            $invoicePayments = $stripeInvoice->payments->data;

            if (! empty($invoicePayments)) {
                $latestPayment = end($invoicePayments);

                if ($latestPayment->payment && $latestPayment->payment->payment_intent) {
                    $payment = new Payment(
                        static::stripe()->paymentIntents->retrieve(
                            $latestPayment->payment->payment_intent,
                            ['expand' => ['invoice.subscription']]
                        )
                    );

                    $payment->validate();
                }
            }

            throw $exception;
        }
    }

    /**
     * Create an invoice within Stripe.
     *
     * @param  array  $options
     * @return \Laravel\Cashier\Invoice
     */
    public function createInvoice(array $options = []): Invoice
    {
        $this->assertCustomerExists();

        $stripeCustomer = $this->asStripeCustomer();

        $parameters = array_merge([
            'automatic_tax' => $this->automaticTaxPayload(),
            'customer' => $this->stripe_id,
            'currency' => $stripeCustomer->currency ?? config('cashier.currency'),
        ], $options);

        if (isset($parameters['subscription'])) {
            unset($parameters['currency']);
        }

        if (array_key_exists('subscription', $parameters)) {
            unset($parameters['pending_invoice_items_behavior']);
        }

        $stripeInvoice = static::stripe()->invoices->create($parameters);

        return new Invoice($this, $stripeInvoice);
    }

    /**
     * Get the customer's upcoming invoice.
     *
     * @param  array  $options
     * @return \Laravel\Cashier\Invoice|null
     */
    public function upcomingInvoice(array $options = []): ?Invoice
    {
        if (! $this->hasStripeId()) {
            return null;
        }

        $parameters = array_merge([
            'automatic_tax' => $this->automaticTaxPayload(),
            'customer' => $this->stripe_id,
        ], $options);

        // For the new Create Preview Invoice API, we need to provide specific details....
        if (! $this->hasRequiredPreviewDetails($parameters)) {
            $activeSubscription = $this->subscriptions()->active()->first();

            if ($activeSubscription) {
                $parameters['subscription'] = $activeSubscription->stripe_id;
            }
        }

        try {
            $stripeInvoice = static::stripe()->invoices->createPreview($parameters);

            return new Invoice($this, $stripeInvoice, $parameters);
        } catch (StripeInvalidRequestException $exception) {
            return null;
        }
    }

    /**
     * Check if the parameters contain the required details for the Create Preview Invoice API.
     *
     * @param  array  $parameters
     * @return bool
     */
    protected function hasRequiredPreviewDetails(array $parameters): bool
    {
        return isset($parameters['subscription']) ||
               isset($parameters['subscription_details']) ||
               isset($parameters['schedule']) ||
               isset($parameters['schedule_details']) ||
               isset($parameters['invoice_items']);
    }

    /**
     * Find an invoice by ID.
     *
     * @param  string  $id
     * @return \Laravel\Cashier\Invoice|null
     */
    public function findInvoice(string $id): ?Invoice
    {
        $stripeInvoice = null;

        try {
            $stripeInvoice = static::stripe()->invoices->retrieve($id);
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
    public function findInvoiceOrFail(string $id): Invoice
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
    public function downloadInvoice(string $id, array $data = [], ?string $filename = null): SymfonyResponse
    {
        $invoice = $this->findInvoiceOrFail($id);

        return $filename ? $invoice->downloadAs($filename, $data) : $invoice->download($data);
    }

    /**
     * Get a collection of the customer's invoices.
     *
     * @param  bool  $includePending
     * @param  array  $parameters
     * @return \Illuminate\Support\Collection<int, \Laravel\Cashier\Invoice>
     */
    public function invoices(bool $includePending = false, array $parameters = []): Collection
    {
        if (! $this->hasStripeId()) {
            return new Collection();
        }

        $invoices = [];

        $parameters = array_merge(['limit' => 24], $parameters);

        $stripeInvoices = static::stripe()->invoices->all(
            ['customer' => $this->stripe_id] + $parameters
        );

        // Here we will loop through the Stripe invoices and create our own custom Invoice
        // instances that have more helper methods and are generally more convenient to
        // work with than the plain Stripe objects are. Then, we'll return the array.
        if (! is_null($stripeInvoices)) {
            foreach ($stripeInvoices->data as $invoice) {
                $invoiceInstance = new Invoice($this, $invoice);

                if ($invoiceInstance->isPaid() || $includePending) {
                    $invoices[] = $invoiceInstance;
                }
            }
        }

        return new Collection($invoices);
    }

    /**
     * Get an array of the customer's invoices, including pending invoices.
     *
     * @param  array  $parameters
     * @return \Illuminate\Support\Collection<int, \Laravel\Cashier\Invoice>
     */
    public function invoicesIncludingPending(array $parameters = []): Collection
    {
        return $this->invoices(true, $parameters);
    }

    /**
     * Get a cursor paginator for the customer's invoices.
     *
     * @param  int|null  $perPage
     * @param  array  $parameters
     * @param  string  $cursorName
     * @param  \Illuminate\Pagination\Cursor|string|null  $cursor
     * @return \Illuminate\Contracts\Pagination\CursorPaginator
     */
    public function cursorPaginateInvoices(
        ?int $perPage = 24,
        array $parameters = [],
        string $cursorName = 'cursor',
        Cursor|string|null $cursor = null
    ): CursorPaginator {
        if (! $cursor instanceof Cursor) {
            $cursor = is_string($cursor)
                ? Cursor::fromEncoded($cursor)
                : CursorPaginator::resolveCurrentCursor($cursorName, $cursor);
        }

        if (! is_null($cursor)) {
            if ($cursor->pointsToNextItems()) {
                $parameters['starting_after'] = $cursor->parameter('id');
            } else {
                $parameters['ending_before'] = $cursor->parameter('id');
            }
        }

        $invoices = $this->invoices(true, array_merge($parameters, ['limit' => $perPage + 1]));

        if (! is_null($cursor) && $cursor->pointsToPreviousItems()) {
            $invoices = $invoices->reverse();
        }

        return new CursorPaginator($invoices, $perPage, $cursor, array_merge([
            'path' => Paginator::resolveCurrentPath(),
            'cursorName' => $cursorName,
            'parameters' => ['id'],
        ]));
    }

    /**
     * Get invoice payments for a specific payment intent.
     *
     * @param  string  $paymentIntentId
     * @return \Illuminate\Support\Collection<int, \Laravel\Cashier\InvoicePayment>
     */
    public function invoicePaymentsForPaymentIntent(string $paymentIntentId): Collection
    {
        $invoicePayments = static::stripe()->invoicePayments->all([
            'payment' => [
                'type' => 'payment_intent',
                'payment_intent' => $paymentIntentId,
            ],
        ]);

        return collect($invoicePayments->data)->map(function ($payment) {
            return new InvoicePayment($payment);
        });
    }

    /**
     * Get invoice payments for a specific invoice.
     *
     * @param  string  $invoiceId
     * @return \Illuminate\Support\Collection<int, \Laravel\Cashier\InvoicePayment>
     */
    public function invoicePaymentsForInvoice(string $invoiceId): Collection
    {
        $invoicePayments = static::stripe()->invoicePayments->all([
            'invoice' => $invoiceId,
        ]);

        return collect($invoicePayments->data)->map(function ($payment) {
            return new InvoicePayment($payment);
        });
    }
}

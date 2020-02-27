<?php

namespace Laravel\Cashier;

use Carbon\Carbon;
use Dompdf\Dompdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Laravel\Cashier\Exceptions\InvalidInvoice;
use Stripe\Invoice as StripeInvoice;
use Stripe\InvoiceLineItem as StripeInvoiceLineItem;
use Symfony\Component\HttpFoundation\Response;

class Invoice
{
    /**
     * The Stripe model instance.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $owner;

    /**
     * The Stripe invoice instance.
     *
     * @var \Stripe\Invoice
     */
    protected $invoice;

    /**
     * The Stripe invoice items.
     *
     * @var \Stripe\Collection|\Stripe\InvoiceLineItem[]
     */
    protected $items;

    /**
     * Create a new invoice instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $owner
     * @param  \Stripe\Invoice  $invoice
     * @return void
     *
     * @throws \Laravel\Cashier\Exceptions\InvalidInvoice
     */
    public function __construct($owner, StripeInvoice $invoice)
    {
        if ($owner->stripe_id !== $invoice->customer) {
            throw InvalidInvoice::invalidOwner($invoice, $owner);
        }

        $this->owner = $owner;
        $this->invoice = $invoice;
    }

    /**
     * Get a Carbon date for the invoice.
     *
     * @param  \DateTimeZone|string  $timezone
     * @return \Carbon\Carbon
     */
    public function date($timezone = null)
    {
        $carbon = Carbon::createFromTimestampUTC($this->invoice->created ?? $this->invoice->date);

        return $timezone ? $carbon->setTimezone($timezone) : $carbon;
    }

    /**
     * Get the total amount that was paid (or will be paid).
     *
     * @return string
     */
    public function total()
    {
        return $this->formatAmount($this->rawTotal());
    }

    /**
     * Get the raw total amount that was paid (or will be paid).
     *
     * @return int
     */
    public function rawTotal()
    {
        return $this->invoice->total + $this->rawStartingBalance();
    }

    /**
     * Get the total of the invoice (before discounts).
     *
     * @return string
     */
    public function subtotal()
    {
        return $this->formatAmount($this->invoice->subtotal);
    }

    /**
     * Determine if the account had a starting balance.
     *
     * @return bool
     */
    public function hasStartingBalance()
    {
        return $this->rawStartingBalance() < 0;
    }

    /**
     * Get the starting balance for the invoice.
     *
     * @return string
     */
    public function startingBalance()
    {
        return $this->formatAmount($this->rawStartingBalance());
    }

    /**
     * Get the raw starting balance for the invoice.
     *
     * @return int
     */
    public function rawStartingBalance()
    {
        return $this->invoice->starting_balance ?? 0;
    }

    /**
     * Determine if the invoice has a discount.
     *
     * @return bool
     */
    public function hasDiscount()
    {
        return $this->invoice->subtotal > 0 &&
            $this->invoice->subtotal != $this->invoice->total &&
            ! is_null($this->invoice->discount);
    }

    /**
     * Get the discount amount.
     *
     * @return string
     */
    public function discount()
    {
        return $this->formatAmount($this->invoice->subtotal + $this->invoice->tax - $this->invoice->total);
    }

    /**
     * Get the coupon code applied to the invoice.
     *
     * @return string|null
     */
    public function coupon()
    {
        if (isset($this->invoice->discount)) {
            return $this->invoice->discount->coupon->id;
        }
    }

    /**
     * Determine if the discount is a percentage.
     *
     * @return bool
     */
    public function discountIsPercentage()
    {
        return $this->coupon() && isset($this->invoice->discount->coupon->percent_off);
    }

    /**
     * Get the discount percentage for the invoice.
     *
     * @return int
     */
    public function percentOff()
    {
        if ($this->coupon()) {
            return $this->invoice->discount->coupon->percent_off;
        }

        return 0;
    }

    /**
     * Get the discount amount for the invoice.
     *
     * @return string
     */
    public function amountOff()
    {
        if (isset($this->invoice->discount->coupon->amount_off)) {
            return $this->formatAmount($this->invoice->discount->coupon->amount_off);
        }

        return $this->formatAmount(0);
    }

    /**
     * Get the tax total amount.
     *
     * @return string
     */
    public function tax()
    {
        return $this->formatAmount($this->invoice->tax);
    }

    /**
     * Get all of the "invoice item" line items.
     *
     * @return array
     */
    public function invoiceItems()
    {
        return $this->invoiceItemsByType('invoiceitem');
    }

    /**
     * Get all of the "subscription" line items.
     *
     * @return array
     */
    public function subscriptions()
    {
        return $this->invoiceItemsByType('subscription');
    }

    /**
     * Get all of the invoice items by a given type.
     *
     * @param  string  $type
     * @return array
     */
    public function invoiceItemsByType($type)
    {
        if (is_null($this->items)) {
            $this->items = new Collection($this->lines->autoPagingIterator());
        }

        return $this->items->filter(function (StripeInvoiceLineItem $item) use ($type) {
            return $item->type === $type;
        })->map(function (StripeInvoiceLineItem $item) {
            return new InvoiceItem($this->owner, $item);
        })->all();
    }

    /**
     * Format the given amount into a displayable currency.
     *
     * @param  int  $amount
     * @return string
     */
    protected function formatAmount($amount)
    {
        return Cashier::formatAmount($amount, $this->invoice->currency);
    }

    /**
     * Get the View instance for the invoice.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\View\View
     */
    public function view(array $data)
    {
        return View::make('cashier::receipt', array_merge($data, [
            'invoice' => $this,
            'owner' => $this->owner,
            'user' => $this->owner,
        ]));
    }

    /**
     * Capture the invoice as a PDF and return the raw bytes.
     *
     * @param  array  $data
     * @return string
     */
    public function pdf(array $data)
    {
        if (! defined('DOMPDF_ENABLE_AUTOLOAD')) {
            define('DOMPDF_ENABLE_AUTOLOAD', false);
        }

        $dompdf = new Dompdf;
        $dompdf->setPaper(config('cashier.paper', 'letter'));
        $dompdf->loadHtml($this->view($data)->render());
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Create an invoice download response.
     *
     * @param  array  $data
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function download(array $data)
    {
        $filename = $data['product'].'_'.$this->date()->month.'_'.$this->date()->year;

        return $this->downloadAs($filename, $data);
    }

    /**
     * Create an invoice download response with a specific filename.
     *
     * @param  string  $filename
     * @param  array  $data
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function downloadAs($filename, array $data)
    {
        return new Response($this->pdf($data), 200, [
            'Content-Description' => 'File Transfer',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"',
            'Content-Transfer-Encoding' => 'binary',
            'Content-Type' => 'application/pdf',
            'X-Vapor-Base64-Encode' => 'True',
        ]);
    }

    /**
     * Get the Stripe model instance.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function owner()
    {
        return $this->owner;
    }

    /**
     * Get the Stripe invoice instance.
     *
     * @return \Stripe\Invoice
     */
    public function asStripeInvoice()
    {
        return $this->invoice;
    }

    /**
     * Dynamically get values from the Stripe invoice.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->invoice->{$key};
    }
}

<?php

namespace Laravel\Cashier;

use Carbon\Carbon;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\View;
use Stripe\Invoice as StripeInvoice;
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
     * Create a new invoice instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $owner
     * @param  \Stripe\Invoice  $invoice
     * @return void
     */
    public function __construct($owner, StripeInvoice $invoice)
    {
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
        $carbon = Carbon::createFromTimestampUTC($this->invoice->date);

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
     * @return float
     */
    public function rawTotal()
    {
        return max(0, $this->invoice->total - ($this->rawStartingBalance() * -1));
    }

    /**
     * Get the total of the invoice (before discounts).
     *
     * @return string
     */
    public function subtotal()
    {
        return $this->formatAmount(
            max(0, $this->invoice->subtotal - $this->rawStartingBalance())
        );
    }

    /**
     * Determine if the account had a starting balance.
     *
     * @return bool
     */
    public function hasStartingBalance()
    {
        return $this->rawStartingBalance() > 0;
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
     * Determine if the invoice has a discount.
     *
     * @return bool
     */
    public function hasDiscount()
    {
        return $this->invoice->subtotal > 0 && $this->invoice->subtotal != $this->invoice->total
          && ! is_null($this->invoice->discount);
    }

    /**
     * Get the discount amount.
     *
     * @return string
     */
    public function discount()
    {
        return $this->formatAmount($this->invoice->subtotal - $this->invoice->total);
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
        } else {
            return $this->formatAmount(0);
        }
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
     * Get all of the invoie items by a given type.
     *
     * @param  string  $type
     * @return array
     */
    public function invoiceItemsByType($type)
    {
        $lineItems = [];

        if (isset($this->lines->data)) {
            foreach ($this->lines->data as $line) {
                if ($line->type == $type) {
                    $lineItems[] = new InvoiceItem($this->owner, $line);
                }
            }
        }

        return $lineItems;
    }

    /**
     * Format the given amount into a string based on the Stripe model's preferences.
     *
     * @param  int  $amount
     * @return string
     */
    protected function formatAmount($amount)
    {
        return Cashier::formatAmount($amount);
    }

    /**
     * Get the View instance for the invoice.
     *
     * @param  array  $data
     * @return \Illuminate\View\View
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

        if (file_exists($configPath = base_path().'/vendor/dompdf/dompdf/dompdf_config.inc.php')) {
            require_once $configPath;
        }

        $dompdf = new Dompdf;

        $dompdf->loadHtml($this->view($data)->render());

        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Create an invoice download response.
     *
     * @param  array   $data
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function download(array $data)
    {
        $filename = $data['product'].'_'.$this->date()->month.'_'.$this->date()->year.'.pdf';

        return new Response($this->pdf($data), 200, [
            'Content-Description' => 'File Transfer',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Transfer-Encoding' => 'binary',
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Get the raw starting balance for the invoice.
     *
     * @return float
     */
    public function rawStartingBalance()
    {
        return isset($this->invoice->starting_balance)
                   ? $this->invoice->starting_balance : 0;
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

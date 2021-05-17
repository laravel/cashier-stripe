<?php

namespace Laravel\Cashier;

use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use JsonSerializable;
use Laravel\Cashier\Exceptions\InvalidInvoice;
use Stripe\Customer as StripeCustomer;
use Stripe\Invoice as StripeInvoice;
use Stripe\InvoiceLineItem as StripeInvoiceLineItem;
use Symfony\Component\HttpFoundation\Response;

class Invoice implements Arrayable, Jsonable, JsonSerializable
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
     * The Stripe invoice line items.
     *
     * @var \Laravel\Cashier\InvoiceLineItem[]
     */
    protected $items;

    /**
     * The taxes applied to the invoice.
     *
     * @var \Laravel\Cashier\Tax[]
     */
    protected $taxes;

    /**
     * The taxes applied to the invoice.
     *
     * @var \Laravel\Cashier\Discount[]
     */
    protected $discounts;

    /**
     * Indicate if the Stripe Object was refreshed with extra data.
     *
     * @var bool
     */
    protected $refreshed = false;

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
     * Get a Carbon instance for the invoicing date.
     *
     * @param  \DateTimeZone|string  $timezone
     * @return \Carbon\Carbon
     */
    public function date($timezone = null)
    {
        $carbon = Carbon::createFromTimestampUTC($this->invoice->created);

        return $timezone ? $carbon->setTimezone($timezone) : $carbon;
    }

    /**
     * Get a Carbon instance for the invoice's due date.
     *
     * @param  \DateTimeZone|string  $timezone
     * @return \Carbon\Carbon|null
     */
    public function dueDate($timezone = null)
    {
        if ($this->invoice->due_date) {
            $carbon = Carbon::createFromTimestampUTC($this->invoice->due_date);

            return $timezone ? $carbon->setTimezone($timezone) : $carbon;
        }
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
     * Determine if the invoice has one or more discounts applied.
     *
     * @return bool
     */
    public function hasDiscount()
    {
        if (is_null($this->invoice->discounts)) {
            return false;
        }

        return count($this->invoice->discounts) > 0;
    }

    /**
     * Get all of the discount objects from the Stripe invoice.
     *
     * @return \Laravel\Cashier\Discount[]
     */
    public function discounts()
    {
        if (! is_null($this->discounts)) {
            return $this->discounts;
        }

        $this->refreshWithExpandedData();

        return Collection::make($this->invoice->discounts)
            ->mapInto(Discount::class)
            ->all();
    }

    /**
     * Calculate the amount for a given discount.
     *
     * @param  \Laravel\Cashier\Discount  $discount
     * @return string|null
     */
    public function discountFor(Discount $discount)
    {
        if (! is_null($discountAmount = $this->rawDiscountFor($discount))) {
            return $this->formatAmount($discountAmount->amount);
        }
    }

    /**
     * Calculate the raw amount for a given discount.
     *
     * @param  \Laravel\Cashier\Discount  $discount
     * @return int|null
     */
    public function rawDiscountFor(Discount $discount)
    {
        return Collection::make($this->invoice->total_discount_amounts)
            ->first(function ($discountAmount) use ($discount) {
                if (is_string($discountAmount->discount)) {
                    return $discountAmount->discount === $discount->id;
                } else {
                    return $discountAmount->discount->id === $discount->id;
                }
            });
    }

    /**
     * Get the total discount amount.
     *
     * @return string
     */
    public function discount()
    {
        return $this->formatAmount($this->rawDiscount());
    }

    /**
     * Get the raw total discount amount.
     *
     * @return int
     */
    public function rawDiscount()
    {
        $total = 0;

        foreach ((array) $this->invoice->total_discount_amounts as $discount) {
            $total += $discount->amount;
        }

        return (int) $total;
    }

    /**
     * Get the total tax amount.
     *
     * @return string
     */
    public function tax()
    {
        return $this->formatAmount($this->invoice->tax);
    }

    /**
     * Determine if the invoice has tax applied.
     *
     * @return bool
     */
    public function hasTax()
    {
        $lineItems = $this->invoiceItems() + $this->subscriptions();

        return Collection::make($lineItems)->contains(function (InvoiceLineItem $item) {
            return $item->hasTaxRates();
        });
    }

    /**
     * Get the taxes applied to the invoice.
     *
     * @return \Laravel\Cashier\Tax[]
     */
    public function taxes()
    {
        if (! is_null($this->taxes)) {
            return $this->taxes;
        }

        $this->refreshWithExpandedData();

        return $this->taxes = Collection::make($this->invoice->total_tax_amounts)
            ->sortByDesc('inclusive')
            ->map(function (object $taxAmount) {
                return new Tax($taxAmount->amount, $this->invoice->currency, $taxAmount->tax_rate);
            })
            ->all();
    }

    /**
     * Determine if the customer is not exempted from taxes.
     *
     * @return bool
     */
    public function isNotTaxExempt()
    {
        return $this->invoice->customer_tax_exempt === StripeCustomer::TAX_EXEMPT_NONE;
    }

    /**
     * Determine if the customer is exempted from taxes.
     *
     * @return bool
     */
    public function isTaxExempt()
    {
        return $this->invoice->customer_tax_exempt === StripeCustomer::TAX_EXEMPT_EXEMPT;
    }

    /**
     * Determine if reverse charge applies to the customer.
     *
     * @return bool
     */
    public function reverseChargeApplies()
    {
        return $this->invoice->customer_tax_exempt === StripeCustomer::TAX_EXEMPT_REVERSE;
    }

    /**
     * Get all of the "invoice item" line items.
     *
     * @return \Laravel\Cashier\InvoiceLineItem[]
     */
    public function invoiceItems()
    {
        return Collection::make($this->invoiceLineItems())->filter(function (InvoiceLineItem $item) {
            return $item->type === 'invoiceitem';
        })->all();
    }

    /**
     * Get all of the "subscription" line items.
     *
     * @return \Laravel\Cashier\InvoiceLineItem[]
     */
    public function subscriptions()
    {
        return Collection::make($this->invoiceLineItems())->filter(function (InvoiceLineItem $item) {
            return $item->type === 'subscription';
        })->all();
    }

    /**
     * Get all of the invoice items.
     *
     * @return \Laravel\Cashier\InvoiceLineItem[]
     */
    public function invoiceLineItems()
    {
        if (! is_null($this->items)) {
            return $this->items;
        }

        $this->refreshWithExpandedData();

        return $this->items = Collection::make($this->invoice->lines->autoPagingIterator())
            ->map(function (StripeInvoiceLineItem $item) {
                return new InvoiceLineItem($this, $item);
            })->all();
    }

    /**
     * Refresh the invoice with expanded objects.
     *
     * @return void
     */
    protected function refreshWithExpandedData()
    {
        if ($this->refreshed) {
            return;
        }

        if ($this->invoice->id) {
            $this->invoice = StripeInvoice::retrieve([
                'id' => $this->invoice->id,
                'expand' => [
                    'account_tax_ids',
                    'discounts',
                    'lines.data.tax_amounts.tax_rate',
                    'total_discount_amounts.discount',
                    'total_tax_amounts.tax_rate',
                ],
            ], $this->owner->stripeOptions());
        } else {
            // If no invoice ID is present then assume this is the customer's upcoming invoice...
            $this->invoice = StripeInvoice::upcoming([
                'customer' => $this->owner->stripe_id,
                'expand' => [
                    'account_tax_ids',
                    'discounts',
                    'lines.data.tax_amounts.tax_rate',
                    'total_discount_amounts.discount',
                    'total_tax_amounts.tax_rate',
                ],
            ], $this->owner->stripeOptions());
        }

        $this->refreshed = true;
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
     * Return the Tax Ids of the account.
     *
     * @return \Stripe\TaxId[]
     */
    public function accountTaxIds()
    {
        $this->refreshWithExpandedData();

        return $this->invoice->account_tax_ids ?? [];
    }

    /**
     * Return the Tax Ids of the customer.
     *
     * @return array
     */
    public function customerTaxIds()
    {
        return $this->invoice->customer_tax_ids ?? [];
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

        $options = new Options;
        $options->setChroot(base_path());

        $dompdf = new Dompdf($options);
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
     * Void the Stripe invoice.
     *
     * @param  array  $options
     * @return $this
     */
    public function void(array $options = [])
    {
        $this->invoice = $this->invoice->voidInvoice($options, $this->owner->stripeOptions());

        return $this;
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
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->asStripeInvoice()->toArray();
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Dynamically get values from the Stripe object.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->invoice->{$key};
    }
}

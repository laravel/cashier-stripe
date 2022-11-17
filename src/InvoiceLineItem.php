<?php

namespace Laravel\Cashier;

use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Collection;
use JsonSerializable;
use Stripe\InvoiceLineItem as StripeInvoiceLineItem;
use Stripe\TaxRate as StripeTaxRate;

class InvoiceLineItem implements Arrayable, Jsonable, JsonSerializable
{
    /**
     * The Cashier Invoice instance.
     *
     * @var \Laravel\Cashier\Invoice
     */
    protected $invoice;

    /**
     * The Stripe invoice line item instance.
     *
     * @var \Stripe\InvoiceLineItem
     */
    protected $item;

    /**
     * Create a new invoice line item instance.
     *
     * @param  \Laravel\Cashier\Invoice  $invoice
     * @param  \Stripe\InvoiceLineItem  $item
     * @return void
     */
    public function __construct(Invoice $invoice, StripeInvoiceLineItem $item)
    {
        $this->invoice = $invoice;
        $this->item = $item;
    }

    /**
     * Get the total for the invoice line item.
     *
     * @return string
     */
    public function total()
    {
        return $this->formatAmount($this->item->amount);
    }

    /**
     * Get the unit amount excluding tax for the invoice line item.
     *
     * @return string
     */
    public function unitAmountExcludingTax()
    {
        return $this->formatAmount($this->item->unit_amount_excluding_tax ?? 0);
    }

    /**
     * Determine if the line item has both inclusive and exclusive tax.
     *
     * @return bool
     */
    public function hasBothInclusiveAndExclusiveTax()
    {
        return $this->inclusiveTaxPercentage() && $this->exclusiveTaxPercentage();
    }

    /**
     * Get the total percentage of the default inclusive tax for the invoice line item.
     *
     * @return int|null
     */
    public function inclusiveTaxPercentage()
    {
        if ($this->invoice->isNotTaxExempt()) {
            return $this->calculateTaxPercentageByTaxAmount(true);
        }

        return $this->calculateTaxPercentageByTaxRate(true);
    }

    /**
     * Get the total percentage of the default exclusive tax for the invoice line item.
     *
     * @return int
     */
    public function exclusiveTaxPercentage()
    {
        if ($this->invoice->isNotTaxExempt()) {
            return $this->calculateTaxPercentageByTaxAmount(false);
        }

        return $this->calculateTaxPercentageByTaxRate(false);
    }

    /**
     * Calculate the total tax percentage for either the inclusive or exclusive tax by tax rate.
     *
     * @param  bool  $inclusive
     * @return int
     */
    protected function calculateTaxPercentageByTaxRate($inclusive)
    {
        if (! $this->item->tax_rates) {
            return 0;
        }

        return (int) Collection::make($this->item->tax_rates)
            ->filter(function (StripeTaxRate $taxRate) use ($inclusive) {
                return $taxRate->inclusive === (bool) $inclusive;
            })
            ->sum(function (StripeTaxRate $taxRate) {
                return $taxRate->percentage;
            });
    }

    /**
     * Calculate the total tax percentage for either the inclusive or exclusive tax by tax amount.
     *
     * @param  bool  $inclusive
     * @return int
     */
    protected function calculateTaxPercentageByTaxAmount($inclusive)
    {
        if (! $this->item->tax_amounts) {
            return 0;
        }

        return (int) Collection::make($this->item->tax_amounts)
            ->filter(function (object $taxAmount) use ($inclusive) {
                return $taxAmount->inclusive === (bool) $inclusive;
            })
            ->sum(function (object $taxAmount) {
                $taxRate = $taxAmount->tax_rate;

                if (is_string($taxRate)) {
                    $taxRate = Cashier::stripe()->taxRates->retrieve($taxRate);
                }

                return $taxRate->percentage;
            });
    }

    /**
     * Determine if the invoice line item has tax rates.
     *
     * @return bool
     */
    public function hasTaxRates()
    {
        if ($this->invoice->isNotTaxExempt()) {
            return ! empty($this->item->tax_amounts);
        }

        return ! empty($this->item->tax_rates);
    }

    /**
     * Get a human readable date for the start date.
     *
     * @return string|null
     */
    public function startDate()
    {
        if ($this->hasPeriod()) {
            return $this->startDateAsCarbon()->toFormattedDateString();
        }
    }

    /**
     * Get a human readable date for the end date.
     *
     * @return string|null
     */
    public function endDate()
    {
        if ($this->hasPeriod()) {
            return $this->endDateAsCarbon()->toFormattedDateString();
        }
    }

    /**
     * Get a Carbon instance for the start date.
     *
     * @return \Carbon\Carbon|null
     */
    public function startDateAsCarbon()
    {
        if ($this->hasPeriod()) {
            return Carbon::createFromTimestampUTC($this->item->period->start);
        }
    }

    /**
     * Get a Carbon instance for the end date.
     *
     * @return \Carbon\Carbon|null
     */
    public function endDateAsCarbon()
    {
        if ($this->hasPeriod()) {
            return Carbon::createFromTimestampUTC($this->item->period->end);
        }
    }

    /**
     * Determine if the invoice line item has a defined period.
     *
     * @return bool
     */
    public function hasPeriod()
    {
        return ! is_null($this->item->period);
    }

    /**
     * Determine if the invoice line item has a period with the same start and end date.
     *
     * @return bool
     */
    public function periodStartAndEndAreEqual()
    {
        return $this->hasPeriod() ? $this->item->period->start === $this->item->period->end : false;
    }

    /**
     * Determine if the invoice line item is for a subscription.
     *
     * @return bool
     */
    public function isSubscription()
    {
        return $this->item->type === 'subscription';
    }

    /**
     * Format the given amount into a displayable currency.
     *
     * @param  int  $amount
     * @return string
     */
    protected function formatAmount($amount)
    {
        return Cashier::formatAmount($amount, $this->item->currency);
    }

    /**
     * Get the Stripe model instance.
     *
     * @return \Laravel\Cashier\Invoice
     */
    public function invoice()
    {
        return $this->invoice;
    }

    /**
     * Get the underlying Stripe invoice line item.
     *
     * @return \Stripe\InvoiceLineItem
     */
    public function asStripeInvoiceLineItem()
    {
        return $this->item;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->asStripeInvoiceLineItem()->toArray();
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
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Dynamically access the Stripe invoice line item instance.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->item->{$key};
    }
}

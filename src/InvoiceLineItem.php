<?php

namespace Laravel\Cashier;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Exception;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Collection;
use JsonSerializable;
use Stripe\InvoiceLineItem as StripeInvoiceLineItem;

class InvoiceLineItem implements Arrayable, Jsonable, JsonSerializable
{
    /**
     * Create a new invoice line item instance.
     *
     * @param  \Laravel\Cashier\Invoice  $invoice
     * @param  \Stripe\InvoiceLineItem  $item
     * @return void
     */
    public function __construct(
        protected Invoice $invoice,
        protected StripeInvoiceLineItem $item
    ) {
        //
    }

    /**
     * Get the total for the invoice line item.
     *
     * @return string
     */
    public function total(): string
    {
        return $this->formatAmount($this->item->amount);
    }

    /**
     * Get the unit amount excluding tax for the invoice line item.
     *
     * @return string
     */
    public function unitAmountExcludingTax(): string
    {
        return $this->formatAmount($this->taxes()->sum('taxable_amount') ?? 0);
    }

    /**
     * Get the unit amount from the pricing structure.
     *
     * @return int|null
     */
    public function unitAmount(): ?int
    {
        if (! isset($this->item->pricing)) {
            return null;
        }

        // Handle the new pricing structure (Basil release)...
        if (isset($this->item->pricing->unit_amount_decimal)) {
            return (int) $this->item->pricing->unit_amount_decimal;
        }

        // For inline price data...
        if (
            $this->item->pricing->type === 'inline_price_data' &&
            isset($this->item->pricing->inline_price_data->unit_amount)
        ) {
            return (int) $this->item->pricing->inline_price_data->unit_amount;
        }

        return null;
    }

    /**
     * Get the formatted unit amount.
     *
     * @return string
     */
    public function formattedUnitAmount(): string
    {
        $unitAmount = $this->unitAmount();

        return $unitAmount ? $this->formatAmount($unitAmount) : '$0.00';
    }

    /**
     * Determine if the line item has both inclusive and exclusive tax.
     *
     * @return bool
     */
    public function hasBothInclusiveAndExclusiveTax(): bool
    {
        return $this->inclusiveTaxPercentage() && $this->exclusiveTaxPercentage();
    }

    /**
     * Get the total percentage of the default inclusive tax for the invoice line item.
     *
     * @return float|int|null
     */
    public function inclusiveTaxPercentage(): float|int|null
    {
        if ($this->invoice->isNotTaxExempt()) {
            return $this->calculateTaxPercentageByTaxAmount(true);
        }

        return $this->calculateTaxPercentageByTaxRate(true);
    }

    /**
     * Get the total percentage of the default exclusive tax for the invoice line item.
     *
     * @return float|int
     */
    public function exclusiveTaxPercentage(): float|int
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
     * @return float|int
     */
    protected function calculateTaxPercentageByTaxRate(bool $inclusive): float|int
    {
        if (! isset($this->item->taxes) || empty($this->item->taxes)) {
            return 0;
        }

        return Collection::make($this->item->taxes)
            ->filter(function (object $tax) use ($inclusive) {
                if ($tax->type !== 'tax_rate_details' || ! isset($tax->tax_rate_details)) {
                    return false;
                }

                $taxRate = $this->getTaxRate($tax->tax_rate_details);

                return $taxRate && $taxRate->inclusive === (bool) $inclusive;
            })
            ->sum(function (object $tax) {
                $taxRate = $this->getTaxRate($tax->tax_rate_details);

                return $taxRate ? $taxRate->percentage : 0;
            });
    }

    /**
     * Calculate the total tax percentage for either the inclusive or exclusive tax by tax amount.
     *
     * @param  bool  $inclusive
     * @return float|int
     */
    protected function calculateTaxPercentageByTaxAmount(bool $inclusive): float|int
    {
        if (! isset($this->item->taxes) || empty($this->item->taxes)) {
            return 0;
        }

        return Collection::make($this->item->taxes)
            ->filter(function (object $tax) use ($inclusive) {
                if ($tax->type !== 'tax_rate_details' || ! isset($tax->tax_rate_details)) {
                    return false;
                }

                $taxRate = $this->getTaxRate($tax->tax_rate_details);

                return $taxRate && $taxRate->inclusive === (bool) $inclusive;
            })
            ->sum(function (object $tax) {
                $taxRate = $this->getTaxRate($tax->tax_rate_details);

                return $taxRate ? $taxRate->percentage : 0;
            });
    }

    /**
     * Determine if the invoice line item has tax rates.
     *
     * @return bool
     */
    public function hasTaxRates(): bool
    {
        return isset($this->item->taxes) && ! empty($this->item->taxes);
    }

    /**
     * Get all taxes applied to this line item.
     *
     * @return \Illuminate\Support\Collection
     */
    public function taxes(): Collection
    {
        if (! isset($this->item->taxes)) {
            return collect();
        }

        return collect($this->item->taxes);
    }

    /**
     * Get tax rate details from the taxes array.
     *
     * @return \Illuminate\Support\Collection
     */
    public function taxRateDetails(): Collection
    {
        return $this->taxes()
            ->filter(function (object $tax) {
                return $tax->type === 'tax_rate_details' && isset($tax->tax_rate_details);
            })
            ->map(function (object $tax) {
                return $this->getTaxRate($tax->tax_rate_details);
            })
            ->filter();
    }

    /**
     * Get the tax rate from tax rate details, fetching from Stripe if needed.
     *
     * @param  object  $taxRateDetails
     * @return \Stripe\TaxRate|null
     */
    protected function getTaxRate($taxRateDetails)
    {
        // If tax_rate is already expanded as an object, return it...
        if (isset($taxRateDetails->tax_rate->id) && is_object($taxRateDetails->tax_rate)) {
            return $taxRateDetails->tax_rate;
        }

        // If tax_rate is just an ID string, fetch it from Stripe...
        if (isset($taxRateDetails->tax_rate) && is_string($taxRateDetails->tax_rate)) {
            try {
                return $this->invoice->owner()->stripe()->taxRates->retrieve($taxRateDetails->tax_rate);
            } catch (Exception $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * Get the total tax amount for this line item.
     *
     * @return int
     */
    public function totalTaxAmount(): int
    {
        return $this->taxes()->sum('amount');
    }

    /**
     * Get the tax behavior from the pricing structure.
     *
     * @return string|null
     */
    public function taxBehavior(): ?string
    {
        // Get the price object and return its tax_behavior...
        $price = $this->price();

        return $price ? ($price->tax_behavior ?? null) : null;
    }

    /**
     * Get a human readable date for the start date.
     *
     * @return string|null
     */
    public function startDate(): ?string
    {
        if ($this->hasPeriod()) {
            return $this->startDateAsCarbon()->toFormattedDateString();
        }

        return null;
    }

    /**
     * Get a human readable date for the end date.
     *
     * @return string|null
     */
    public function endDate(): ?string
    {
        if ($this->hasPeriod()) {
            return $this->endDateAsCarbon()->toFormattedDateString();
        }

        return null;
    }

    /**
     * Get a Carbon instance for the start date.
     *
     * @return \Carbon\CarbonInterface|null
     */
    public function startDateAsCarbon(): ?CarbonInterface
    {
        if ($this->hasPeriod()) {
            return Carbon::createFromTimestampUTC($this->item->period->start);
        }

        return null;
    }

    /**
     * Get a Carbon instance for the end date.
     *
     * @return \Carbon\CarbonInterface|null
     */
    public function endDateAsCarbon(): ?CarbonInterface
    {
        if ($this->hasPeriod()) {
            return Carbon::createFromTimestampUTC($this->item->period->end);
        }

        return null;
    }

    /**
     * Determine if the invoice line item has a defined period.
     *
     * @return bool
     */
    public function hasPeriod(): bool
    {
        return ! is_null($this->item->period);
    }

    /**
     * Determine if the invoice line item has a period with the same start and end date.
     *
     * @return bool
     */
    public function periodStartAndEndAreEqual(): bool
    {
        return $this->hasPeriod() ? $this->item->period->start === $this->item->period->end : false;
    }

    /**
     * Determine if the invoice line item is for a subscription.
     *
     * @return bool
     */
    public function isSubscription(): bool
    {
        return isset($this->item->parent) &&
               ($this->item->parent->type === 'subscription_details' ||
                $this->item->parent->type === 'subscription_item_details');
    }

    /**
     * Determine if the invoice line item is for an invoice item.
     *
     * @return bool
     */
    public function isInvoiceItem(): bool
    {
        return isset($this->item->parent) &&
               $this->item->parent->type === 'invoice_item_details';
    }

    /**
     * Get the subscription ID associated with this line item.
     *
     * @return string|null
     */
    public function subscriptionId(): ?string
    {
        if (! isset($this->item->parent)) {
            return null;
        }

        if ($this->item->parent->type === 'subscription_details') {
            return $this->item->parent->subscription_details->subscription ?? null;
        }

        if ($this->item->parent->type === 'subscription_item_details') {
            return $this->item->parent->subscription_item_details->subscription ?? null;
        }

        return null;
    }

    /**
     * Get the subscription item ID associated with this line item.
     *
     * @return string|null
     */
    public function subscriptionItemId(): ?string
    {
        if (! isset($this->item->parent)) {
            return null;
        }

        if ($this->item->parent->type === 'subscription_item_details') {
            return $this->item->parent->subscription_item_details->subscription_item ?? null;
        }

        return null;
    }

    /**
     * Get the invoice item ID associated with this line item.
     *
     * @return string|null
     */
    public function invoiceItemId(): ?string
    {
        if (! isset($this->item->parent)) {
            return null;
        }

        if ($this->item->parent->type === 'invoice_item_details') {
            return $this->item->parent->invoice_item_details->invoice_item ?? null;
        }

        return null;
    }

    /**
     * Determine if this line item is a proration.
     *
     * @return bool
     */
    public function isProration(): bool
    {
        if (! isset($this->item->parent)) {
            return false;
        }

        if ($this->item->parent->type === 'subscription_item_details') {
            return $this->item->parent->subscription_item_details->proration ?? false;
        }

        if ($this->item->parent->type === 'invoice_item_details') {
            return $this->item->parent->invoice_item_details->proration ?? false;
        }

        return false;
    }

    /**
     * Get proration details for this line item.
     *
     * @return object|null
     */
    public function prorationDetails(): ?object
    {
        if (! isset($this->item->parent)) {
            return null;
        }

        if ($this->item->parent->type === 'subscription_item_details') {
            return $this->item->parent->subscription_item_details->proration_details ?? null;
        }

        if ($this->item->parent->type === 'invoice_item_details') {
            return $this->item->parent->invoice_item_details->proration_details ?? null;
        }

        return null;
    }

    /**
     * Get the price ID from the pricing structure.
     *
     * @return string|null
     */
    public function priceId(): ?string
    {
        // Handle the new pricing structure (Basil release)...
        if (isset($this->item->pricing) && $this->item->pricing->type === 'price_details') {
            return $this->item->pricing->price_details->price ?? null;
        }

        return null;
    }

    /**
     * Get the full price object from Stripe.
     *
     * @return object|null
     */
    public function price(): ?object
    {
        if (isset($this->item->price) && is_object($this->item->price) && isset($this->item->price->id)) {
            return $this->item->price;
        }

        $priceId = $this->priceId();

        if ($priceId && $this->invoice->owner()) {
            try {
                return $this->invoice->owner()->stripe()->prices->retrieve($priceId);
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * Get the parent information for this line item.
     *
     * @return object|null
     */
    public function parent(): ?object
    {
        return $this->item->parent ?? null;
    }

    /**
     * Format the given amount into a displayable currency.
     *
     * @param  int  $amount
     * @return string
     */
    protected function formatAmount(int $amount): string
    {
        return Cashier::formatAmount($amount, $this->item->currency);
    }

    /**
     * Get the Stripe model instance.
     *
     * @return \Laravel\Cashier\Invoice
     */
    public function invoice(): Invoice
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
    public function __get(string $key)
    {
        return $this->item->{$key};
    }
}

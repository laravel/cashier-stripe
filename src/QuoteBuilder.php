<?php

namespace Laravel\Cashier;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Conditionable;
use InvalidArgumentException;
use Laravel\Cashier\Concerns\AllowsCoupons;
use Laravel\Cashier\Concerns\HandlesTaxes;
use Laravel\Cashier\Concerns\InteractsWithStripe;
use Laravel\Cashier\Concerns\ManagesBillingMode;
use Stripe\Quote as StripeQuote;

class QuoteBuilder
{
    use AllowsCoupons;
    use Conditionable;
    use HandlesTaxes;
    use InteractsWithStripe;
    use ManagesBillingMode;

    /**
     * The model that is creating the quote.
     *
     * @var \Laravel\Cashier\Billable|\Illuminate\Database\Eloquent\Model
     */
    protected $owner;

    /**
     * The line items for the quote.
     *
     * @var array
     */
    protected array $lineItems = [];

    /**
     * The description for the quote.
     *
     * @var string|null
     */
    protected ?string $description = null;

    /**
     * The footer for the quote.
     *
     * @var string|null
     */
    protected ?string $footer = null;

    /**
     * The header for the quote.
     *
     * @var string|null
     */
    protected ?string $header = null;

    /**
     * The expiration date for the quote.
     *
     * @var \Carbon\Carbon|null
     */
    protected ?Carbon $expiresAt = null;

    /**
     * The metadata for the quote.
     *
     * @var array
     */
    protected array $metadata = [];

    /**
     * Create a new quote builder instance.
     *
     * @param  mixed  $owner
     * @return void
     */
    public function __construct($owner)
    {
        $this->owner = $owner;
    }

    /**
     * Add a line item to the quote.
     *
     * @param  string  $price
     * @param  int|null  $quantity
     * @return $this
     */
    public function addLineItem(string $price, ?int $quantity = 1)
    {
        $item = ['price' => $price];

        if (! is_null($quantity)) {
            $item['quantity'] = $quantity;
        }

        $this->lineItems[] = $item;

        return $this;
    }

    /**
     * Set the line items for the quote.
     *
     * @param  array  $lineItems
     * @return $this
     */
    public function lineItems(array $lineItems)
    {
        $this->lineItems = $lineItems;

        return $this;
    }

    /**
     * Set the description for the quote.
     *
     * @param  string  $description
     * @return $this
     */
    public function description(string $description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Set the footer for the quote.
     *
     * @param  string  $footer
     * @return $this
     */
    public function footer(string $footer)
    {
        $this->footer = $footer;

        return $this;
    }

    /**
     * Set the header for the quote.
     *
     * @param  string  $header
     * @return $this
     */
    public function header(string $header)
    {
        $this->header = $header;

        return $this;
    }

    /**
     * Set the expiration date for the quote.
     *
     * @param  \DateTimeInterface|int  $expiresAt
     * @return $this
     */
    public function expiresAt(DateTimeInterface|int $expiresAt)
    {
        $this->expiresAt = $expiresAt instanceof DateTimeInterface
            ? Carbon::instance($expiresAt)
            : Carbon::createFromTimestamp($expiresAt);

        return $this;
    }

    /**
     * Set the metadata for the quote.
     *
     * @param  array  $metadata
     * @return $this
     */
    public function withMetadata(array $metadata)
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Create a new Stripe quote.
     *
     * @param  array  $options
     * @return \Laravel\Cashier\Quote
     */
    public function create(array $options = [])
    {
        if (empty($this->lineItems)) {
            throw new InvalidArgumentException('At least one line item is required when creating quotes.');
        }

        $stripeCustomer = $this->owner->createOrGetStripeCustomer();

        $payload = array_filter([
            'customer' => $stripeCustomer->id,
            'line_items' => $this->getLineItems(),
            'automatic_tax' => $this->automaticTaxPayload(),
            'description' => $this->description,
            'footer' => $this->footer,
            'header' => $this->header,
            'expires_at' => $this->expiresAt?->getTimestamp(),
            'metadata' => $this->metadata ?: null,
        ]);

        // Add discounts if set...
        if ($this->couponId || $this->promotionCodeId) {
            $discounts = [];

            if ($this->couponId) {
                $discounts[] = ['coupon' => $this->couponId];
            }

            if ($this->promotionCodeId) {
                $discounts[] = ['promotion_code' => $this->promotionCodeId];
            }

            $payload['discounts'] = $discounts;
        }

        // Add billing mode for subscription quotes...
        if ($billingMode = $this->getBillingModeForPayload()) {
            $payload['subscription_data'] = ['billing_mode' => $billingMode];
        }

        if ($taxRates = $this->getTaxRatesForPayload()) {
            $payload['default_tax_rates'] = $taxRates;
        }

        $payload = array_merge($payload, $options);

        $stripeQuote = $this->owner->stripe()->quotes->create($payload);

        return $this->createQuote($stripeQuote);
    }

    /**
     * Create the Eloquent Quote.
     *
     * @param  \Stripe\Quote  $stripeQuote
     * @return \Laravel\Cashier\Quote
     */
    protected function createQuote(StripeQuote $stripeQuote)
    {
        if ($quote = $this->owner->quotes()->where('stripe_id', $stripeQuote->id)->first()) {
            return $quote;
        }

        /** @var \Laravel\Cashier\Quote $quote */
        $quote = $this->owner->quotes()->create([
            'stripe_id' => $stripeQuote->id,
            'status' => $stripeQuote->status,
            'number' => $stripeQuote->number,
            'amount_subtotal' => $stripeQuote->amount_subtotal,
            'amount_total' => $stripeQuote->amount_total,
            'currency' => $stripeQuote->currency,
            'expires_at' => $stripeQuote->expires_at
                ? Carbon::createFromTimestamp($stripeQuote->expires_at)
                : null,
        ]);

        return $quote;
    }

    /**
     * Get the line items for the Stripe payload.
     *
     * @return array
     */
    protected function getLineItems(): array
    {
        return Collection::make($this->lineItems)->map(function ($item) {
            if (is_string($item)) {
                return ['price' => $item, 'quantity' => 1];
            }

            if (is_array($item) && isset($item['price']) && ! isset($item['quantity'])) {
                $item['quantity'] = 1;
            }

            return $item;
        })->values()->all();
    }

    /**
     * Get the tax rates for the Stripe payload.
     *
     * @return array|null
     */
    protected function getTaxRatesForPayload(): ?array
    {
        if ($taxRates = $this->owner->taxRates()) {
            return $taxRates;
        }

        return null;
    }
}

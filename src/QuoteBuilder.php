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
use Stripe\Quote as StripeQuote;

class QuoteBuilder
{
    use AllowsCoupons;
    use Conditionable;
    use HandlesTaxes;
    use InteractsWithStripe;

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
    protected $lineItems = [];

    /**
     * The application fee amount for the quote.
     *
     * @var int|null
     */
    protected $applicationFeeAmount = null;

    /**
     * The application fee percent for the quote.
     *
     * @var float|null
     */
    protected $applicationFeePercent = null;

    /**
     * The description for the quote.
     *
     * @var string|null
     */
    protected $description = null;

    /**
     * The footer for the quote.
     *
     * @var string|null
     */
    protected $footer = null;

    /**
     * The header for the quote.
     *
     * @var string|null
     */
    protected $header = null;

    /**
     * The expiration date for the quote.
     *
     * @var \Carbon\Carbon|\Carbon\CarbonInterface|null
     */
    protected $expiresAt = null;

    /**
     * The billing mode for the subscription created from this quote.
     *
     * @var array|null
     */
    protected $billingMode = null;

    /**
     * The metadata to apply to the quote.
     *
     * @var array
     */
    protected $metadata = [];

    /**
     * The quote number for the quote.
     *
     * @var string|null
     */
    protected $quoteNumber = null;

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
    public function addLineItem($price, $quantity = 1)
    {
        $this->lineItems[] = [
            'price' => $price,
            'quantity' => $quantity,
        ];

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
     * Set the application fee amount.
     *
     * @param  int  $amount
     * @return $this
     */
    public function applicationFeeAmount($amount)
    {
        $this->applicationFeeAmount = $amount;

        return $this;
    }

    /**
     * Set the application fee percent.
     *
     * @param  float  $percent
     * @return $this
     */
    public function applicationFeePercent($percent)
    {
        $this->applicationFeePercent = $percent;

        return $this;
    }

    /**
     * Set the description for the quote.
     *
     * @param  string  $description
     * @return $this
     */
    public function description($description)
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
    public function footer($footer)
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
    public function header($header)
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
    public function expiresAt($expiresAt)
    {
        if ($expiresAt instanceof DateTimeInterface) {
            $this->expiresAt = Carbon::instance($expiresAt);
        } else {
            $this->expiresAt = Carbon::createFromTimestamp($expiresAt);
        }

        return $this;
    }

    /**
     * Set the billing mode for subscriptions created from this quote.
     *
     * @param  string  $type
     * @return $this
     */
    public function withBillingMode($type = 'flexible')
    {
        $this->billingMode = ['type' => $type];

        return $this;
    }

    /**
     * The metadata to apply to the quote.
     *
     * @param  array  $metadata
     * @return $this
     */
    public function withMetadata($metadata)
    {
        $this->metadata = (array) $metadata;

        return $this;
    }

    /**
     * Set a custom quote number.
     *
     * @param  string  $number
     * @return $this
     */
    public function number($number)
    {
        $this->quoteNumber = $number;

        return $this;
    }

    /**
     * Create a new Stripe quote.
     *
     * @param  array  $options
     * @return \Laravel\Cashier\Quote
     *
     * @throws \Exception
     */
    public function create(array $options = [])
    {
        if (empty($this->lineItems)) {
            throw new InvalidArgumentException('At least one line item is required when creating quotes.');
        }

        $stripeCustomer = $this->owner->createOrGetStripeCustomer();

        $payload = array_merge([
            'customer' => $stripeCustomer->id,
            'line_items' => $this->getLineItems(),
            'application_fee_amount' => $this->applicationFeeAmount,
            'application_fee_percent' => $this->applicationFeePercent,
            'automatic_tax' => $this->automaticTaxPayload(),
            'description' => $this->description,
            'footer' => $this->footer,
            'header' => $this->header,
            'expires_at' => $this->expiresAt?->getTimestamp(),
            'metadata' => $this->metadata,
            'number' => $this->quoteNumber,
        ], $options);

        // Add discounts if set
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

        // Add billing mode for subscription quotes
        if ($billingMode = $this->getBillingModeForPayload()) {
            $payload['subscription_data']['billing_mode'] = $billingMode;
        }

        if ($taxRates = $this->getTaxRatesForPayload()) {
            $payload['default_tax_rates'] = $taxRates;
        }

        $payload = array_filter($payload);

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
    protected function getLineItems()
    {
        return Collection::make($this->lineItems)->map(function ($item) {
            if (is_string($item)) {
                return ['price' => $item, 'quantity' => 1];
            }

            if (is_array($item) && isset($item['price'])) {
                $item['quantity'] = $item['quantity'] ?? 1;

                return $item;
            }

            return $item;
        })->values()->all();
    }

    /**
     * Get the default billing mode from config.
     *
     * @return string
     */
    protected function getDefaultBillingMode()
    {
        return config('cashier.default_billing_mode', 'classic');
    }

    /**
     * Get the effective billing mode.
     *
     * @return string
     */
    protected function getEffectiveBillingMode()
    {
        return $this->billingMode['type'] ?? $this->getDefaultBillingMode();
    }

    /**
     * Get the billing mode for the Stripe payload.
     *
     * @return array|null
     */
    protected function getBillingModeForPayload()
    {
        $effectiveMode = $this->getEffectiveBillingMode();

        // Only include billing_mode in payload if it's flexible
        // Classic mode is Stripe's default, so we omit it for backwards compatibility
        if ($effectiveMode === 'flexible') {
            $this->validateFlexibleBillingSupport();

            return ['type' => 'flexible'];
        }

        return null;
    }

    /**
     * Validate that flexible billing mode is supported by the current API version.
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    protected function validateFlexibleBillingSupport()
    {
        $apiVersion = config('cashier.stripe.api_version') ?? \Stripe\Stripe::getApiVersion();

        if ($apiVersion && version_compare($apiVersion, '2025-06-30', '<')) {
            throw new \InvalidArgumentException(
                'Flexible billing mode requires Stripe API version 2025-06-30.basil or later. '.
                'Current version: '.$apiVersion.'. Please update your API version.'
            );
        }
    }

    /**
     * Get the tax rates for the Stripe payload.
     *
     * @return array|null
     */
    protected function getTaxRatesForPayload()
    {
        if ($taxRates = $this->owner->taxRates()) {
            return $taxRates;
        }
    }
}

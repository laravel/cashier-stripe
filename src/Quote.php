<?php

namespace Laravel\Cashier;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Concerns\InteractsWithStripe;
use Stripe\Quote as StripeQuote;

class Quote extends Model
{
    use InteractsWithStripe;

    /**
     * The billing mode for updates to this quote.
     */
    protected ?array $billingMode = null;

    /**
     * The table associated with the model.
     */
    protected $table = 'quotes';

    /**
     * The attributes that are not mass assignable.
     *
     * @var array<string>
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount_subtotal' => 'integer',
        'amount_total' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'expires_at' => 'datetime',
        'status_transitions_finalized_at' => 'datetime',
        'status_transitions_accepted_at' => 'datetime',
        'status_transitions_canceled_at' => 'datetime',
    ];

    /**
     * Get the user that owns the quote.
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->owner();
    }

    /**
     * Get the model related to the quote.
     */
    public function owner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        $model = config('cashier.customer_model', config('auth.providers.users.model', 'App\\Models\\User'));

        return $this->belongsTo($model, (new $model)->getForeignKey());
    }

    /**
     * Determine if the quote is in draft status.
     */
    public function draft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Determine if the quote is open (finalized and awaiting customer action).
     */
    public function open(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Determine if the quote has been accepted.
     */
    public function accepted(): bool
    {
        return $this->status === 'accepted';
    }

    /**
     * Determine if the quote has been canceled.
     */
    public function canceled(): bool
    {
        return $this->status === 'canceled';
    }

    /**
     * Finalize the quote.
     */
    public function finalize(array $options = []): static
    {
        $stripeQuote = $this->asStripeQuote();
        $stripeQuote = $stripeQuote->finalizeQuote($options);

        $this->syncFromStripe($stripeQuote);

        return $this;
    }

    /**
     * Accept the quote.
     */
    public function accept(array $options = []): static
    {
        $stripeQuote = $this->asStripeQuote();
        $stripeQuote = $stripeQuote->accept($options);

        $this->syncFromStripe($stripeQuote);

        return $this;
    }

    /**
     * Get the subscription created from this quote (if accepted).
     */
    public function subscription(): ?\Laravel\Cashier\Subscription
    {
        if (! $this->accepted()) {
            return null;
        }

        $stripeQuote = $this->asStripeQuote(['subscription']);
        if (! $stripeQuote->subscription) {
            return null;
        }

        return $this->owner->subscriptions()
            ->where('stripe_id', $stripeQuote->subscription->id)
            ->first();
    }

    /**
     * Get the subscription schedule created from this quote (if accepted with future start date).
     *
     * @return \Laravel\Cashier\SubscriptionSchedule|null
     */
    public function subscriptionSchedule()
    {
        if (! $this->accepted()) {
            return null;
        }

        $stripeQuote = $this->asStripeQuote(['subscription_schedule']);

        if (! $stripeQuote->subscription_schedule) {
            return null;
        }

        return $this->owner->subscriptionSchedules()
            ->where('stripe_id', $stripeQuote->subscription_schedule->id)
            ->first();
    }

    /**
     * Get the invoice created from this quote (if accepted as one-time payment).
     *
     * @return \Laravel\Cashier\Invoice|null
     */
    public function invoice()
    {
        if (! $this->accepted()) {
            return null;
        }

        $stripeQuote = $this->asStripeQuote(['invoice']);

        if (! $stripeQuote->invoice) {
            return null;
        }

        return $this->owner->findInvoice($stripeQuote->invoice->id);
    }

    /**
     * Get the quote number from Stripe.
     *
     * @return string|null
     */
    public function number()
    {
        $stripeQuote = $this->asStripeQuote();

        return $stripeQuote->number ?? null;
    }

    /**
     * Get a formatted quote number.
     *
     * @param  string  $format
     * @return string
     */
    public function formattedNumber($format = 'QT-%s')
    {
        $number = $this->number();

        return $number ? sprintf($format, $number) : null;
    }

    /**
     * Check if the quote has a custom number assigned.
     *
     * @return bool
     */
    public function hasCustomNumber()
    {
        $stripeQuote = $this->asStripeQuote();

        // Check if the number was explicitly set (non-null and not auto-generated)
        return ! empty($stripeQuote->number);
    }

    /**
     * Find a quote by its number.
     *
     * @param  mixed  $owner
     * @param  string  $number
     * @return static|null
     */
    public static function findByNumber($owner, $number)
    {
        // Search through the owner's quotes to find one with matching number
        $quotes = $owner->quotes;

        foreach ($quotes as $quote) {
            if ($quote->number() === $number) {
                return $quote;
            }
        }

        return null;
    }

    /**
     * Generate a unique quote reference for tracking.
     *
     * @return string
     */
    public function generateReference()
    {
        return 'quote_'.$this->id.'_'.substr($this->stripe_id, -8);
    }

    /**
     * Cancel the quote.
     */
    public function cancel(array $options = []): static
    {
        $stripeQuote = $this->asStripeQuote();
        $stripeQuote = $stripeQuote->cancel($options);

        $this->syncFromStripe($stripeQuote);

        return $this;
    }

    /**
     * Update the quote on Stripe.
     */
    public function updateStripeQuote(array $options = []): static
    {
        // Add billing mode for subscription quotes if set
        if ($billingMode = $this->getBillingModeForPayload()) {
            $options['subscription_data']['billing_mode'] = $billingMode;
        }

        $stripeQuote = $this->owner->stripe()->quotes->update($this->stripe_id, $options);

        $this->syncFromStripe($stripeQuote);

        return $this;
    }

    /**
     * Set the billing mode for this quote's future subscription.
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
     * Download the quote PDF.
     *
     * @param  array  $data
     * @param  string  $filename
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function downloadPdf(array $data = [], $filename = null)
    {
        $filename = $filename ?: 'quote-'.$this->id.'.pdf';

        return response()->streamDownload(function () {
            echo $this->owner->stripe()->quotes->pdf($this->stripe_id);
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Sync the quote with Stripe.
     *
     * @return $this
     */
    public function syncWithStripe()
    {
        $stripeQuote = $this->asStripeQuote();

        $this->syncFromStripe($stripeQuote);

        return $this;
    }

    /**
     * Sync the quote from a Stripe quote object.
     */
    protected function syncFromStripe(StripeQuote $stripeQuote): void
    {
        $this->fill([
            'status' => $stripeQuote->status,
            'number' => $stripeQuote->number,
            'amount_subtotal' => $stripeQuote->amount_subtotal,
            'amount_total' => $stripeQuote->amount_total,
            'currency' => $stripeQuote->currency,
            'expires_at' => $stripeQuote->expires_at
                ? Carbon::createFromTimestamp($stripeQuote->expires_at)
                : null,
            'status_transitions_finalized_at' => $stripeQuote->status_transitions->finalized_at
                ? Carbon::createFromTimestamp($stripeQuote->status_transitions->finalized_at)
                : null,
            'status_transitions_accepted_at' => $stripeQuote->status_transitions->accepted_at
                ? Carbon::createFromTimestamp($stripeQuote->status_transitions->accepted_at)
                : null,
            'status_transitions_canceled_at' => $stripeQuote->status_transitions->canceled_at
                ? Carbon::createFromTimestamp($stripeQuote->status_transitions->canceled_at)
                : null,
        ])->save();
    }

    /**
     * Get the quote as a Stripe quote object.
     */
    public function asStripeQuote(array $expand = []): StripeQuote
    {
        return $this->owner->stripe()->quotes->retrieve($this->stripe_id, ['expand' => $expand]);
    }

    /**
     * Get the default billing mode from config.
     */
    protected function getDefaultBillingMode(): string
    {
        return config('cashier.default_billing_mode', 'classic');
    }

    /**
     * Get the effective billing mode.
     */
    protected function getEffectiveBillingMode(): string
    {
        return $this->billingMode['type'] ?? $this->getDefaultBillingMode();
    }

    /**
     * Get the billing mode for the Stripe payload.
     */
    protected function getBillingModeForPayload(): ?array
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
     * @throws \InvalidArgumentException
     */
    protected function validateFlexibleBillingSupport(): void
    {
        $apiVersion = config('cashier.stripe.api_version') ?? \Stripe\Stripe::getApiVersion();

        if ($apiVersion && version_compare($apiVersion, '2025-06-30', '<')) {
            throw new \InvalidArgumentException(
                'Flexible billing mode requires Stripe API version 2025-06-30.basil or later. '.
                'Current version: '.$apiVersion.'. Please update your API version.'
            );
        }
    }
}

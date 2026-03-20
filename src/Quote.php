<?php

namespace Laravel\Cashier;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Cashier\Concerns\InteractsWithStripe;
use Laravel\Cashier\Database\Factories\QuoteFactory;
use Stripe\Quote as StripeQuote;

/**
 * @property \Laravel\Cashier\Billable&\Illuminate\Database\Eloquent\Model $owner
 */
class Quote extends Model
{
    use HasFactory;
    use InteractsWithStripe;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cashier_quotes';

    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'amount_subtotal' => 'integer',
        'amount_total' => 'integer',
        'expires_at' => 'datetime',
        'finalized_at' => 'datetime',
        'accepted_at' => 'datetime',
        'canceled_at' => 'datetime',
    ];

    /**
     * Get the user that owns the quote.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->owner();
    }

    /**
     * Get the model related to the quote.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function owner(): BelongsTo
    {
        $model = Cashier::$customerModel;

        return $this->belongsTo($model, (new $model)->getForeignKey());
    }

    /**
     * Determine if the quote is in draft status.
     *
     * @return bool
     */
    public function draft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Determine if the quote is open (finalized and awaiting customer action).
     *
     * @return bool
     */
    public function open(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Determine if the quote has been accepted.
     *
     * @return bool
     */
    public function accepted(): bool
    {
        return $this->status === 'accepted';
    }

    /**
     * Determine if the quote has been canceled.
     *
     * @return bool
     */
    public function canceled(): bool
    {
        return $this->status === 'canceled';
    }

    /**
     * Finalize the quote on Stripe.
     *
     * @param  array  $options
     * @return $this
     */
    public function finalize(array $options = [])
    {
        $stripeQuote = $this->owner->stripe()->quotes->finalizeQuote(
            $this->stripe_id,
            $options
        );

        $this->syncFromStripe($stripeQuote);

        return $this;
    }

    /**
     * Accept the quote on Stripe.
     *
     * @param  array  $options
     * @return $this
     */
    public function accept(array $options = [])
    {
        $stripeQuote = $this->owner->stripe()->quotes->accept(
            $this->stripe_id,
            $options
        );

        $this->syncFromStripe($stripeQuote);

        return $this;
    }

    /**
     * Cancel the quote on Stripe.
     *
     * @param  array  $options
     * @return $this
     */
    public function cancel(array $options = [])
    {
        $stripeQuote = $this->owner->stripe()->quotes->cancel(
            $this->stripe_id,
            $options
        );

        $this->syncFromStripe($stripeQuote);

        return $this;
    }

    /**
     * Update the quote on Stripe.
     *
     * @param  array  $options
     * @return $this
     */
    public function updateStripeQuote(array $options = [])
    {
        $stripeQuote = $this->owner->stripe()->quotes->update(
            $this->stripe_id,
            $options
        );

        $this->syncFromStripe($stripeQuote);

        return $this;
    }

    /**
     * Download the quote as a PDF.
     *
     * @param  string|null  $filename
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function downloadPdf(?string $filename = null)
    {
        $filename = $filename ?: 'quote-'.$this->stripe_id.'.pdf';

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
     * Sync the quote from a Stripe Quote object.
     *
     * @param  \Stripe\Quote  $stripeQuote
     * @return void
     */
    public function syncFromStripe(StripeQuote $stripeQuote): void
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
            'finalized_at' => isset($stripeQuote->status_transitions->finalized_at)
                ? Carbon::createFromTimestamp($stripeQuote->status_transitions->finalized_at)
                : null,
            'accepted_at' => isset($stripeQuote->status_transitions->accepted_at)
                ? Carbon::createFromTimestamp($stripeQuote->status_transitions->accepted_at)
                : null,
            'canceled_at' => isset($stripeQuote->status_transitions->canceled_at)
                ? Carbon::createFromTimestamp($stripeQuote->status_transitions->canceled_at)
                : null,
        ])->save();
    }

    /**
     * Get the quote as a Stripe Quote object.
     *
     * @param  array  $expand
     * @return \Stripe\Quote
     */
    public function asStripeQuote(array $expand = []): StripeQuote
    {
        return $this->owner->stripe()->quotes->retrieve(
            $this->stripe_id, ['expand' => $expand]
        );
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return QuoteFactory::new();
    }
}

<?php

namespace Laravel\Cashier;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;
use Laravel\Cashier\Concerns\InteractsWithStripe;
use Laravel\Cashier\Database\Factories\SubscriptionScheduleFactory;
use Stripe\SubscriptionSchedule as StripeSubscriptionSchedule;

/**
 * @property \Laravel\Cashier\Billable&\Illuminate\Database\Eloquent\Model $owner
 */
class SubscriptionSchedule extends Model
{
    use HasFactory;
    use InteractsWithStripe;

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
        'current_phase_started_at' => 'datetime',
        'current_phase_ends_at' => 'datetime',
        'canceled_at' => 'datetime',
        'completed_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    /**
     * Get the user that owns the subscription schedule.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->owner();
    }

    /**
     * Get the model related to the subscription schedule.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function owner(): BelongsTo
    {
        $model = Cashier::$customerModel;

        return $this->belongsTo($model, (new $model)->getForeignKey());
    }

    /**
     * Get the subscription associated with this schedule, if any.
     *
     * @return \Laravel\Cashier\Subscription|null
     */
    public function subscription(): ?Subscription
    {
        if (! $this->subscription_id) {
            return null;
        }

        return $this->owner->subscriptions()
            ->where('stripe_id', $this->subscription_id)
            ->first();
    }

    /**
     * Determine if the schedule is not yet started.
     *
     * @return bool
     */
    public function notStarted(): bool
    {
        return $this->stripe_status === 'not_started';
    }

    /**
     * Determine if the schedule is active.
     *
     * @return bool
     */
    public function active(): bool
    {
        return $this->stripe_status === 'active';
    }

    /**
     * Determine if the schedule has been completed.
     *
     * @return bool
     */
    public function completed(): bool
    {
        return $this->stripe_status === 'completed';
    }

    /**
     * Determine if the schedule has been released.
     *
     * @return bool
     */
    public function released(): bool
    {
        return $this->stripe_status === 'released';
    }

    /**
     * Determine if the schedule has been canceled.
     *
     * @return bool
     */
    public function canceled(): bool
    {
        return $this->stripe_status === 'canceled';
    }

    /**
     * Cancel the subscription schedule.
     *
     * @param  array  $options
     * @return $this
     */
    public function cancel(array $options = [])
    {
        $stripeSchedule = $this->owner->stripe()->subscriptionSchedules->cancel(
            $this->stripe_id,
            $options
        );

        $this->syncFromStripe($stripeSchedule);

        return $this;
    }

    /**
     * Release the subscription schedule.
     *
     * This detaches the schedule from the subscription, leaving the subscription
     * running in its current configuration.
     *
     * @param  array  $options
     * @return $this
     */
    public function release(array $options = [])
    {
        $stripeSchedule = $this->owner->stripe()->subscriptionSchedules->release(
            $this->stripe_id,
            $options
        );

        $this->syncFromStripe($stripeSchedule);

        return $this;
    }

    /**
     * Update the subscription schedule on Stripe.
     *
     * @param  array  $options
     * @return $this
     */
    public function updateSchedule(array $options = [])
    {
        $stripeSchedule = $this->owner->stripe()->subscriptionSchedules->update(
            $this->stripe_id,
            $options
        );

        $this->syncFromStripe($stripeSchedule);

        return $this;
    }

    /**
     * Get the phases from the Stripe subscription schedule.
     *
     * @return array
     */
    public function phases(): array
    {
        $stripeSchedule = $this->asStripeSubscriptionSchedule();

        return $stripeSchedule->phases ?? [];
    }

    /**
     * Get the current phase from the Stripe subscription schedule.
     *
     * @return object|null
     */
    public function currentPhase(): ?object
    {
        $stripeSchedule = $this->asStripeSubscriptionSchedule();

        return $stripeSchedule->current_phase;
    }

    /**
     * Sync the schedule with Stripe.
     *
     * @return $this
     */
    public function syncWithStripe()
    {
        $stripeSchedule = $this->asStripeSubscriptionSchedule();

        $this->syncFromStripe($stripeSchedule);

        return $this;
    }

    /**
     * Sync from a Stripe subscription schedule object.
     *
     * @param  \Stripe\SubscriptionSchedule  $stripeSchedule
     * @return void
     */
    public function syncFromStripe(StripeSubscriptionSchedule $stripeSchedule): void
    {
        $this->fill([
            'stripe_status' => $stripeSchedule->status,
            'subscription_id' => $stripeSchedule->subscription,
            'current_phase_started_at' => $stripeSchedule->current_phase
                ? Carbon::createFromTimestamp($stripeSchedule->current_phase->start_date)
                : null,
            'current_phase_ends_at' => $stripeSchedule->current_phase
                ? Carbon::createFromTimestamp($stripeSchedule->current_phase->end_date)
                : null,
            'canceled_at' => $stripeSchedule->canceled_at
                ? Carbon::createFromTimestamp($stripeSchedule->canceled_at)
                : null,
            'completed_at' => $stripeSchedule->completed_at
                ? Carbon::createFromTimestamp($stripeSchedule->completed_at)
                : null,
            'released_at' => $stripeSchedule->released_at
                ? Carbon::createFromTimestamp($stripeSchedule->released_at)
                : null,
        ])->save();
    }

    /**
     * Get the subscription schedule as a Stripe object.
     *
     * @param  array  $expand
     * @return \Stripe\SubscriptionSchedule
     */
    public function asStripeSubscriptionSchedule(array $expand = []): StripeSubscriptionSchedule
    {
        return $this->owner->stripe()->subscriptionSchedules->retrieve(
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
        return SubscriptionScheduleFactory::new();
    }
}

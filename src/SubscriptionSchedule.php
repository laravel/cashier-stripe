<?php

namespace Laravel\Cashier;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Laravel\Cashier\Concerns\InteractsWithStripe;
use Stripe\SubscriptionSchedule as StripeSubscriptionSchedule;

class SubscriptionSchedule extends Model
{
    use InteractsWithStripe;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'subscription_schedules';

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
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'current_phase_started_at' => 'datetime',
        'current_phase_ends_at' => 'datetime',
        'released_at' => 'datetime',
        'canceled_at' => 'datetime',
        'completed_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the user that owns the subscription schedule.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->owner();
    }

    /**
     * Get the model related to the subscription schedule.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function owner()
    {
        $model = config('cashier.customer_model', config('auth.providers.users.model', 'App\\Models\\User'));

        return $this->belongsTo($model, (new $model)->getForeignKey());
    }

    /**
     * Get the subscription associated with this schedule if it exists.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function subscription()
    {
        return $this->belongsTo(Subscription::class, 'subscription_id', 'stripe_id');
    }

    /**
     * Determine if the subscription schedule is active.
     *
     * @return bool
     */
    public function active()
    {
        return $this->stripe_status === 'active';
    }

    /**
     * Determine if the subscription schedule is not started.
     *
     * @return bool
     */
    public function notStarted()
    {
        return $this->stripe_status === 'not_started';
    }

    /**
     * Determine if the subscription schedule is released.
     *
     * @return bool
     */
    public function released()
    {
        return $this->stripe_status === 'released';
    }

    /**
     * Determine if the subscription schedule is canceled.
     *
     * @return bool
     */
    public function canceled()
    {
        return $this->stripe_status === 'canceled';
    }

    /**
     * Determine if the subscription schedule is completed.
     *
     * @return bool
     */
    public function completed()
    {
        return $this->stripe_status === 'completed';
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

        $this->fill([
            'stripe_status' => $stripeSchedule->status,
            'canceled_at' => $stripeSchedule->canceled_at ? Carbon::createFromTimestamp($stripeSchedule->canceled_at) : null,
        ])->save();

        return $this;
    }

    /**
     * Release the subscription schedule.
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

        // Refresh the schedule to get the latest data including subscription ID
        $stripeSchedule = $this->owner->stripe()->subscriptionSchedules->retrieve($this->stripe_id);

        $this->fill([
            'stripe_status' => $stripeSchedule->status,
            'subscription_id' => $stripeSchedule->subscription,
            'released_at' => $stripeSchedule->released_at ? Carbon::createFromTimestamp($stripeSchedule->released_at) : null,
        ])->save();

        return $this;
    }

    /**
     * Update the subscription schedule model and sync with Stripe.
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        $result = parent::update($attributes, $options);

        // If metadata was updated, sync with Stripe
        if (isset($attributes['metadata'])) {
            $this->updateSchedule(['metadata' => $attributes['metadata']]);
        }

        return $result;
    }

    /**
     * Update the subscription schedule.
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
     * Sync the subscription schedule with Stripe.
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
     * Sync the subscription schedule from a Stripe schedule object.
     *
     * @param  \Stripe\SubscriptionSchedule  $stripeSchedule
     * @return void
     */
    protected function syncFromStripe(StripeSubscriptionSchedule $stripeSchedule)
    {
        $currentPhase = $stripeSchedule->current_phase ?? null;

        $this->fill([
            'stripe_status' => $stripeSchedule->status,
            'subscription_id' => $stripeSchedule->subscription
                ? (is_object($stripeSchedule->subscription) ? $stripeSchedule->subscription->id : $stripeSchedule->subscription)
                : null,
            'current_phase_started_at' => $currentPhase && isset($currentPhase->start_date)
                ? Carbon::createFromTimestamp($currentPhase->start_date)
                : null,
            'current_phase_ends_at' => $currentPhase && isset($currentPhase->end_date)
                ? Carbon::createFromTimestamp($currentPhase->end_date)
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
            'metadata' => isset($stripeSchedule->metadata) && $stripeSchedule->metadata
                ? (is_object($stripeSchedule->metadata) ? $stripeSchedule->metadata->toArray() : $stripeSchedule->metadata)
                : null,
        ])->save();
    }

    /**
     * Get the subscription schedule as a Stripe subscription schedule object.
     *
     * @param  array  $expand
     * @return \Stripe\SubscriptionSchedule
     */
    public function asStripeSubscriptionSchedule(array $expand = [])
    {
        return $this->owner->stripe()->subscriptionSchedules->retrieve(
            $this->stripe_id,
            ['expand' => $expand]
        );
    }

    /**
     * Get upcoming invoice preview for this subscription schedule.
     *
     * @param  array  $options
     * @return \Laravel\Cashier\Invoice
     */
    public function upcomingInvoicePreview(array $options = [])
    {
        if (! $this->active() && ! $this->notStarted()) {
            throw new InvalidArgumentException('Cannot preview invoices for inactive subscription schedules.');
        }

        $stripeInvoice = $this->owner->stripe()->invoices->upcoming(
            array_merge([
                'customer' => $this->owner->stripe_id,
                'subscription_schedule' => $this->stripe_id,
            ], $options)
        );

        return new Invoice($this->owner, $stripeInvoice);
    }

    /**
     * Preview the next invoice that will be generated by this schedule.
     *
     * @param  array  $options
     * @return \Laravel\Cashier\Invoice|null
     */
    public function previewNextInvoice(array $options = [])
    {
        try {
            return $this->upcomingInvoicePreview($options);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            // If no upcoming invoice exists, return null
            if (str_contains($e->getMessage(), 'No upcoming invoice')) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Preview invoice for a specific phase transition.
     *
     * @param  int  $phaseIndex
     * @param  array  $options
     * @return \Laravel\Cashier\Invoice|null
     */
    public function previewPhaseTransitionInvoice($phaseIndex, array $options = [])
    {
        $schedule = $this->asStripeSubscriptionSchedule(['phases']);

        if (! isset($schedule->phases[$phaseIndex])) {
            throw new InvalidArgumentException("Phase index {$phaseIndex} does not exist on this schedule.");
        }

        $phase = $schedule->phases[$phaseIndex];

        // Calculate the preview date for this phase
        $previewDate = isset($phase->start_date) ? $phase->start_date : null;

        if (! $previewDate) {
            return null;
        }

        try {
            $stripeInvoice = $this->owner->stripe()->invoices->upcoming(
                array_merge([
                    'customer' => $this->owner->stripe_id,
                    'subscription_schedule' => $this->stripe_id,
                    'subscription_preview_items' => $this->formatPhaseItemsForPreview($phase->items ?? []),
                ], $options)
            );

            return new Invoice($this->owner, $stripeInvoice);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            if (str_contains($e->getMessage(), 'No upcoming invoice')) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Get all phases from the Stripe schedule.
     *
     * @return array
     */
    public function phases()
    {
        $schedule = $this->asStripeSubscriptionSchedule(['phases']);

        return $schedule->phases->data ?? [];
    }

    /**
     * Get the current phase information.
     *
     * @return \stdClass|null
     */
    public function currentPhase()
    {
        $schedule = $this->asStripeSubscriptionSchedule(['current_phase']);

        return $schedule->current_phase ?? null;
    }

    /**
     * Get the remaining phases in the schedule.
     *
     * @return array
     */
    public function remainingPhases()
    {
        $phases = $this->phases();
        $currentTime = time();

        return array_filter($phases, function ($phase) use ($currentTime) {
            return isset($phase->start_date) && $phase->start_date > $currentTime;
        });
    }

    /**
     * Update a specific phase in the subscription schedule.
     *
     * @param  int  $phaseIndex
     * @param  array  $phaseData
     * @return $this
     */
    public function updatePhase($phaseIndex, array $phaseData)
    {
        $schedule = $this->asStripeSubscriptionSchedule(['phases']);
        $phases = $schedule->phases->data ?? [];

        if (! isset($phases[$phaseIndex])) {
            throw new InvalidArgumentException("Phase index {$phaseIndex} does not exist on this schedule.");
        }

        // Update the specific phase
        $updatedPhases = [];
        foreach ($phases as $index => $phase) {
            if ($index === $phaseIndex) {
                $updatedPhases[] = array_merge((array) $phase, $phaseData);
            } else {
                $updatedPhases[] = (array) $phase;
            }
        }

        return $this->updateSchedule(['phases' => $updatedPhases]);
    }

    /**
     * Format phase items for invoice preview.
     *
     * @param  array  $items
     * @return array
     */
    protected function formatPhaseItemsForPreview(array $items)
    {
        return array_map(function ($item) {
            return [
                'price' => $item['price'] ?? null,
                'quantity' => $item['quantity'] ?? 1,
            ];
        }, $items);
    }
}

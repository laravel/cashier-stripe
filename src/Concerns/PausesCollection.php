<?php

namespace Laravel\Cashier\Concerns;

use Carbon\Carbon;
use LogicException;
use Stripe\StripeObject;
use Stripe\Subscription;

/**
 * @property string|null $pause_behavior
 * @property \Carbon\Carbon|null $resumes_at
 */
trait PausesCollection
{

    /**
     * Pause subscription.
     *
     * @param  string  $behavior
     * @param  \Carbon\Carbon|null  $resumesAt
     * @return static
     * @throws \LogicException
     * @throws \Stripe\Exception\ApiErrorException
     */
    public function pause(string $behavior, ?Carbon $resumesAt = null)
    {
        if ($this->cancelled()) {
            throw new LogicException('Unable to pause subscription that is cancelled.');
        }

        $payload = ['behavior' => $behavior];
        if ($resumesAt) {
            $payload['resumes_at'] = $resumesAt->timestamp;
        }
        $stripeSubscription = $this->updateStripeSubscription(['pause_collection' => $payload]);

        $this->fillPauseCollectionFields($stripeSubscription)
            ->save();

        return $this;
    }

    /**
     * Pause with behavior mark_uncollectible.
     *
     * @param  \Carbon\Carbon|null  $resumesAt
     * @return static
     * @throws \LogicException
     * @throws \Stripe\Exception\ApiErrorException
     */
    public function pauseBehaviorMarkUncollectible(?Carbon $resumesAt = null)
    {
        return $this->pause(static::PAUSE_BEHAVIOR_MARK_UNCOLLECTIBLE, $resumesAt);
    }

    /**
     * Pause with behavior keep_as_draft.
     *
     * @param  \Carbon\Carbon|null  $resumesAt
     * @return static
     * @throws \LogicException
     * @throws \Stripe\Exception\ApiErrorException
     */
    public function pauseBehaviorKeepAsDraft(?Carbon $resumesAt = null)
    {
        return $this->pause(static::PAUSE_BEHAVIOR_KEEP_AS_DRAFT, $resumesAt);
    }

    /**
     * Pause with behavior void.
     *
     * @param  \Carbon\Carbon|null  $resumesAt
     * @return static
     * @throws \LogicException
     * @throws \Stripe\Exception\ApiErrorException
     */
    public function pauseBehaviorVoid(?Carbon $resumesAt = null)
    {
        return $this->pause(static::PAUSE_BEHAVIOR_VOID, $resumesAt);
    }

    /**
     * Resume the paused subscription.
     *
     * @return static
     * @throws \LogicException
     * @throws \Stripe\Exception\ApiErrorException
     */
    public function unpause()
    {
        if (! $this->paused()) {
            throw new LogicException('Unable to unpause subscription that is not paused.');
        }

        $stripeSubscription = $this->updateStripeSubscription(['pause_collection' => '']);

        $this->fillPauseCollectionFields($stripeSubscription)
            ->save();

        return $this;
    }

    /**
     * Retrieve and save the Stripe pause_collection of the subscription.
     *
     * @return static
     */
    public function syncStripePauseCollection()
    {
        $this->fillPauseCollectionFields($this->asStripeSubscription())
            ->save();

        return $this;
    }

    /**
     * Check is current subscription paused.
     *
     * @param  string|null  $behavior - Check specific behavior, if null will check any behavior.
     * @return bool
     */
    public function paused(?string $behavior = null)
    {
        $hasBehavior = ! is_null($this->pause_behavior);

        if ($behavior) {
            return $hasBehavior && $this->pause_behavior === $behavior;
        }

        return $hasBehavior;
    }

    /**
     * Filter query by pause_collection behavior.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string|null  $behavior
     * @return void
     */
    public function scopePaused($query, ?string $behavior = null)
    {
        if (! $behavior) {
            $query->whereNotNull('pause_behavior');

            return;
        }

        $query->where('pause_behavior', '=', $behavior);
    }

    /**
     * Check is current subscription not paused.
     *
     * @param  string|null  $behavior - Check specific behavior, if null will check any behavior.
     * @return bool
     */
    public function notPaused(?string $behavior = null)
    {
        return ! $this->paused($behavior);
    }

    /**
     * Filter query by not paused payment collection.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string|null  $behavior
     * @return void
     */
    public function scopeNotPaused($query, ?string $behavior = null)
    {
        if (! $behavior) {
            $query->whereNull('pause_behavior');

            return;
        }

        $query->where(function ($query) use ($behavior) {
            $query->where('pause_behavior', '!=', $behavior)
                ->orWhereNull('pause_behavior');
        });
    }

    /**
     * Get auto pause resumes datetime.
     *
     * @param  string|null  $behavior - Check specific behavior, if null will check any behavior.
     * @return \Carbon\Carbon|null
     */
    public function pauseResumesAt(?string $behavior = null)
    {
        if (! $this->paused($behavior) || empty($this->resumes_at)) {
            return null;
        }

        return $this->resumes_at;
    }

    /**
     * Fill to model pause collection attributes.
     *
     * @param  \Stripe\Subscription  $stripeSubscription
     * @return static
     * @throws \Illuminate\Database\Eloquent\MassAssignmentException
     */
    public function fillPauseCollectionFields(Subscription $stripeSubscription)
    {
        $fields = [
            'pause_behavior' => null,
            'resumes_at' => null,
        ];

        if ($stripeSubscription->pause_collection instanceof StripeObject) {
            $fields = [
                'pause_behavior' => $stripeSubscription->pause_collection->behavior ?? null,
                'resumes_at' => $stripeSubscription->pause_collection->resumes_at
                    ? Carbon::createFromTimestamp($stripeSubscription->pause_collection->resumes_at)
                    : null,
            ];
        }

        return $this->fill($fields);
    }
}

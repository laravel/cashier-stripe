<?php

namespace Laravel\Cashier;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Traits\Conditionable;
use InvalidArgumentException;
use Laravel\Cashier\Concerns\InteractsWithStripe;
use Laravel\Cashier\Concerns\ManagesBillingMode;
use Stripe\SubscriptionSchedule as StripeSubscriptionSchedule;

class SubscriptionScheduleBuilder
{
    use Conditionable;
    use InteractsWithStripe;
    use ManagesBillingMode;

    /**
     * The model that is creating the schedule.
     *
     * @var \Laravel\Cashier\Billable|\Illuminate\Database\Eloquent\Model
     */
    protected $owner;

    /**
     * The type of the subscription schedule.
     *
     * @var string
     */
    protected string $type;

    /**
     * The phases for the subscription schedule.
     *
     * @var array
     */
    protected array $phases = [];

    /**
     * The start date for the schedule.
     *
     * @var int|string|null
     */
    protected int|string|null $startDate = null;

    /**
     * The end behavior for the schedule.
     *
     * @var string
     */
    protected string $endBehavior = 'release';

    /**
     * The metadata to apply to the schedule.
     *
     * @var array
     */
    protected array $metadata = [];

    /**
     * The default settings for the schedule.
     *
     * @var array
     */
    protected array $defaultSettings = [];

    /**
     * Create a new subscription schedule builder instance.
     *
     * @param  mixed  $owner
     * @param  string  $type
     * @return void
     */
    public function __construct($owner, string $type = 'default')
    {
        $this->owner = $owner;
        $this->type = $type;
    }

    /**
     * Add a phase to the subscription schedule.
     *
     * @param  array  $items
     * @param  array  $options
     * @return $this
     */
    public function addPhase(array $items, array $options = [])
    {
        $phase = array_merge([
            'items' => $this->normalizePhaseItems($items),
        ], $options);

        $this->phases[] = $phase;

        return $this;
    }

    /**
     * Set the start date for the schedule.
     *
     * @param  \DateTimeInterface|int|string  $date
     * @return $this
     */
    public function startDate(DateTimeInterface|int|string $date)
    {
        if ($date instanceof DateTimeInterface) {
            $this->startDate = $date->getTimestamp();
        } elseif (is_string($date) && $date !== 'now') {
            $this->startDate = Carbon::parse($date)->getTimestamp();
        } else {
            $this->startDate = $date;
        }

        return $this;
    }

    /**
     * Set the end behavior for the schedule.
     *
     * @param  'release'|'cancel'|'none'  $behavior
     * @return $this
     */
    public function endBehavior(string $behavior)
    {
        if (! in_array($behavior, ['release', 'cancel', 'none'])) {
            throw new InvalidArgumentException("Invalid end behavior [{$behavior}]. Must be 'release', 'cancel', or 'none'.");
        }

        $this->endBehavior = $behavior;

        return $this;
    }

    /**
     * Set metadata for the schedule.
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
     * Set default settings for the schedule phases.
     *
     * @param  array  $settings
     * @return $this
     */
    public function withDefaultSettings(array $settings)
    {
        $this->defaultSettings = $settings;

        return $this;
    }

    /**
     * Create the subscription schedule on Stripe.
     *
     * @param  array  $options
     * @return \Laravel\Cashier\SubscriptionSchedule
     */
    public function create(array $options = [])
    {
        if (empty($this->phases)) {
            throw new InvalidArgumentException('At least one phase is required when creating subscription schedules.');
        }

        $stripeCustomer = $this->owner->createOrGetStripeCustomer();

        $payload = array_filter([
            'customer' => $stripeCustomer->id,
            'phases' => $this->buildPhases(),
            'start_date' => $this->startDate,
            'end_behavior' => $this->endBehavior,
            'metadata' => array_merge($this->metadata, [
                'type' => $this->type,
            ]),
        ]);

        if (! empty($this->defaultSettings)) {
            $payload['default_settings'] = $this->defaultSettings;
        }

        if ($billingMode = $this->getBillingModeForPayload()) {
            $payload['billing_mode'] = $billingMode;
        }

        $payload = array_merge($payload, $options);

        $stripeSchedule = $this->owner->stripe()->subscriptionSchedules->create($payload);

        return $this->createSchedule($stripeSchedule);
    }

    /**
     * Create a subscription schedule from an existing subscription.
     *
     * Note: billing_mode is inherited from the source subscription and
     * must NOT be set explicitly — Stripe will reject the request.
     * Any billing mode set via withBillingMode() is intentionally ignored.
     *
     * @param  \Laravel\Cashier\Subscription  $subscription
     * @param  array  $options
     * @return \Laravel\Cashier\SubscriptionSchedule
     */
    public function createFromSubscription(Subscription $subscription, array $options = [])
    {
        $payload = array_merge([
            'from_subscription' => $subscription->stripe_id,
        ], $options);

        $stripeSchedule = $this->owner->stripe()->subscriptionSchedules->create($payload);

        return $this->createSchedule($stripeSchedule);
    }

    /**
     * Create the Eloquent SubscriptionSchedule.
     *
     * @param  \Stripe\SubscriptionSchedule  $stripeSchedule
     * @return \Laravel\Cashier\SubscriptionSchedule
     */
    protected function createSchedule(StripeSubscriptionSchedule $stripeSchedule)
    {
        if ($schedule = $this->owner->subscriptionSchedules()->where('stripe_id', $stripeSchedule->id)->first()) {
            $schedule->syncFromStripe($stripeSchedule);

            return $schedule;
        }

        /** @var \Laravel\Cashier\SubscriptionSchedule $schedule */
        $schedule = $this->owner->subscriptionSchedules()->create([
            'type' => $this->type,
            'stripe_id' => $stripeSchedule->id,
            'stripe_status' => $stripeSchedule->status,
            'subscription_id' => $stripeSchedule->subscription,
            'current_phase_started_at' => $stripeSchedule->current_phase
                ? Carbon::createFromTimestamp($stripeSchedule->current_phase->start_date)
                : null,
            'current_phase_ends_at' => $stripeSchedule->current_phase
                ? Carbon::createFromTimestamp($stripeSchedule->current_phase->end_date)
                : null,
        ]);

        return $schedule;
    }

    /**
     * Build the phases for the Stripe payload.
     *
     * @return array
     */
    protected function buildPhases(): array
    {
        return $this->phases;
    }

    /**
     * Normalize phase items into the correct format.
     *
     * @param  array  $items
     * @return array
     */
    protected function normalizePhaseItems(array $items): array
    {
        return collect($items)->map(function ($item) {
            if (is_string($item)) {
                return ['price' => $item, 'quantity' => 1];
            }

            if (is_array($item) && isset($item['price'])) {
                return $item;
            }

            return $item;
        })->values()->all();
    }
}

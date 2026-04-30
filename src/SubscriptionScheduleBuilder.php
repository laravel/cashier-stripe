<?php

namespace Laravel\Cashier;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Traits\Conditionable;
use InvalidArgumentException;
use Laravel\Cashier\Concerns\HandlesTaxes;
use Laravel\Cashier\Concerns\InteractsWithStripe;
use Stripe\SubscriptionSchedule as StripeSubscriptionSchedule;

class SubscriptionScheduleBuilder
{
    use Conditionable;
    use HandlesTaxes;
    use InteractsWithStripe;

    /**
     * The model that is creating the subscription schedule.
     */
    protected mixed $owner;

    /**
     * The subscription to schedule from (if any).
     */
    protected ?string $fromSubscription = null;

    /**
     * The start date for the subscription schedule.
     */
    protected ?\Carbon\CarbonInterface $startDate = null;

    /**
     * The end behavior for the subscription schedule.
     */
    protected string $endBehavior = 'release';

    /**
     * The phases for the subscription schedule.
     */
    protected array $phases = [];

    /**
     * The billing mode for the subscription schedule.
     */
    protected ?array $billingMode = null;

    /**
     * The metadata to apply to the subscription schedule.
     */
    protected array $metadata = [];

    /**
     * Create a new subscription schedule builder instance.
     */
    public function __construct(mixed $owner)
    {
        $this->owner = $owner;
    }

    /**
     * Create the subscription schedule from an existing subscription.
     */
    public function fromSubscription(string $subscriptionId): static
    {
        $this->fromSubscription = $subscriptionId;

        return $this;
    }

    /**
     * Set the start date for the subscription schedule.
     */
    public function startDate(DateTimeInterface|int $date): static
    {
        if ($date instanceof DateTimeInterface) {
            $this->startDate = Carbon::instance($date);
        } else {
            $this->startDate = Carbon::createFromTimestamp($date);
        }

        return $this;
    }

    /**
     * Set the end behavior for the subscription schedule.
     */
    public function endBehavior(string $behavior): static
    {
        $this->endBehavior = $behavior;

        return $this;
    }

    /**
     * Set the billing mode for the subscription schedule.
     */
    public function withBillingMode(string $type = 'flexible'): static
    {
        $this->billingMode = ['type' => $type];

        return $this;
    }

    /**
     * Add a phase to the subscription schedule.
     */
    public function addPhase(array $phase): static
    {
        $this->validatePhaseLimit();
        $this->phases[] = $this->processPhaseOptions($phase);

        return $this;
    }

    /**
     * Add a phase with advanced configuration options.
     */
    public function addPhaseWithOptions(array $items, array $options = []): static
    {
        $this->validatePhaseLimit();

        $phase = array_merge([
            'items' => $items,
        ], $options);

        $this->phases[] = $this->processPhaseOptions($phase);

        return $this;
    }

    /**
     * Add a phase with proration behavior configuration.
     */
    public function addPhaseWithProration(array $items, ?string $prorationBehavior = null, array $options = []): static
    {
        $this->validatePhaseLimit();

        $phase = array_merge([
            'items' => $items,
        ], $options);

        if ($prorationBehavior) {
            $phase['proration_behavior'] = $prorationBehavior;
        }

        $this->phases[] = $this->processPhaseOptions($phase);

        return $this;
    }

    /**
     * Add a phase with metadata.
     */
    public function addPhaseWithMetadata(array $items, array $metadata, array $options = []): static
    {
        $this->validatePhaseLimit();

        $phase = array_merge([
            'items' => $items,
            'metadata' => $metadata,
        ], $options);

        $this->phases[] = $this->processPhaseOptions($phase);

        return $this;
    }

    /**
     * Add a phase with trial period.
     *
     * @param  array  $items
     * @param  \DateTimeInterface|int  $trialEnd
     * @param  array  $options
     * @return $this
     */
    public function addTrialPhase(array $items, $trialEnd, array $options = [])
    {
        $this->validatePhaseLimit();

        $trialEndTimestamp = $trialEnd instanceof DateTimeInterface
            ? $trialEnd->getTimestamp()
            : $trialEnd;

        $phase = array_merge([
            'items' => $items,
            'trial_end' => $trialEndTimestamp,
        ], $options);

        $this->phases[] = $this->processPhaseOptions($phase);

        return $this;
    }

    /**
     * Set the phases for the subscription schedule.
     *
     * @param  array  $phases
     * @return $this
     */
    public function phases(array $phases)
    {
        if (count($phases) > 10) {
            throw new InvalidArgumentException('Subscription schedules can have a maximum of 10 phases.');
        }

        $this->phases = array_map([$this, 'processPhaseOptions'], $phases);

        return $this;
    }

    /**
     * Validate that we haven't exceeded the phase limit.
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    protected function validatePhaseLimit()
    {
        if (count($this->phases) >= 10) {
            throw new InvalidArgumentException('Subscription schedules can have a maximum of 10 phases.');
        }
    }

    /**
     * Process phase options to ensure proper formatting.
     *
     * @param  array  $phase
     * @return array
     */
    protected function processPhaseOptions(array $phase)
    {
        // Remove unsupported parameters that might be present
        unset($phase['start_date']); // start_date is set at the schedule level, not phase level

        // Validate proration behavior if set
        if (isset($phase['proration_behavior'])) {
            $validProrationBehaviors = ['create_prorations', 'none', 'always_invoice'];
            if (! in_array($phase['proration_behavior'], $validProrationBehaviors)) {
                throw new InvalidArgumentException(
                    'Invalid proration behavior. Must be one of: '.implode(', ', $validProrationBehaviors)
                );
            }
        }

        // Process invoice settings if present
        if (isset($phase['invoice_settings'])) {
            $phase['invoice_settings'] = $this->processInvoiceSettings($phase['invoice_settings']);
        }

        // Process automatic tax if present
        if (isset($phase['automatic_tax'])) {
            $phase['automatic_tax'] = $this->processAutomaticTax($phase['automatic_tax']);
        }

        return $phase;
    }

    /**
     * Process invoice settings for a phase.
     *
     * @param  array  $settings
     * @return array
     */
    protected function processInvoiceSettings(array $settings)
    {
        // Validate account tax ids format if present
        if (isset($settings['account_tax_ids'])) {
            $settings['account_tax_ids'] = (array) $settings['account_tax_ids'];
        }

        return $settings;
    }

    /**
     * Process automatic tax settings for a phase.
     *
     * @param  array  $settings
     * @return array
     */
    protected function processAutomaticTax(array $settings)
    {
        // Ensure enabled is boolean
        if (isset($settings['enabled'])) {
            $settings['enabled'] = (bool) $settings['enabled'];
        }

        return $settings;
    }

    /**
     * The metadata to apply to the subscription schedule.
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
     * Create a new Stripe subscription schedule.
     *
     * @throws \Exception
     */
    public function create(array $options = []): \Laravel\Cashier\SubscriptionSchedule
    {
        if ($this->fromSubscription) {
            // When creating from existing subscription, only set minimal required parameters
            $payload = array_merge([
                'from_subscription' => $this->fromSubscription,
                'metadata' => $this->metadata,
            ], $options);
        } else {
            // Full payload for new subscription schedules
            $stripeCustomer = $this->owner->createOrGetStripeCustomer();
            $payload = array_merge([
                'customer' => $stripeCustomer->id,
                'start_date' => $this->getStartDateForPayload(),
                'end_behavior' => $this->endBehavior,
                'phases' => $this->getPhases(),
                'metadata' => $this->metadata,
            ], $options);
        }

        if ($billingMode = $this->getBillingModeForPayload()) {
            $payload['billing_mode'] = $billingMode;
        }

        $payload = array_filter($payload, function ($value) {
            return $value !== null && $value !== [];
        });

        $stripeSchedule = $this->owner->stripe()->subscriptionSchedules->create($payload);

        return $this->createSubscriptionSchedule($stripeSchedule);
    }

    /**
     * Create the Eloquent SubscriptionSchedule.
     */
    protected function createSubscriptionSchedule(StripeSubscriptionSchedule $stripeSchedule): \Laravel\Cashier\SubscriptionSchedule
    {
        if ($schedule = $this->owner->subscriptionSchedules()->where('stripe_id', $stripeSchedule->id)->first()) {
            return $schedule;
        }

        $currentPhase = $stripeSchedule->current_phase ?? null;

        /** @var \Laravel\Cashier\SubscriptionSchedule $schedule */
        $schedule = $this->owner->subscriptionSchedules()->create([
            'stripe_id' => $stripeSchedule->id,
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
        ]);

        return $schedule;
    }

    /**
     * Get the start date for the Stripe payload.
     */
    protected function getStartDateForPayload(): int|string
    {
        if ($this->startDate) {
            return $this->startDate->getTimestamp();
        }

        if ($this->fromSubscription) {
            return 'now';
        }

        // Default to 'now' if no start date is specified
        return 'now';
    }

    /**
     * Get the phases for the subscription schedule.
     */
    protected function getPhases(): array
    {
        if (empty($this->phases) && ! $this->fromSubscription) {
            throw new InvalidArgumentException('At least one phase is required when creating subscription schedules.');
        }

        return $this->phases;
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

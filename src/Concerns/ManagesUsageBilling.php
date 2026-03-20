<?php

namespace Laravel\Cashier\Concerns;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Cashier\UsageThreshold;

trait ManagesUsageBilling
{
    use InteractsWithStripe;

    /**
     * Get all of the defined billing meters.
     *
     * @param  array  $options
     * @param  array  $requestOptions
     * @return \Illuminate\Support\Collection
     */
    public function meters(array $options = [], array $requestOptions = []): Collection
    {
        return new Collection($this->stripe()->billing->meters->all($options, $requestOptions)->data);
    }

    /**
     * Report usage for a metered product.
     *
     * @param  string  $meter
     * @param  int  $quantity
     * @param  string|null  $price
     * @param  array  $options
     * @param  array  $requestOptions
     * @return \Stripe\V2\Billing\MeterEvent
     */
    public function reportMeterEvent(string $meter, int $quantity = 1, array $options = [], array $requestOptions = [])
    {
        $this->assertCustomerExists();

        /** @var \Stripe\Service\V2\Billing\MeterEventService $meterEventsService */
        $meterEventsService = static::stripe()->v2->billing->meterEvents;

        return $meterEventsService->create([
            'event_name' => $meter,
            'payload' => [
                'stripe_customer_id' => $this->stripeId(),
                'value' => (string) $quantity,
            ],
            'identifier' => Str::uuid()->toString(),
            ...$options,
        ], $requestOptions);
    }

    /**
     * Get the usage records for a meter using its ID.
     *
     * @param  string  $meterId
     * @param  array  $options
     * @param  array  $requestOptions
     * @return \Illuminate\Support\Collection
     */
    public function meterEventSummaries(string $meterId, int $startTime = 1, ?int $endTime = null, array $options = [], array $requestOptions = []): Collection
    {
        $this->assertCustomerExists();

        if (! isset($endTime)) {
            $endTime = time();
        }

        /** @var \Stripe\Service\Billing\MeterService $metersService */
        $metersService = static::stripe()->billing->meters;

        return new Collection($metersService->allEventSummaries(
            $meterId,
            [
                'customer' => $this->stripeId(),
                'start_time' => $startTime,
                'end_time' => $endTime,
                ...$options,
            ],
            $requestOptions
        )->data);
    }

    /**
     * Get all usage thresholds for this customer.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function usageThresholds(): HasMany
    {
        return $this->hasMany(UsageThreshold::class, $this->getForeignKey());
    }

    /**
     * Set a usage threshold for a meter.
     *
     * @param  string  $meterId
     * @param  int  $threshold
     * @param  string  $period
     * @param  array  $alertOptions
     * @return \Laravel\Cashier\UsageThreshold
     *
     * @throws \InvalidArgumentException
     */
    public function setUsageThreshold(string $meterId, int $threshold, string $period = 'billing_cycle', array $alertOptions = []): UsageThreshold
    {
        if ($threshold <= 0) {
            throw new InvalidArgumentException('Usage threshold must be a positive integer.');
        }

        $validPeriods = ['billing_cycle', 'monthly', 'daily', 'weekly'];

        if (! in_array($period, $validPeriods)) {
            throw new InvalidArgumentException('Invalid period. Must be one of: '.implode(', ', $validPeriods));
        }

        return $this->usageThresholds()->updateOrCreate(
            ['meter_id' => $meterId],
            [
                'threshold' => $threshold,
                'period' => $period,
                'alert_options' => ! empty($alertOptions) ? $alertOptions : null,
            ]
        );
    }

    /**
     * Get the usage threshold for a specific meter.
     *
     * @param  string  $meterId
     * @return \Laravel\Cashier\UsageThreshold|null
     */
    public function getUsageThreshold(string $meterId): ?UsageThreshold
    {
        return $this->usageThresholds()->where('meter_id', $meterId)->first();
    }

    /**
     * Remove the usage threshold for a meter.
     *
     * @param  string  $meterId
     * @return bool
     */
    public function removeUsageThreshold(string $meterId): bool
    {
        return $this->usageThresholds()->where('meter_id', $meterId)->delete() > 0;
    }
}

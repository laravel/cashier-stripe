<?php

namespace Laravel\Cashier\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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
    public function meterEventSummaries(string $meterId, ?int $startTime = null, ?int $endTime = null, array $options = [], array $requestOptions = []): Collection
    {
        $this->assertCustomerExists();

        if (! isset($startTime)) {
            $startTime = 1;
        }

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
     * Set a usage threshold for a meter.
     *
     * @param  string  $meterId
     * @param  int  $threshold
     * @param  string  $period
     * @param  array  $alertOptions
     * @return static
     *
     * @throws \InvalidArgumentException
     */
    public function setUsageThreshold(string $meterId, int $threshold, string $period = 'billing_cycle', array $alertOptions = []): static
    {
        $this->assertCustomerExists();

        if ($threshold <= 0) {
            throw new \InvalidArgumentException('Usage threshold must be a positive integer.');
        }

        $validPeriods = ['billing_cycle', 'monthly', 'daily', 'weekly'];
        if (! in_array($period, $validPeriods)) {
            throw new \InvalidArgumentException('Invalid period. Must be one of: '.implode(', ', $validPeriods));
        }

        $thresholdData = [
            'customer_id' => $this->getKey(),
            'meter_id' => $meterId,
            'threshold' => $threshold,
            'period' => $period,
            'alert_options' => $alertOptions,
            'created_at' => now()->toISOString(),
        ];

        // Cache for 30 days by default
        cache()->put($this->getUsageThresholdCacheKey($meterId), $thresholdData, now()->addDays(30));

        return $this;
    }

    /**
     * Get the usage threshold for a meter.
     *
     * @param  string  $meterId
     * @return array|null
     */
    public function getUsageThreshold(string $meterId): ?array
    {
        return cache()->get($this->getUsageThresholdCacheKey($meterId));
    }

    /**
     * Remove the usage threshold for a meter.
     *
     * @param  string  $meterId
     * @return static
     */
    public function removeUsageThreshold(string $meterId): static
    {
        cache()->forget($this->getUsageThresholdCacheKey($meterId));

        return $this;
    }

    /**
     * Check if usage exceeds the threshold for a meter.
     *
     * @param  string  $meterId
     * @param  int|null  $startTime
     * @param  int|null  $endTime
     * @param  array  $options
     * @param  array  $requestOptions
     * @return array|null
     */
    public function checkUsageThreshold(
        string $meterId,
        ?int $startTime = null,
        ?int $endTime = null,
        array $options = [],
        array $requestOptions = []
    ): ?array {
        $threshold = $this->getUsageThreshold($meterId);

        if (! $threshold || $threshold['threshold'] <= 0) {
            return null;
        }

        $usageSummaries = $this->meterEventSummaries($meterId, $startTime ?? 1, $endTime, $options, $requestOptions);
        $currentUsage = $usageSummaries->sum('aggregated_value');

        if ($currentUsage <= $threshold['threshold']) {
            return null;
        }

        $overage = $currentUsage - $threshold['threshold'];
        $percentage = round(($currentUsage / $threshold['threshold']) * 100);

        return [
            'threshold_config' => $threshold,
            'current_usage' => $currentUsage,
            'overage' => $overage,
            'percentage' => $percentage,
        ];
    }

    /**
     * Get usage as a percentage of the threshold.
     *
     * @param  string  $meterId
     * @param  int|null  $startTime
     * @param  int|null  $endTime
     * @param  array  $options
     * @param  array  $requestOptions
     * @return float|null
     */
    public function getUsagePercentage(
        string $meterId,
        ?int $startTime = null,
        ?int $endTime = null,
        array $options = [],
        array $requestOptions = []
    ): ?float {
        $threshold = $this->getUsageThreshold($meterId);

        if (! $threshold || $threshold['threshold'] <= 0) {
            return null;
        }

        $usageSummaries = $this->meterEventSummaries($meterId, $startTime ?? 1, $endTime, $options, $requestOptions);
        $currentUsage = $usageSummaries->sum('aggregated_value');

        return round(($currentUsage / $threshold['threshold']) * 100, 1);
    }

    /**
     * Get usage analytics for a meter.
     *
     * @param  string  $meterId
     * @param  array  $periods
     * @param  array  $options
     * @param  array  $requestOptions
     * @return array
     */
    public function getUsageAnalytics(
        string $meterId,
        array $periods = ['daily'],
        array $options = [],
        array $requestOptions = []
    ): array {
        $analytics = [];

        foreach ($periods as $period) {
            $usageSummaries = $this->meterEventSummaries($meterId, 1, null, $options, $requestOptions);
            $totalUsage = $usageSummaries->sum('aggregated_value');
            $eventsCount = $usageSummaries->count();

            $analytics[$period] = [
                'total_usage' => $totalUsage,
                'events_count' => $eventsCount,
                'period_start' => now()->subDays(30)->toISOString(),
                'period_end' => now()->toISOString(),
            ];
        }

        return $analytics;
    }

    /**
     * Get the cache key for usage threshold.
     *
     * @param  string  $meterId
     * @return string
     */
    protected function getUsageThresholdCacheKey(string $meterId): string
    {
        return "cashier:usage_threshold:{$this->getKey()}:{$meterId}";
    }
}

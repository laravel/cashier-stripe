<?php

namespace Laravel\Cashier\Tests\Unit;

use Laravel\Cashier\UsageThreshold;
use PHPUnit\Framework\TestCase;

class UsageThresholdTest extends TestCase
{
    public function test_threshold_is_exceeded_when_usage_is_above()
    {
        $threshold = new UsageThreshold([
            'threshold' => 10000,
            'meter_id' => 'meter_api_calls',
        ]);

        $this->assertTrue($threshold->isExceeded(15000));
        $this->assertTrue($threshold->isExceeded(10001));
    }

    public function test_threshold_is_not_exceeded_when_usage_is_at_or_below()
    {
        $threshold = new UsageThreshold([
            'threshold' => 10000,
            'meter_id' => 'meter_api_calls',
        ]);

        $this->assertFalse($threshold->isExceeded(10000));
        $this->assertFalse($threshold->isExceeded(5000));
        $this->assertFalse($threshold->isExceeded(0));
    }

    public function test_usage_percentage_calculation()
    {
        $threshold = new UsageThreshold([
            'threshold' => 10000,
        ]);

        $this->assertSame(50.0, $threshold->usagePercentage(5000));
        $this->assertSame(100.0, $threshold->usagePercentage(10000));
        $this->assertSame(150.0, $threshold->usagePercentage(15000));
        $this->assertSame(0.0, $threshold->usagePercentage(0));
    }

    public function test_usage_percentage_with_zero_threshold()
    {
        $threshold = new UsageThreshold([
            'threshold' => 0,
        ]);

        $this->assertSame(0.0, $threshold->usagePercentage(5000));
    }

    public function test_overage_calculation()
    {
        $threshold = new UsageThreshold([
            'threshold' => 10000,
        ]);

        $this->assertSame(5000, $threshold->overage(15000));
        $this->assertSame(0, $threshold->overage(10000));
        $this->assertSame(0, $threshold->overage(5000));
        $this->assertSame(0, $threshold->overage(0));
    }

    public function test_threshold_casts_to_integer()
    {
        $threshold = new UsageThreshold([
            'threshold' => '10000',
        ]);

        $this->assertIsInt($threshold->threshold);
        $this->assertSame(10000, $threshold->threshold);
    }

    public function test_alert_options_cast_to_array()
    {
        $threshold = new UsageThreshold([
            'alert_options' => ['email' => 'test@example.com'],
        ]);

        $this->assertIsArray($threshold->alert_options);
        $this->assertSame('test@example.com', $threshold->alert_options['email']);
    }

    public function test_threshold_uses_correct_table_name()
    {
        $threshold = new UsageThreshold;

        $this->assertSame('cashier_usage_thresholds', $threshold->getTable());
    }
}

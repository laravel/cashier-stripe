<?php

namespace Laravel\Cashier\Tests\Unit;

use Laravel\Cashier\Tests\TestCase;

class UsageThresholdsTest extends TestCase
{
    public function test_set_usage_threshold()
    {
        $billable = $this->getMockBillableWithUsageBilling();

        $result = $billable->setUsageThreshold('meter_123', 1000, 'monthly', ['email' => true]);

        $this->assertSame($billable, $result);

        // Check that the threshold was stored in cache
        $threshold = cache()->get('cashier:usage_threshold:user_123:meter_123');

        $this->assertNotNull($threshold);
        $this->assertEquals('user_123', $threshold['customer_id']);
        $this->assertEquals('meter_123', $threshold['meter_id']);
        $this->assertEquals(1000, $threshold['threshold']);
        $this->assertEquals('monthly', $threshold['period']);
        $this->assertEquals(['email' => true], $threshold['alert_options']);
    }

    public function test_get_usage_threshold()
    {
        $billable = $this->getMockBillableWithUsageBilling();

        // Set a threshold first
        $expectedThreshold = [
            'customer_id' => 'user_123',
            'meter_id' => 'meter_123',
            'threshold' => 500,
            'period' => 'billing_cycle',
            'alert_options' => [],
        ];

        cache()->put('cashier:usage_threshold:user_123:meter_123', $expectedThreshold);

        $threshold = $billable->getUsageThreshold('meter_123');

        $this->assertEquals($expectedThreshold, $threshold);
    }

    public function test_get_usage_threshold_returns_null_when_not_set()
    {
        $billable = $this->getMockBillableWithUsageBilling();

        $threshold = $billable->getUsageThreshold('nonexistent_meter');

        $this->assertNull($threshold);
    }

    public function test_remove_usage_threshold()
    {
        $billable = $this->getMockBillableWithUsageBilling();

        // Set a threshold first
        cache()->put('cashier:usage_threshold:user_123:meter_123', ['test' => 'data']);

        $result = $billable->removeUsageThreshold('meter_123');

        $this->assertSame($billable, $result);
        $this->assertNull(cache()->get('cashier:usage_threshold:user_123:meter_123'));
    }

    public function test_check_usage_threshold_returns_null_when_no_threshold_set()
    {
        $billable = $this->getMockBillableWithUsageBilling();

        $result = $billable->checkUsageThreshold('meter_123');

        $this->assertNull($result);
    }

    public function test_check_usage_threshold_returns_null_when_under_limit()
    {
        $billable = $this->getMockBillableWithUsageBilling();

        // Set a threshold manually
        $billable->setUsageThreshold('meter_123', 1000);

        $mockUsageSummaries = collect([
            (object) ['aggregated_value' => 200],
            (object) ['aggregated_value' => 300],
        ]);

        $billable->setMockUsageSummaries($mockUsageSummaries);

        $result = $billable->checkUsageThreshold('meter_123');

        $this->assertNull($result);
    }

    public function test_check_usage_threshold_returns_data_when_over_limit()
    {
        $billable = $this->getMockBillableWithUsageBilling();

        // Set a threshold manually
        $billable->setUsageThreshold('meter_123', 500, 'monthly');

        $mockUsageSummaries = collect([
            (object) ['aggregated_value' => 400],
            (object) ['aggregated_value' => 300],
        ]);

        $billable->setMockUsageSummaries($mockUsageSummaries);

        $result = $billable->checkUsageThreshold('meter_123');

        $this->assertNotNull($result);
        $this->assertEquals(500, $result['threshold_config']['threshold']);
        $this->assertEquals('monthly', $result['threshold_config']['period']);
        $this->assertEquals(700, $result['current_usage']);
        $this->assertEquals(200, $result['overage']);
        $this->assertEquals(140, $result['percentage']);
    }

    public function test_get_usage_percentage()
    {
        $billable = $this->getMockBillableWithUsageBilling();

        // Set a threshold manually
        $billable->setUsageThreshold('meter_123', 1000);

        $mockUsageSummaries = collect([
            (object) ['aggregated_value' => 250],
        ]);

        $billable->setMockUsageSummaries($mockUsageSummaries);

        $percentage = $billable->getUsagePercentage('meter_123');

        $this->assertEquals(25.0, $percentage);
    }

    public function test_get_usage_percentage_returns_null_when_no_threshold()
    {
        $billable = $this->getMockBillableWithUsageBilling();

        $percentage = $billable->getUsagePercentage('meter_123');

        $this->assertNull($percentage);
    }

    public function test_get_usage_analytics()
    {
        $billable = $this->getMockBillableWithUsageBilling();

        $mockUsageSummaries = collect([
            (object) ['aggregated_value' => 100],
            (object) ['aggregated_value' => 200],
        ]);

        $billable->setMockUsageSummaries($mockUsageSummaries);

        $analytics = $billable->getUsageAnalytics('meter_123', ['daily']);

        $this->assertArrayHasKey('daily', $analytics);
        $this->assertEquals(300, $analytics['daily']['total_usage']);
        $this->assertEquals(2, $analytics['daily']['events_count']);
        $this->assertArrayHasKey('period_start', $analytics['daily']);
        $this->assertArrayHasKey('period_end', $analytics['daily']);
    }

    protected function getMockBillableWithUsageBilling()
    {
        return new MockBillableForUsageBilling();
    }
}

class MockBillableForUsageBilling
{
    use \Laravel\Cashier\Concerns\ManagesUsageBilling;

    protected $key = 'user_123';
    public $mockUsageSummaries = null;

    public function getKey()
    {
        return $this->key;
    }

    public function assertCustomerExists()
    {
        // Mock implementation
    }

    public function stripeId()
    {
        return 'cus_test123';
    }

    public function stripe()
    {
        return new \stdClass();
    }

    public function meterEventSummaries(string $meterId, ?int $startTime = null, ?int $endTime = null, array $options = [], array $requestOptions = []): \Illuminate\Support\Collection
    {
        return $this->mockUsageSummaries ?: collect([]);
    }

    public function setMockUsageSummaries($summaries)
    {
        $this->mockUsageSummaries = $summaries;
    }
}

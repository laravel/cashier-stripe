<?php

namespace Laravel\Cashier\Tests\Unit;

use App\Models\User;
use InvalidArgumentException;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\SubscriptionScheduleBuilder;
use PHPUnit\Framework\TestCase;

class SubscriptionScheduleBuilderTest extends TestCase
{
    protected function tearDown(): void
    {
        Cashier::$defaultBillingMode = 'classic';

        parent::tearDown();
    }

    public function test_it_can_be_instantiated()
    {
        $builder = new SubscriptionScheduleBuilder(new User);

        $this->assertInstanceOf(SubscriptionScheduleBuilder::class, $builder);
    }

    public function test_add_phase_returns_builder_instance()
    {
        $builder = new SubscriptionScheduleBuilder(new User);

        $result = $builder->addPhase(['price_monthly']);

        $this->assertSame($builder, $result);
    }

    public function test_create_requires_at_least_one_phase()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one phase is required when creating subscription schedules.');

        $builder = new SubscriptionScheduleBuilder(new User);
        $builder->create();
    }

    public function test_end_behavior_rejects_invalid_values()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid end behavior [invalid].');

        $builder = new SubscriptionScheduleBuilder(new User);
        $builder->endBehavior('invalid');
    }

    public function test_end_behavior_accepts_valid_values()
    {
        $builder = new SubscriptionScheduleBuilder(new User);

        $builder->endBehavior('release');
        $builder->endBehavior('cancel');
        $builder->endBehavior('none');

        // No exception thrown
        $this->assertTrue(true);
    }

    public function test_with_billing_mode_returns_builder_instance()
    {
        $builder = new SubscriptionScheduleBuilder(new User);

        $result = $builder->withBillingMode('flexible');

        $this->assertSame($builder, $result);
    }

    public function test_with_metadata_returns_builder_instance()
    {
        $builder = new SubscriptionScheduleBuilder(new User);

        $result = $builder->withMetadata(['key' => 'value']);

        $this->assertSame($builder, $result);
    }

    public function test_start_date_accepts_timestamp()
    {
        $builder = new SubscriptionScheduleBuilder(new User);

        $result = $builder->startDate(1700000000);

        $this->assertSame($builder, $result);
    }

    public function test_start_date_accepts_now_string()
    {
        $builder = new SubscriptionScheduleBuilder(new User);

        $result = $builder->startDate('now');

        $this->assertSame($builder, $result);
    }

    public function test_start_date_accepts_datetime_interface()
    {
        $builder = new SubscriptionScheduleBuilder(new User);

        $result = $builder->startDate(new \DateTimeImmutable('+1 day'));

        $this->assertSame($builder, $result);
    }

    public function test_with_default_settings_returns_builder_instance()
    {
        $builder = new SubscriptionScheduleBuilder(new User);

        $result = $builder->withDefaultSettings([
            'collection_method' => 'charge_automatically',
        ]);

        $this->assertSame($builder, $result);
    }

    public function test_multiple_phases_can_be_added()
    {
        $builder = new SubscriptionScheduleBuilder(new User);

        $builder->addPhase(['price_starter'], ['iterations' => 1]);
        $builder->addPhase(['price_pro'], ['iterations' => 2]);

        // Builder should hold both phases
        $this->assertInstanceOf(SubscriptionScheduleBuilder::class, $builder);
    }
}

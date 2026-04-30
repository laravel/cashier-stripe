<?php

namespace Laravel\Cashier\Tests\Unit;

use App\Models\User;
use Laravel\Cashier\SubscriptionScheduleBuilder;
use PHPUnit\Framework\TestCase;

class SubscriptionScheduleBuilderTest extends TestCase
{
    public function test_subscription_schedule_builder_with_billing_mode()
    {
        $builder = new SubscriptionScheduleBuilder(new User);
        $result = $builder->withBillingMode('flexible');

        // Should return builder for method chaining
        $this->assertSame($builder, $result);

        // Use reflection to access protected property
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('billingMode');

        $this->assertEquals(['type' => 'flexible'], $property->getValue($builder));
    }

    public function test_subscription_schedule_builder_billing_mode_payload_omits_classic()
    {
        $builder = new SubscriptionScheduleBuilder(new User);
        $builder->withBillingMode('classic');

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('getBillingModeForPayload');

        $this->assertNull($method->invoke($builder));
    }

    public function test_subscription_schedule_builder_effective_billing_mode()
    {
        $builder = new SubscriptionScheduleBuilder(new User);
        $builder->withBillingMode('flexible');

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('getEffectiveBillingMode');

        $this->assertEquals('flexible', $method->invoke($builder));
    }

    public function test_subscription_schedule_builder_default_billing_mode_parameter()
    {
        $builder = new SubscriptionScheduleBuilder(new User);
        $result = $builder->withBillingMode(); // No parameter should default to 'flexible'

        // Should return builder for method chaining
        $this->assertSame($builder, $result);

        // Use reflection to access protected property
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('billingMode');

        $this->assertEquals(['type' => 'flexible'], $property->getValue($builder));
    }

    public function test_subscription_schedule_builder_phases()
    {
        $phases = [
            [
                'items' => [['price' => 'price_test', 'quantity' => 1]],
                'iterations' => 2,
            ],
        ];

        $builder = new SubscriptionScheduleBuilder(new User);
        $result = $builder->phases($phases);

        // Should return builder for method chaining
        $this->assertSame($builder, $result);

        // Use reflection to access protected property
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('phases');

        $this->assertEquals($phases, $property->getValue($builder));
    }

    public function test_subscription_schedule_builder_add_phase()
    {
        $phase = [
            'items' => [['price' => 'price_test', 'quantity' => 1]],
            'iterations' => 1,
        ];

        $builder = new SubscriptionScheduleBuilder(new User);
        $result = $builder->addPhase($phase);

        // Should return builder for method chaining
        $this->assertSame($builder, $result);

        // Use reflection to access protected property
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('phases');

        $this->assertEquals([$phase], $property->getValue($builder));
    }

    public function test_subscription_schedule_builder_from_subscription()
    {
        $builder = new SubscriptionScheduleBuilder(new User);
        $result = $builder->fromSubscription('sub_test');

        // Should return builder for method chaining
        $this->assertSame($builder, $result);

        // Use reflection to access protected property
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('fromSubscription');

        $this->assertEquals('sub_test', $property->getValue($builder));
    }

    public function test_subscription_schedule_builder_end_behavior()
    {
        $builder = new SubscriptionScheduleBuilder(new User);
        $result = $builder->endBehavior('cancel');

        // Should return builder for method chaining
        $this->assertSame($builder, $result);

        // Use reflection to access protected property
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('endBehavior');

        $this->assertEquals('cancel', $property->getValue($builder));
    }

    public function test_subscription_schedule_builder_with_metadata()
    {
        $metadata = ['test' => 'value'];

        $builder = new SubscriptionScheduleBuilder(new User);
        $result = $builder->withMetadata($metadata);

        // Should return builder for method chaining
        $this->assertSame($builder, $result);

        // Use reflection to access protected property
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('metadata');

        $this->assertEquals($metadata, $property->getValue($builder));
    }

    public function test_subscription_schedule_builder_phase_limit_validation()
    {
        $builder = new SubscriptionScheduleBuilder(new User);

        // Add 10 phases (maximum allowed)
        for ($i = 0; $i < 10; $i++) {
            $builder->addPhase([
                'items' => [['price' => 'price_test', 'quantity' => 1]],
                'iterations' => 1,
            ]);
        }

        // 11th phase should throw exception
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Subscription schedules can have a maximum of 10 phases.');

        $builder->addPhase([
            'items' => [['price' => 'price_test', 'quantity' => 1]],
            'iterations' => 1,
        ]);
    }

    public function test_subscription_schedule_builder_phases_method_validation()
    {
        $builder = new SubscriptionScheduleBuilder(new User);

        // Create 11 phases to test validation
        $phases = [];
        for ($i = 0; $i < 11; $i++) {
            $phases[] = [
                'items' => [['price' => 'price_test', 'quantity' => 1]],
                'iterations' => 1,
            ];
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Subscription schedules can have a maximum of 10 phases.');

        $builder->phases($phases);
    }

    public function test_subscription_schedule_builder_add_phase_with_proration()
    {
        $items = [['price' => 'price_test', 'quantity' => 1]];

        $builder = new SubscriptionScheduleBuilder(new User);
        $result = $builder->addPhaseWithProration($items, 'create_prorations');

        // Should return builder for method chaining
        $this->assertSame($builder, $result);

        // Use reflection to access protected property
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('phases');

        $phases = $property->getValue($builder);
        $this->assertCount(1, $phases);
        $this->assertEquals('create_prorations', $phases[0]['proration_behavior']);
        $this->assertEquals($items, $phases[0]['items']);
    }

    public function test_subscription_schedule_builder_invalid_proration_behavior()
    {
        $builder = new SubscriptionScheduleBuilder(new User);
        $items = [['price' => 'price_test', 'quantity' => 1]];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid proration behavior. Must be one of: create_prorations, none, always_invoice');

        $builder->addPhaseWithProration($items, 'invalid_behavior');
    }

    public function test_subscription_schedule_builder_add_phase_with_metadata()
    {
        $items = [['price' => 'price_test', 'quantity' => 1]];
        $metadata = ['phase' => 'trial', 'duration' => '30_days'];

        $builder = new SubscriptionScheduleBuilder(new User);
        $result = $builder->addPhaseWithMetadata($items, $metadata);

        // Should return builder for method chaining
        $this->assertSame($builder, $result);

        // Use reflection to access protected property
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('phases');

        $phases = $property->getValue($builder);
        $this->assertCount(1, $phases);
        $this->assertEquals($metadata, $phases[0]['metadata']);
        $this->assertEquals($items, $phases[0]['items']);
    }

    public function test_subscription_schedule_builder_add_trial_phase()
    {
        $items = [['price' => 'price_test', 'quantity' => 1]];
        $trialEnd = new \DateTime('2025-01-31');

        $builder = new SubscriptionScheduleBuilder(new User);
        $result = $builder->addTrialPhase($items, $trialEnd);

        // Should return builder for method chaining
        $this->assertSame($builder, $result);

        // Use reflection to access protected property
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('phases');

        $phases = $property->getValue($builder);
        $this->assertCount(1, $phases);
        $this->assertEquals($trialEnd->getTimestamp(), $phases[0]['trial_end']);
        $this->assertEquals($items, $phases[0]['items']);
    }

    public function test_subscription_schedule_builder_add_trial_phase_with_timestamp()
    {
        $items = [['price' => 'price_test', 'quantity' => 1]];
        $trialEndTimestamp = 1738281600; // 2025-01-31

        $builder = new SubscriptionScheduleBuilder(new User);
        $result = $builder->addTrialPhase($items, $trialEndTimestamp);

        // Should return builder for method chaining
        $this->assertSame($builder, $result);

        // Use reflection to access protected property
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('phases');

        $phases = $property->getValue($builder);
        $this->assertCount(1, $phases);
        $this->assertEquals($trialEndTimestamp, $phases[0]['trial_end']);
        $this->assertEquals($items, $phases[0]['items']);
    }

    public function test_subscription_schedule_builder_add_phase_with_options()
    {
        $items = [['price' => 'price_test', 'quantity' => 1]];
        $options = [
            'proration_behavior' => 'none',
            'metadata' => ['type' => 'discount_phase'],
            'automatic_tax' => ['enabled' => true],
        ];

        $builder = new SubscriptionScheduleBuilder(new User);
        $result = $builder->addPhaseWithOptions($items, $options);

        // Should return builder for method chaining
        $this->assertSame($builder, $result);

        // Use reflection to access protected property
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('phases');

        $phases = $property->getValue($builder);
        $this->assertCount(1, $phases);
        $this->assertEquals($items, $phases[0]['items']);
        $this->assertEquals('none', $phases[0]['proration_behavior']);
        $this->assertEquals(['type' => 'discount_phase'], $phases[0]['metadata']);
        $this->assertEquals(['enabled' => true], $phases[0]['automatic_tax']);
    }
}

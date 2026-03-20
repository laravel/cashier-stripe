<?php

namespace Laravel\Cashier\Tests\Unit;

use Laravel\Cashier\Cashier;
use Laravel\Cashier\CheckoutBuilder;
use Laravel\Cashier\Concerns\ManagesBillingMode;
use PHPUnit\Framework\TestCase;

class CheckoutBuilderTest extends TestCase
{
    protected function tearDown(): void
    {
        Cashier::$defaultBillingMode = 'classic';

        parent::tearDown();
    }

    public function test_with_billing_mode_returns_builder_instance()
    {
        $builder = CheckoutBuilder::make();

        $result = $builder->withBillingMode('flexible');

        $this->assertSame($builder, $result);
    }

    public function test_billing_mode_defaults_to_null_payload_for_classic()
    {
        $builder = new TestableCheckoutBuilder;

        $this->assertNull($builder->testGetBillingModeForPayload());
    }

    public function test_billing_mode_payload_returns_array_for_flexible()
    {
        $builder = new TestableCheckoutBuilder;
        $builder->withBillingMode('flexible');

        $this->assertSame(['type' => 'flexible'], $builder->testGetBillingModeForPayload());
    }

    public function test_billing_mode_is_inherited_from_parent_instance()
    {
        $parent = new BillingModeParentStub;
        $parent->withBillingMode('flexible');

        $builder = new TestableCheckoutBuilder(null, $parent);

        $this->assertSame(['type' => 'flexible'], $builder->testGetBillingModeForPayload());
    }

    public function test_billing_mode_is_not_inherited_from_parent_without_trait()
    {
        $parent = new \stdClass;

        $builder = new TestableCheckoutBuilder(null, $parent);

        $this->assertNull($builder->testGetBillingModeForPayload());
    }

    public function test_billing_mode_inherits_global_default()
    {
        Cashier::defaultBillingMode('flexible');

        $builder = new TestableCheckoutBuilder;

        $this->assertSame(['type' => 'flexible'], $builder->testGetBillingModeForPayload());
    }

    public function test_with_billing_mode_rejects_invalid_type()
    {
        $this->expectException(\InvalidArgumentException::class);

        $builder = CheckoutBuilder::make();
        $builder->withBillingMode('invalid');
    }
}

class TestableCheckoutBuilder extends CheckoutBuilder
{
    public function testGetBillingModeForPayload(): ?array
    {
        return $this->getBillingModeForPayload();
    }
}

class BillingModeParentStub
{
    use ManagesBillingMode;
}

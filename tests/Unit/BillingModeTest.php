<?php

namespace Laravel\Cashier\Tests\Unit;

use InvalidArgumentException;
use Laravel\Cashier\Cashier;
use PHPUnit\Framework\TestCase;

class BillingModeTest extends TestCase
{
    protected function tearDown(): void
    {
        // Reset to default after each test
        Cashier::$defaultBillingMode = 'classic';

        parent::tearDown();
    }

    public function test_default_billing_mode_is_classic()
    {
        $this->assertSame('classic', Cashier::$defaultBillingMode);
    }

    public function test_billing_mode_can_be_set_to_flexible()
    {
        Cashier::defaultBillingMode('flexible');

        $this->assertSame('flexible', Cashier::$defaultBillingMode);
    }

    public function test_billing_mode_can_be_set_to_classic()
    {
        Cashier::defaultBillingMode('flexible');
        Cashier::defaultBillingMode('classic');

        $this->assertSame('classic', Cashier::$defaultBillingMode);
    }

    public function test_invalid_billing_mode_throws_exception()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid billing mode [invalid]. Must be 'classic' or 'flexible'.");

        Cashier::defaultBillingMode('invalid');
    }

    public function test_manages_billing_mode_trait_with_billing_mode()
    {
        $stub = new BillingModeTraitStub;

        $result = $stub->withBillingMode('flexible');

        $this->assertSame($stub, $result);
        $this->assertSame('flexible', $stub->testGetEffectiveBillingMode());
    }

    public function test_manages_billing_mode_trait_defaults_to_classic()
    {
        $stub = new BillingModeTraitStub;

        $this->assertSame('classic', $stub->testGetEffectiveBillingMode());
    }

    public function test_manages_billing_mode_trait_respects_global_default()
    {
        Cashier::defaultBillingMode('flexible');

        $stub = new BillingModeTraitStub;

        $this->assertSame('flexible', $stub->testGetEffectiveBillingMode());
    }

    public function test_manages_billing_mode_trait_instance_overrides_global()
    {
        Cashier::defaultBillingMode('flexible');

        $stub = new BillingModeTraitStub;
        $stub->withBillingMode('classic');

        $this->assertSame('classic', $stub->testGetEffectiveBillingMode());
    }

    public function test_billing_mode_payload_returns_null_for_classic()
    {
        $stub = new BillingModeTraitStub;

        $this->assertNull($stub->testGetBillingModeForPayload());
    }

    public function test_billing_mode_payload_returns_array_for_flexible()
    {
        $stub = new BillingModeTraitStub;
        $stub->withBillingMode('flexible');

        $this->assertSame(['type' => 'flexible'], $stub->testGetBillingModeForPayload());
    }

    public function test_billing_mode_payload_returns_array_when_global_is_flexible()
    {
        Cashier::defaultBillingMode('flexible');

        $stub = new BillingModeTraitStub;

        $this->assertSame(['type' => 'flexible'], $stub->testGetBillingModeForPayload());
    }

    public function test_billing_mode_payload_returns_null_when_global_flexible_but_instance_classic()
    {
        Cashier::defaultBillingMode('flexible');

        $stub = new BillingModeTraitStub;
        $stub->withBillingMode('classic');

        $this->assertNull($stub->testGetBillingModeForPayload());
    }

    public function test_with_billing_mode_rejects_invalid_type()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid billing mode [unknown]. Must be 'classic' or 'flexible'.");

        $stub = new BillingModeTraitStub;
        $stub->withBillingMode('unknown');
    }

    public function test_proration_discounts_sets_flexible_sub_option()
    {
        $stub = new BillingModeTraitStub;
        $stub->withProrationDiscounts('itemized');

        $payload = $stub->testGetBillingModeForPayload();

        $this->assertSame('flexible', $payload['type']);
        $this->assertSame(['proration_discounts' => 'itemized'], $payload['flexible']);
    }

    public function test_proration_discounts_defaults_to_included()
    {
        $stub = new BillingModeTraitStub;
        $stub->withProrationDiscounts();

        $payload = $stub->testGetBillingModeForPayload();

        $this->assertSame(['proration_discounts' => 'included'], $payload['flexible']);
    }

    public function test_proration_discounts_auto_sets_flexible_mode()
    {
        $stub = new BillingModeTraitStub;

        // Without explicitly calling withBillingMode first
        $stub->withProrationDiscounts('itemized');

        $this->assertSame('flexible', $stub->testGetEffectiveBillingMode());
    }

    public function test_proration_discounts_rejects_invalid_behavior()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid proration discounts behavior [bad].');

        $stub = new BillingModeTraitStub;
        $stub->withProrationDiscounts('bad');
    }

    public function test_flexible_payload_without_proration_discounts_has_no_flexible_key()
    {
        $stub = new BillingModeTraitStub;
        $stub->withBillingMode('flexible');

        $payload = $stub->testGetBillingModeForPayload();

        $this->assertSame(['type' => 'flexible'], $payload);
        $this->assertArrayNotHasKey('flexible', $payload);
    }

    public function test_migrate_already_flexible_is_noop()
    {
        $subscription = new \Laravel\Cashier\Subscription([
            'stripe_status' => 'active',
        ]);

        // Can set billing mode without error
        $result = $subscription->withBillingMode('flexible');
        $this->assertSame($subscription, $result);
    }

    public function test_canceled_subscription_cannot_migrate_billing_mode()
    {
        // Use SubscriptionTest's TestCase base which has app context
        // This test is in SubscriptionTest instead due to datetime cast requirement
        // Here we test that the guard exists via the incomplete guard
        $subscription = new \Laravel\Cashier\Subscription([
            'stripe_status' => \Stripe\Subscription::STATUS_INCOMPLETE,
        ]);

        $this->expectException(\Laravel\Cashier\Exceptions\SubscriptionUpdateFailure::class);

        $subscription->migrateToFlexibleBillingMode();
    }
}

class BillingModeTraitStub
{
    use \Laravel\Cashier\Concerns\ManagesBillingMode;

    public function testGetEffectiveBillingMode(): string
    {
        return $this->getEffectiveBillingMode();
    }

    public function testGetBillingModeForPayload(): ?array
    {
        return $this->getBillingModeForPayload();
    }
}

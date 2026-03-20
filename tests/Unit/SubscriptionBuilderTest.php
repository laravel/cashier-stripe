<?php

namespace Laravel\Cashier\Tests\Unit;

use App\Models\User;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\SubscriptionBuilder;
use PHPUnit\Framework\TestCase;

class SubscriptionBuilderTest extends TestCase
{
    protected function tearDown(): void
    {
        Cashier::$defaultBillingMode = 'classic';

        parent::tearDown();
    }

    public function test_it_can_be_instantiated()
    {
        $builder = new SubscriptionBuilder(new User, 'default', [
            'price_foo',
            ['price' => 'price_bux'],
            ['price' => 'price_bar', 'quantity' => 1],
            ['price' => 'price_baz', 'quantity' => 0],
        ]);

        $this->assertSame([
            'price_foo' => ['price' => 'price_foo', 'quantity' => 1],
            'price_bux' => ['price' => 'price_bux', 'quantity' => 1],
            'price_bar' => ['price' => 'price_bar', 'quantity' => 1],
            'price_baz' => ['price' => 'price_baz', 'quantity' => 0],
        ], $builder->getItems());
    }

    public function test_quantity_without_price_and_no_items_throws_exception()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No price specified for quantity update.');

        $builder = new SubscriptionBuilder(new User, 'default');

        $builder->quantity(1);
    }

    public function test_with_billing_mode_returns_builder_instance()
    {
        $builder = new SubscriptionBuilder(new User, 'default', 'price_foo');

        $result = $builder->withBillingMode('flexible');

        $this->assertSame($builder, $result);
    }

    public function test_with_billing_mode_rejects_invalid_type()
    {
        $this->expectException(\InvalidArgumentException::class);

        $builder = new SubscriptionBuilder(new User, 'default', 'price_foo');
        $builder->withBillingMode('invalid');
    }

    public function test_billing_mode_defaults_to_classic_with_no_payload()
    {
        $builder = new TestableSubscriptionBuilder(new User, 'default', 'price_foo');

        $payload = $builder->exposeBuildPayload();

        $this->assertArrayNotHasKey('billing_mode', $payload);
    }

    public function test_billing_mode_flexible_is_included_in_payload()
    {
        $builder = new TestableSubscriptionBuilder(new User, 'default', 'price_foo');
        $builder->withBillingMode('flexible');

        $payload = $builder->exposeBuildPayload();

        $this->assertArrayHasKey('billing_mode', $payload);
        $this->assertSame(['type' => 'flexible'], $payload['billing_mode']);
    }

    public function test_billing_mode_uses_global_default_when_set_to_flexible()
    {
        Cashier::defaultBillingMode('flexible');

        $builder = new TestableSubscriptionBuilder(new User, 'default', 'price_foo');

        $payload = $builder->exposeBuildPayload();

        $this->assertArrayHasKey('billing_mode', $payload);
        $this->assertSame(['type' => 'flexible'], $payload['billing_mode']);
    }

    public function test_billing_mode_instance_overrides_global_default()
    {
        Cashier::defaultBillingMode('flexible');

        $builder = new TestableSubscriptionBuilder(new User, 'default', 'price_foo');
        $builder->withBillingMode('classic');

        $payload = $builder->exposeBuildPayload();

        $this->assertArrayNotHasKey('billing_mode', $payload);
    }

    public function test_flexible_billing_with_billing_thresholds_throws_exception()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Flexible billing mode is not compatible with billing thresholds.');

        $builder = new TestableSubscriptionBuilder(new User, 'default', 'price_foo');
        $builder->withBillingMode('flexible');
        $builder->withBillingThresholds(['amount_gte' => 1000]);

        // Validation happens on create(), simulate via exposed method
        $builder->exposeValidateFlexibleBillingCompatibility();
    }

    public function test_classic_billing_with_billing_thresholds_is_allowed()
    {
        $builder = new TestableSubscriptionBuilder(new User, 'default', 'price_foo');
        $builder->withBillingThresholds(['amount_gte' => 1000]);

        // Should not throw
        $builder->exposeValidateFlexibleBillingCompatibility();
        $this->assertTrue(true);
    }

    public function test_flexible_payload_includes_proration_discounts()
    {
        $builder = new TestableSubscriptionBuilder(new User, 'default', 'price_foo');
        $builder->withBillingMode('flexible');
        $builder->withProrationDiscounts('itemized');

        $payload = $builder->exposeBuildPayload();

        $this->assertSame('flexible', $payload['billing_mode']['type']);
        $this->assertSame(['proration_discounts' => 'itemized'], $payload['billing_mode']['flexible']);
    }
}

class TestableSubscriptionBuilder extends SubscriptionBuilder
{
    public function exposeBuildPayload(): array
    {
        return $this->buildPayload();
    }

    public function exposeValidateFlexibleBillingCompatibility(): void
    {
        $this->validateFlexibleBillingCompatibility();
    }
}

<?php

namespace Laravel\Cashier\Tests\Unit;

use App\Models\User;
use Laravel\Cashier\CheckoutBuilder;
use Laravel\Cashier\SubscriptionBuilder;
use PHPUnit\Framework\TestCase;

class BillingModeTest extends TestCase
{
    public function test_subscription_builder_with_billing_mode()
    {
        $builder = new SubscriptionBuilder(new User, 'main', []);
        $result = $builder->withBillingMode('flexible');

        // Should return builder for method chaining
        $this->assertSame($builder, $result);

        // Use reflection to access protected property
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('billingMode');

        $this->assertEquals(['type' => 'flexible'], $property->getValue($builder));
    }

    public function test_subscription_builder_billing_mode_payload_omits_classic()
    {
        $builder = new SubscriptionBuilder(new User, 'main', []);
        $builder->withBillingMode('classic');

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('getBillingModeForPayload');

        $this->assertNull($method->invoke($builder));
    }

    public function test_subscription_builder_billing_mode_payload_includes_flexible()
    {
        $builder = new SubscriptionBuilder(new User, 'main', []);
        $builder->withBillingMode('flexible');

        // Use reflection to access protected method and property
        $reflection = new \ReflectionClass($builder);

        // Test the effective billing mode without validation
        $method = $reflection->getMethod('getEffectiveBillingMode');

        $this->assertEquals('flexible', $method->invoke($builder));

        // Check that billing mode property is set correctly
        $property = $reflection->getProperty('billingMode');

        $this->assertEquals(['type' => 'flexible'], $property->getValue($builder));
    }

    public function test_checkout_builder_with_billing_mode()
    {
        $builder = new CheckoutBuilder();
        $result = $builder->withBillingMode('flexible');

        // Should return builder for method chaining
        $this->assertSame($builder, $result);

        // Use reflection to access protected property
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('billingMode');

        $this->assertEquals(['type' => 'flexible'], $property->getValue($builder));
    }

    public function test_checkout_builder_billing_mode_payload_omits_classic()
    {
        $builder = new CheckoutBuilder();
        $builder->withBillingMode('classic');

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('getBillingModeForPayload');

        $this->assertNull($method->invoke($builder));
    }

    public function test_checkout_builder_billing_mode_payload_includes_flexible()
    {
        $builder = new CheckoutBuilder();
        $builder->withBillingMode('flexible');

        // Use reflection to access protected method and property
        $reflection = new \ReflectionClass($builder);

        // Test the effective billing mode without validation
        $method = $reflection->getMethod('getEffectiveBillingMode');

        $this->assertEquals('flexible', $method->invoke($builder));

        // Check that billing mode property is set correctly
        $property = $reflection->getProperty('billingMode');

        $this->assertEquals(['type' => 'flexible'], $property->getValue($builder));
    }

    public function test_subscription_builder_default_billing_mode_parameter()
    {
        $builder = new SubscriptionBuilder(new User, 'main', []);
        $result = $builder->withBillingMode(); // No parameter should default to 'flexible'

        // Should return builder for method chaining
        $this->assertSame($builder, $result);

        // Use reflection to access protected property
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('billingMode');

        $this->assertEquals(['type' => 'flexible'], $property->getValue($builder));
    }

    public function test_checkout_builder_default_billing_mode_parameter()
    {
        $builder = new CheckoutBuilder();
        $result = $builder->withBillingMode(); // No parameter should default to 'flexible'

        // Should return builder for method chaining
        $this->assertSame($builder, $result);

        // Use reflection to access protected property
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('billingMode');

        $this->assertEquals(['type' => 'flexible'], $property->getValue($builder));
    }

    public function test_subscription_with_billing_mode()
    {
        $subscription = new \Laravel\Cashier\Subscription([
            'user_id' => 1,
            'type' => 'main',
            'stripe_id' => 'sub_test',
            'stripe_status' => 'active',
        ]);

        $result = $subscription->withBillingMode('flexible');

        // Should return subscription for method chaining
        $this->assertSame($subscription, $result);

        // Use reflection to access protected property
        $reflection = new \ReflectionClass($subscription);
        $property = $reflection->getProperty('billingMode');

        $this->assertEquals(['type' => 'flexible'], $property->getValue($subscription));
    }

    public function test_subscription_billing_mode_payload_in_swap_options()
    {
        $subscription = new \Laravel\Cashier\Subscription([
            'user_id' => 1,
            'type' => 'main',
            'stripe_id' => 'sub_test',
            'stripe_status' => 'active',
        ]);

        $subscription->withBillingMode('flexible');

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($subscription);
        $method = $reflection->getMethod('getSwapOptions');

        $options = $method->invoke($subscription, collect([]));

        // billing_mode should be included in swap options
        $this->assertArrayHasKey('billing_mode', $options);
        $this->assertEquals(['type' => 'flexible'], $options['billing_mode']);
    }
}

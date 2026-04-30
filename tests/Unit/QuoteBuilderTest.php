<?php

namespace Laravel\Cashier\Tests\Unit;

use App\Models\User;
use Laravel\Cashier\QuoteBuilder;
use PHPUnit\Framework\TestCase;

class QuoteBuilderTest extends TestCase
{
    public function test_quote_builder_with_billing_mode()
    {
        $builder = new QuoteBuilder(new User);
        $result = $builder->withBillingMode('flexible');

        // Should return builder for method chaining
        $this->assertSame($builder, $result);

        // Use reflection to access protected property
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('billingMode');

        $this->assertEquals(['type' => 'flexible'], $property->getValue($builder));
    }

    public function test_quote_builder_billing_mode_payload_omits_classic()
    {
        $builder = new QuoteBuilder(new User);
        $builder->withBillingMode('classic');

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('getBillingModeForPayload');

        $this->assertNull($method->invoke($builder));
    }

    public function test_quote_builder_effective_billing_mode()
    {
        $builder = new QuoteBuilder(new User);
        $builder->withBillingMode('flexible');

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('getEffectiveBillingMode');

        $this->assertEquals('flexible', $method->invoke($builder));
    }

    public function test_quote_builder_default_billing_mode_parameter()
    {
        $builder = new QuoteBuilder(new User);
        $result = $builder->withBillingMode(); // No parameter should default to 'flexible'

        // Should return builder for method chaining
        $this->assertSame($builder, $result);

        // Use reflection to access protected property
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('billingMode');

        $this->assertEquals(['type' => 'flexible'], $property->getValue($builder));
    }

    public function test_quote_builder_add_line_item()
    {
        $builder = new QuoteBuilder(new User);
        $result = $builder->addLineItem('price_test', 2);

        // Should return builder for method chaining
        $this->assertSame($builder, $result);

        // Use reflection to access protected property
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('lineItems');

        $expected = [
            ['price' => 'price_test', 'quantity' => 2],
        ];
        $this->assertEquals($expected, $property->getValue($builder));
    }

    public function test_quote_builder_line_items()
    {
        $lineItems = [
            ['price' => 'price_1', 'quantity' => 1],
            ['price' => 'price_2', 'quantity' => 3],
        ];

        $builder = new QuoteBuilder(new User);
        $result = $builder->lineItems($lineItems);

        // Should return builder for method chaining
        $this->assertSame($builder, $result);

        // Use reflection to access protected property
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('lineItems');

        $this->assertEquals($lineItems, $property->getValue($builder));
    }

    public function test_quote_builder_with_metadata()
    {
        $metadata = ['test' => 'value'];

        $builder = new QuoteBuilder(new User);
        $result = $builder->withMetadata($metadata);

        // Should return builder for method chaining
        $this->assertSame($builder, $result);

        // Use reflection to access protected property
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('metadata');

        $this->assertEquals($metadata, $property->getValue($builder));
    }

    public function test_quote_builder_description()
    {
        $builder = new QuoteBuilder(new User);
        $result = $builder->description('Test quote description');

        // Should return builder for method chaining
        $this->assertSame($builder, $result);

        // Use reflection to access protected property
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('description');

        $this->assertEquals('Test quote description', $property->getValue($builder));
    }

    public function test_quote_builder_expires_at_datetime()
    {
        $expiresAt = new \DateTime('2025-12-31 23:59:59');

        $builder = new QuoteBuilder(new User);
        $result = $builder->expiresAt($expiresAt);

        // Should return builder for method chaining
        $this->assertSame($builder, $result);

        // Use reflection to access protected property
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('expiresAt');

        $value = $property->getValue($builder);
        $this->assertInstanceOf(\Carbon\Carbon::class, $value);
        $this->assertEquals('2025-12-31 23:59:59', $value->format('Y-m-d H:i:s'));
    }

    public function test_quote_builder_expires_at_timestamp()
    {
        $timestamp = 1735689599; // 2025-12-31 23:59:59

        $builder = new QuoteBuilder(new User);
        $result = $builder->expiresAt($timestamp);

        // Should return builder for method chaining
        $this->assertSame($builder, $result);

        // Use reflection to access protected property
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('expiresAt');

        $value = $property->getValue($builder);
        $this->assertInstanceOf(\Carbon\Carbon::class, $value);
        $this->assertEquals($timestamp, $value->getTimestamp());
    }

    public function test_quote_builder_get_line_items_with_strings()
    {
        $builder = new QuoteBuilder(new User);
        $builder->lineItems(['price_1', 'price_2']);

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('getLineItems');

        $result = $method->invoke($builder);
        $expected = [
            ['price' => 'price_1', 'quantity' => 1],
            ['price' => 'price_2', 'quantity' => 1],
        ];

        $this->assertEquals($expected, $result);
    }

    public function test_quote_builder_get_line_items_with_arrays()
    {
        $lineItems = [
            ['price' => 'price_1', 'quantity' => 2],
            ['price' => 'price_2'], // Should default quantity to 1
        ];

        $builder = new QuoteBuilder(new User);
        $builder->lineItems($lineItems);

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('getLineItems');

        $result = $method->invoke($builder);
        $expected = [
            ['price' => 'price_1', 'quantity' => 2],
            ['price' => 'price_2', 'quantity' => 1],
        ];

        $this->assertEquals($expected, $result);
    }

    public function test_quote_builder_number()
    {
        $builder = new QuoteBuilder(new User);
        $result = $builder->number('QT-2025-001');

        // Should return builder for method chaining
        $this->assertSame($builder, $result);

        // Use reflection to access protected property
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('quoteNumber');

        $this->assertEquals('QT-2025-001', $property->getValue($builder));
    }

    public function test_quote_builder_header()
    {
        $builder = new QuoteBuilder(new User);
        $result = $builder->header('Custom quote header');

        // Should return builder for method chaining
        $this->assertSame($builder, $result);

        // Use reflection to access protected property
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('header');

        $this->assertEquals('Custom quote header', $property->getValue($builder));
    }

    public function test_quote_builder_footer()
    {
        $builder = new QuoteBuilder(new User);
        $result = $builder->footer('Custom quote footer');

        // Should return builder for method chaining
        $this->assertSame($builder, $result);

        // Use reflection to access protected property
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('footer');

        $this->assertEquals('Custom quote footer', $property->getValue($builder));
    }
}

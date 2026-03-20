<?php

namespace Laravel\Cashier\Tests\Unit;

use App\Models\User;
use InvalidArgumentException;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\QuoteBuilder;
use PHPUnit\Framework\TestCase;

class QuoteBuilderTest extends TestCase
{
    protected function tearDown(): void
    {
        Cashier::$defaultBillingMode = 'classic';

        parent::tearDown();
    }

    public function test_it_can_be_instantiated()
    {
        $builder = new QuoteBuilder(new User);

        $this->assertInstanceOf(QuoteBuilder::class, $builder);
    }

    public function test_create_requires_at_least_one_line_item()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one line item is required when creating quotes.');

        $builder = new QuoteBuilder(new User);
        $builder->create();
    }

    public function test_add_line_item_returns_builder_instance()
    {
        $builder = new QuoteBuilder(new User);

        $result = $builder->addLineItem('price_test', 1);

        $this->assertSame($builder, $result);
    }

    public function test_description_returns_builder_instance()
    {
        $builder = new QuoteBuilder(new User);

        $result = $builder->description('Test quote');

        $this->assertSame($builder, $result);
    }

    public function test_footer_returns_builder_instance()
    {
        $builder = new QuoteBuilder(new User);

        $result = $builder->footer('Terms and conditions');

        $this->assertSame($builder, $result);
    }

    public function test_header_returns_builder_instance()
    {
        $builder = new QuoteBuilder(new User);

        $result = $builder->header('Quote #123');

        $this->assertSame($builder, $result);
    }

    public function test_expires_at_accepts_timestamp()
    {
        $builder = new QuoteBuilder(new User);

        $result = $builder->expiresAt(1700000000);

        $this->assertSame($builder, $result);
    }

    public function test_expires_at_accepts_datetime()
    {
        $builder = new QuoteBuilder(new User);

        $result = $builder->expiresAt(new \DateTimeImmutable('+7 days'));

        $this->assertSame($builder, $result);
    }

    public function test_with_metadata_returns_builder_instance()
    {
        $builder = new QuoteBuilder(new User);

        $result = $builder->withMetadata(['key' => 'value']);

        $this->assertSame($builder, $result);
    }

    public function test_with_billing_mode_returns_builder_instance()
    {
        $builder = new QuoteBuilder(new User);

        $result = $builder->withBillingMode('flexible');

        $this->assertSame($builder, $result);
    }

    public function test_line_items_can_be_set_in_bulk()
    {
        $builder = new QuoteBuilder(new User);

        $result = $builder->lineItems([
            ['price' => 'price_a', 'quantity' => 1],
            ['price' => 'price_b', 'quantity' => 2],
        ]);

        $this->assertSame($builder, $result);
    }

    public function test_multiple_line_items_can_be_added_individually()
    {
        $builder = new QuoteBuilder(new User);

        $builder->addLineItem('price_a', 1);
        $builder->addLineItem('price_b', 2);
        $builder->addLineItem('price_c');

        $this->assertInstanceOf(QuoteBuilder::class, $builder);
    }
}

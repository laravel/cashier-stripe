<?php

namespace Laravel\Cashier\Tests\Unit;

use App\Models\User;
use Laravel\Cashier\SubscriptionBuilder;
use PHPUnit\Framework\TestCase;

class SubscriptionBuilderTest extends TestCase
{
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

    public function test_price_tax_rates_are_applied_to_array_prices()
    {
        $user = new User;
        $user->priceTaxRates = ['price_bar' => ['txr_123']];

        $builder = new SubscriptionBuilder($user, 'default', [
            'price_foo',
            ['price' => 'price_bar', 'quantity' => 2],
        ]);

        $this->assertSame([
            'price_foo' => ['price' => 'price_foo', 'quantity' => 1],
            'price_bar' => ['price' => 'price_bar', 'quantity' => 2, 'tax_rates' => ['txr_123']],
        ], $builder->getItems());
    }

    public function test_quantity_without_price_and_no_items_throws_exception()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No price specified for quantity update.');

        $builder = new SubscriptionBuilder(new User, 'default');

        $builder->quantity(1);
    }
}

<?php

namespace Laravel\Cashier\Tests\Unit;

use Laravel\Cashier\SubscriptionBuilder;
use Laravel\Cashier\Tests\Fixtures\User;
use PHPUnit\Framework\TestCase;

class SubscriptionBuilderTest extends TestCase
{
    public function test_it_can_be_instantiated_with_both_normal_and_metered_prices()
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
}

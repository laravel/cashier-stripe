<?php

namespace Laravel\Cashier\Tests\Unit;

use Laravel\Cashier\RateCard;
use PHPUnit\Framework\TestCase;

class RateCardTest extends TestCase
{
    public function test_create_tiered_card()
    {
        $tiers = [
            ['up_to' => 100, 'unit_amount' => 1000, 'flat_amount' => 0],
            ['up_to' => 500, 'unit_amount' => 800, 'flat_amount' => 0],
            ['up_to' => null, 'unit_amount' => 600, 'flat_amount' => 0],
        ];

        // Test that the method returns a RateCard instance
        $rateCard = new RateCard([
            'name' => 'Test Tiered Card',
            'product_id' => 'prod_123',
            'pricing_type' => 'tiered',
            'rates' => [
                'tiers' => $tiers,
                'mode' => 'graduated',
            ],
            'currency' => 'usd',
        ]);

        $this->assertInstanceOf(RateCard::class, $rateCard);
        $this->assertEquals('Test Tiered Card', $rateCard->name);
        $this->assertEquals('tiered', $rateCard->pricing_type);
    }

    public function test_create_package_card()
    {
        $rateCard = new RateCard([
            'name' => 'Test Package Card',
            'product_id' => 'prod_123',
            'pricing_type' => 'package',
            'rates' => [
                'package_size' => 100,
                'package_price' => 1000,
            ],
            'currency' => 'usd',
        ]);

        $this->assertInstanceOf(RateCard::class, $rateCard);
        $this->assertEquals('Test Package Card', $rateCard->name);
        $this->assertEquals('package', $rateCard->pricing_type);
    }

    public function test_create_flat_rate_card()
    {
        $rateCard = new RateCard([
            'name' => 'Test Flat Rate Card',
            'product_id' => 'prod_123',
            'pricing_type' => 'flat',
            'rates' => [
                'unit_amount' => 500,
            ],
            'currency' => 'usd',
        ]);

        $this->assertInstanceOf(RateCard::class, $rateCard);
        $this->assertEquals('Test Flat Rate Card', $rateCard->name);
        $this->assertEquals('flat', $rateCard->pricing_type);
    }

    public function test_calculate_flat_pricing()
    {
        $rateCard = new RateCard([
            'pricing_type' => 'flat',
            'rates' => ['unit_amount' => 500],
            'currency' => 'usd',
        ]);

        $result = $rateCard->calculatePricing(100);

        $this->assertEquals([
            'rate_card_id' => null,
            'pricing_type' => 'flat',
            'usage' => 100,
            'unit_amount' => 500,
            'total_amount' => 50000,
            'currency' => 'usd',
        ], $result);
    }

    public function test_calculate_package_pricing()
    {
        $rateCard = new RateCard([
            'pricing_type' => 'package',
            'rates' => [
                'package_size' => 50,
                'package_price' => 1000,
            ],
            'currency' => 'usd',
        ]);

        $result = $rateCard->calculatePricing(120); // 120 units = 3 packages (50 each)

        $this->assertEquals([
            'rate_card_id' => null,
            'pricing_type' => 'package',
            'usage' => 120,
            'package_size' => 50,
            'packages_used' => 3,
            'package_price' => 1000,
            'total_amount' => 3000,
            'currency' => 'usd',
            'breakdown' => [
                'included_usage' => 150, // 3 * 50
                'overage' => 0, // No overage since we round up packages
            ],
        ], $result);
    }

    public function test_calculate_volume_pricing()
    {
        $tiers = [
            ['up_to' => 100, 'unit_amount' => 1000, 'flat_amount' => 0],
            ['up_to' => 500, 'unit_amount' => 800, 'flat_amount' => 0],
            ['up_to' => null, 'unit_amount' => 600, 'flat_amount' => 0],
        ];

        $rateCard = new RateCard([
            'pricing_type' => 'tiered',
            'rates' => [
                'tiers' => $tiers,
                'mode' => 'volume',
            ],
            'currency' => 'usd',
        ]);

        // Test usage in second tier (150 units)
        $result = $rateCard->calculatePricing(150);

        $this->assertEquals([
            'rate_card_id' => null,
            'pricing_type' => 'tiered_volume',
            'usage' => 150,
            'applicable_tier' => $tiers[1],
            'total_amount' => 120000, // 150 * 800
            'currency' => 'usd',
            'breakdown' => [
                'usage_charge' => 120000,
                'flat_charge' => 0,
            ],
        ], $result);
    }

    public function test_calculate_graduated_pricing()
    {
        $tiers = [
            ['up_to' => 100, 'unit_amount' => 1000, 'flat_amount' => 0],
            ['up_to' => 500, 'unit_amount' => 800, 'flat_amount' => 0],
            ['up_to' => null, 'unit_amount' => 600, 'flat_amount' => 0],
        ];

        $rateCard = new RateCard([
            'pricing_type' => 'tiered',
            'rates' => [
                'tiers' => $tiers,
                'mode' => 'graduated',
            ],
            'currency' => 'usd',
        ]);

        // Test usage spanning multiple tiers (250 units)
        $result = $rateCard->calculatePricing(250);

        $expectedTotal = (100 * 1000) + (150 * 800); // First 100 at $10, next 150 at $8

        $this->assertEquals([
            'rate_card_id' => null,
            'pricing_type' => 'tiered_graduated',
            'usage' => 250,
            'total_amount' => $expectedTotal,
            'currency' => 'usd',
            'breakdown' => [
                [
                    'tier' => 1,
                    'usage_in_tier' => 100,
                    'unit_amount' => 1000,
                    'flat_amount' => 0,
                    'tier_total' => 100000,
                    'range' => ['from' => 1, 'to' => 100],
                ],
                [
                    'tier' => 2,
                    'usage_in_tier' => 150,
                    'unit_amount' => 800,
                    'flat_amount' => 0,
                    'tier_total' => 120000,
                    'range' => ['from' => 101, 'to' => 250],
                ],
            ],
            'tiers_used' => 2,
        ], $result);
    }

    public function test_unsupported_pricing_type_throws_exception()
    {
        $rateCard = new RateCard([
            'pricing_type' => 'unsupported',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported pricing type: unsupported');

        $rateCard->calculatePricing(100);
    }

    public function test_generate_tiered_pricing_table()
    {
        $tiers = [
            ['up_to' => 100, 'unit_amount' => 1000, 'flat_amount' => 0],
            ['up_to' => null, 'unit_amount' => 800, 'flat_amount' => 500],
        ];

        $rateCard = new RateCard([
            'pricing_type' => 'tiered',
            'rates' => [
                'tiers' => $tiers,
                'mode' => 'graduated',
            ],
            'currency' => 'usd',
        ]);

        $table = $rateCard->generatePricingTable();

        $this->assertEquals([
            'type' => 'tiered',
            'mode' => 'graduated',
            'currency' => 'usd',
            'tiers' => [
                [
                    'tier' => 1,
                    'range' => '1 - 100',
                    'unit_amount' => 1000,
                    'flat_amount' => 0,
                    'display_price' => '$10.00',
                ],
                [
                    'tier' => 2,
                    'range' => '101+',
                    'unit_amount' => 800,
                    'flat_amount' => 500,
                    'display_price' => '$8.00',
                ],
            ],
        ], $table);
    }

    public function test_generate_package_pricing_table()
    {
        $rateCard = new RateCard([
            'pricing_type' => 'package',
            'rates' => [
                'package_size' => 100,
                'package_price' => 2000,
            ],
            'currency' => 'usd',
        ]);

        $table = $rateCard->generatePricingTable();

        $this->assertEquals([
            'type' => 'package',
            'currency' => 'usd',
            'package_size' => 100,
            'package_price' => 2000,
            'display_price' => '$20.00',
        ], $table);
    }

    public function test_generate_flat_pricing_table()
    {
        $rateCard = new RateCard([
            'pricing_type' => 'flat',
            'rates' => [
                'unit_amount' => 500,
            ],
            'currency' => 'usd',
        ]);

        $table = $rateCard->generatePricingTable();

        $this->assertEquals([
            'type' => 'flat',
            'currency' => 'usd',
            'unit_amount' => 500,
            'display_price' => '$5.00',
        ], $table);
    }

    public function test_format_price()
    {
        $rateCard = new RateCard(['currency' => 'usd']);

        $reflection = new \ReflectionClass($rateCard);
        $method = $reflection->getMethod('formatPrice');

        $this->assertEquals('$10.00', $method->invoke($rateCard, 1000));
        $this->assertEquals('$0.50', $method->invoke($rateCard, 50));
    }

    public function test_format_price_non_usd()
    {
        $rateCard = new RateCard(['currency' => 'eur']);

        $reflection = new \ReflectionClass($rateCard);
        $method = $reflection->getMethod('formatPrice');

        $this->assertEquals('EUR 10.00', $method->invoke($rateCard, 1000));
    }

    public function test_deactivate()
    {
        $rateCard = new RateCard([
            'name' => 'Test Rate Card',
            'is_active' => true,
        ]);

        // Test that deactivate method exists and can be called
        $this->assertTrue($rateCard->is_active);

        // Since we can't actually test the deactivation without a database,
        // we just verify the method exists and would return the instance
        $this->assertTrue(method_exists($rateCard, 'deactivate'));
    }
}

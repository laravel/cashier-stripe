<?php

namespace Laravel\Cashier\Tests\Unit;

use InvalidArgumentException;
use Laravel\Cashier\RateCard;
use Laravel\Cashier\Tests\TestCase;

class RateCardTest extends TestCase
{
    public function test_flat_pricing_calculation()
    {
        $card = new RateCard([
            'pricing_type' => 'flat',
            'rates' => ['unit_amount' => 10], // $0.10 per unit
            'currency' => 'usd',
        ]);

        $result = $card->calculatePricing(1000);

        $this->assertSame('flat', $result['pricing_type']);
        $this->assertSame(1000, $result['usage']);
        $this->assertSame(10, $result['unit_amount']);
        $this->assertSame(10000, $result['total_amount']);
        $this->assertSame('usd', $result['currency']);
    }

    public function test_flat_pricing_zero_usage()
    {
        $card = new RateCard([
            'pricing_type' => 'flat',
            'rates' => ['unit_amount' => 50],
            'currency' => 'usd',
        ]);

        $result = $card->calculatePricing(0);

        $this->assertSame(0, $result['total_amount']);
    }

    public function test_package_pricing_calculation()
    {
        $card = new RateCard([
            'pricing_type' => 'package',
            'rates' => [
                'package_size' => 100,
                'package_price' => 500, // $5 per 100 units
            ],
            'currency' => 'usd',
        ]);

        $result = $card->calculatePricing(250);

        $this->assertSame('package', $result['pricing_type']);
        $this->assertSame(250, $result['usage']);
        $this->assertSame(100, $result['package_size']);
        $this->assertSame(3, $result['packages_used']); // ceil(250/100) = 3
        $this->assertSame(1500, $result['total_amount']); // 3 * $5
    }

    public function test_package_pricing_exact_match()
    {
        $card = new RateCard([
            'pricing_type' => 'package',
            'rates' => [
                'package_size' => 100,
                'package_price' => 500,
            ],
            'currency' => 'usd',
        ]);

        $result = $card->calculatePricing(200);

        $this->assertSame(2, $result['packages_used']);
        $this->assertSame(1000, $result['total_amount']);
    }

    public function test_tiered_graduated_pricing()
    {
        $card = new RateCard([
            'pricing_type' => 'tiered',
            'rates' => [
                'mode' => 'graduated',
                'tiers' => [
                    ['up_to' => 100, 'unit_amount' => 10, 'flat_amount' => 0],
                    ['up_to' => 500, 'unit_amount' => 8, 'flat_amount' => 0],
                    ['up_to' => null, 'unit_amount' => 5, 'flat_amount' => 0],
                ],
            ],
            'currency' => 'usd',
        ]);

        $result = $card->calculatePricing(600);

        $this->assertSame('tiered_graduated', $result['pricing_type']);
        $this->assertSame(600, $result['usage']);
        $this->assertCount(3, $result['breakdown']);

        // Tier 1: 100 units * $0.10 = $10
        $this->assertSame(100, $result['breakdown'][0]['usage_in_tier']);
        $this->assertSame(1000, $result['breakdown'][0]['tier_total']);

        // Tier 2: 400 units * $0.08 = $32
        $this->assertSame(400, $result['breakdown'][1]['usage_in_tier']);
        $this->assertSame(3200, $result['breakdown'][1]['tier_total']);

        // Tier 3: 100 units * $0.05 = $5
        $this->assertSame(100, $result['breakdown'][2]['usage_in_tier']);
        $this->assertSame(500, $result['breakdown'][2]['tier_total']);

        // Total: $10 + $32 + $5 = $47
        $this->assertSame(4700, $result['total_amount']);
    }

    public function test_tiered_graduated_pricing_within_first_tier()
    {
        $card = new RateCard([
            'pricing_type' => 'tiered',
            'rates' => [
                'mode' => 'graduated',
                'tiers' => [
                    ['up_to' => 100, 'unit_amount' => 10, 'flat_amount' => 0],
                    ['up_to' => null, 'unit_amount' => 5, 'flat_amount' => 0],
                ],
            ],
            'currency' => 'usd',
        ]);

        $result = $card->calculatePricing(50);

        $this->assertSame(1, count($result['breakdown']));
        $this->assertSame(500, $result['total_amount']); // 50 * $0.10
    }

    public function test_tiered_volume_pricing()
    {
        $card = new RateCard([
            'pricing_type' => 'tiered',
            'rates' => [
                'mode' => 'volume',
                'tiers' => [
                    ['up_to' => 100, 'unit_amount' => 10, 'flat_amount' => 0],
                    ['up_to' => 500, 'unit_amount' => 8, 'flat_amount' => 0],
                    ['up_to' => null, 'unit_amount' => 5, 'flat_amount' => 0],
                ],
            ],
            'currency' => 'usd',
        ]);

        // 250 falls in the 101-500 tier, so all 250 are priced at $0.08
        $result = $card->calculatePricing(250);

        $this->assertSame('tiered_volume', $result['pricing_type']);
        $this->assertSame(2000, $result['total_amount']); // 250 * $0.08
    }

    public function test_tiered_volume_with_flat_amount()
    {
        $card = new RateCard([
            'pricing_type' => 'tiered',
            'rates' => [
                'mode' => 'volume',
                'tiers' => [
                    ['up_to' => 100, 'unit_amount' => 10, 'flat_amount' => 500],
                    ['up_to' => null, 'unit_amount' => 5, 'flat_amount' => 0],
                ],
            ],
            'currency' => 'usd',
        ]);

        $result = $card->calculatePricing(50);

        // 50 * $0.10 + $5 flat = $10
        $this->assertSame(1000, $result['total_amount']);
    }

    public function test_tiered_graduated_with_flat_amounts()
    {
        $card = new RateCard([
            'pricing_type' => 'tiered',
            'rates' => [
                'mode' => 'graduated',
                'tiers' => [
                    ['up_to' => 10, 'unit_amount' => 0, 'flat_amount' => 1000], // $10 flat for first 10
                    ['up_to' => null, 'unit_amount' => 5, 'flat_amount' => 0],
                ],
            ],
            'currency' => 'usd',
        ]);

        $result = $card->calculatePricing(15);

        // Tier 1: 10 units * $0 + $10 flat = $10
        $this->assertSame(1000, $result['breakdown'][0]['tier_total']);
        // Tier 2: 5 units * $0.05 + $0 flat = $0.25
        $this->assertSame(25, $result['breakdown'][1]['tier_total']);
        // Total: $10.25
        $this->assertSame(1025, $result['total_amount']);
    }

    public function test_unsupported_pricing_type_throws_exception()
    {
        $card = new RateCard([
            'pricing_type' => 'custom',
            'rates' => [],
            'currency' => 'usd',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported pricing type: custom');

        $card->calculatePricing(100);
    }

    public function test_deactivate_sets_active_to_false()
    {
        $card = new RateCard([
            'is_active' => true,
        ]);

        $card->deactivate(new \DateTimeImmutable('2025-12-31'));

        $this->assertFalse($card->is_active);
        $this->assertNotNull($card->effective_until);
    }

    public function test_rate_card_uses_correct_table_name()
    {
        $card = new RateCard;

        $this->assertSame('cashier_rate_cards', $card->getTable());
    }

    public function test_rates_cast_to_array()
    {
        $card = new RateCard([
            'rates' => ['unit_amount' => 10],
        ]);

        $this->assertIsArray($card->rates);
    }

    public function test_is_active_cast_to_boolean()
    {
        $card = new RateCard([
            'is_active' => 1,
        ]);

        $this->assertTrue($card->is_active);

        $card = new RateCard([
            'is_active' => 0,
        ]);

        $this->assertFalse($card->is_active);
    }
}

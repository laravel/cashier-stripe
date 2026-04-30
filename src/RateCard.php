<?php

namespace Laravel\Cashier;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Laravel\Cashier\Concerns\InteractsWithStripe;

class RateCard extends Model
{
    use InteractsWithStripe;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'rate_cards';

    /**
     * The attributes that are not mass assignable.
     *
     * @var array<string>
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'rates' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'effective_from' => 'datetime',
        'effective_until' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Create a new rate card with tiered pricing.
     *
     * @param  string  $name
     * @param  string  $productId
     * @param  array  $tiers
     * @param  array  $options
     * @return static
     */
    public static function createTieredCard(string $name, string $productId, array $tiers, array $options = []): self
    {
        return static::create([
            'name' => $name,
            'product_id' => $productId,
            'pricing_type' => 'tiered',
            'rates' => [
                'tiers' => $tiers,
                'mode' => $options['mode'] ?? 'graduated',
            ],
            'currency' => $options['currency'] ?? 'usd',
            'interval' => $options['interval'] ?? 'month',
            'metadata' => $options['metadata'] ?? [],
            'is_active' => $options['is_active'] ?? true,
            'effective_from' => $options['effective_from'] ?? now(),
        ]);
    }

    /**
     * Create a new rate card with package pricing.
     *
     * @param  string  $name
     * @param  string  $productId
     * @param  int  $packageSize
     * @param  int  $packagePrice
     * @param  array  $options
     * @return static
     */
    public static function createPackageCard(string $name, string $productId, int $packageSize, int $packagePrice, array $options = []): self
    {
        return static::create([
            'name' => $name,
            'product_id' => $productId,
            'pricing_type' => 'package',
            'rates' => [
                'package_size' => $packageSize,
                'package_price' => $packagePrice,
            ],
            'currency' => $options['currency'] ?? 'usd',
            'interval' => $options['interval'] ?? 'month',
            'metadata' => $options['metadata'] ?? [],
            'is_active' => $options['is_active'] ?? true,
            'effective_from' => $options['effective_from'] ?? now(),
        ]);
    }

    /**
     * Create a new rate card with flat rate pricing.
     *
     * @param  string  $name
     * @param  string  $productId
     * @param  int  $unitAmount
     * @param  array  $options
     * @return static
     */
    public static function createFlatRateCard(string $name, string $productId, int $unitAmount, array $options = []): self
    {
        return static::create([
            'name' => $name,
            'product_id' => $productId,
            'pricing_type' => 'flat',
            'rates' => [
                'unit_amount' => $unitAmount,
            ],
            'currency' => $options['currency'] ?? 'usd',
            'interval' => $options['interval'] ?? 'month',
            'metadata' => $options['metadata'] ?? [],
            'is_active' => $options['is_active'] ?? true,
            'effective_from' => $options['effective_from'] ?? now(),
        ]);
    }

    /**
     * Calculate pricing based on this rate card.
     *
     * @param  int  $usage
     * @return array
     */
    public function calculatePricing(int $usage): array
    {
        switch ($this->pricing_type) {
            case 'tiered':
                return $this->calculateTieredPricing($usage);
            case 'package':
                return $this->calculatePackagePricing($usage);
            case 'flat':
                return $this->calculateFlatPricing($usage);
            default:
                throw new \InvalidArgumentException("Unsupported pricing type: {$this->pricing_type}");
        }
    }

    /**
     * Calculate tiered pricing.
     *
     * @param  int  $usage
     * @return array
     */
    protected function calculateTieredPricing(int $usage): array
    {
        $tiers = $this->rates['tiers'] ?? [];
        $mode = $this->rates['mode'] ?? 'graduated';

        if ($mode === 'volume') {
            return $this->calculateVolumePricing($usage, $tiers);
        }

        return $this->calculateGraduatedPricing($usage, $tiers);
    }

    /**
     * Calculate volume pricing.
     *
     * @param  int  $usage
     * @param  array  $tiers
     * @return array
     */
    protected function calculateVolumePricing(int $usage, array $tiers): array
    {
        $applicableTier = null;

        foreach ($tiers as $tier) {
            if (($tier['up_to'] ?? null) === null || $usage <= $tier['up_to']) {
                $applicableTier = $tier;
                break;
            }
        }

        if (! $applicableTier) {
            $applicableTier = end($tiers);
        }

        $unitAmount = $applicableTier['unit_amount'] ?? 0;
        $flatAmount = $applicableTier['flat_amount'] ?? 0;
        $total = ($usage * $unitAmount) + $flatAmount;

        return [
            'rate_card_id' => $this->id,
            'pricing_type' => 'tiered_volume',
            'usage' => $usage,
            'applicable_tier' => $applicableTier,
            'total_amount' => $total,
            'currency' => $this->currency,
            'breakdown' => [
                'usage_charge' => $usage * $unitAmount,
                'flat_charge' => $flatAmount,
            ],
        ];
    }

    /**
     * Calculate graduated pricing.
     *
     * @param  int  $usage
     * @param  array  $tiers
     * @return array
     */
    protected function calculateGraduatedPricing(int $usage, array $tiers): array
    {
        $total = 0;
        $breakdown = [];
        $remainingUsage = $usage;
        $previousLimit = 0;

        foreach ($tiers as $index => $tier) {
            if ($remainingUsage <= 0) {
                break;
            }

            $tierLimit = $tier['up_to'] ?? PHP_INT_MAX;
            $tierUsage = min($remainingUsage, $tierLimit - $previousLimit);

            if ($tierUsage > 0) {
                $unitAmount = $tier['unit_amount'] ?? 0;
                $flatAmount = $tier['flat_amount'] ?? 0;
                $tierTotal = ($tierUsage * $unitAmount) + $flatAmount;

                $breakdown[] = [
                    'tier' => $index + 1,
                    'usage_in_tier' => $tierUsage,
                    'unit_amount' => $unitAmount,
                    'flat_amount' => $flatAmount,
                    'tier_total' => $tierTotal,
                    'range' => [
                        'from' => $previousLimit + 1,
                        'to' => min($tierLimit, $previousLimit + $tierUsage),
                    ],
                ];

                $total += $tierTotal;
                $remainingUsage -= $tierUsage;
            }

            $previousLimit = $tierLimit;
        }

        return [
            'rate_card_id' => $this->id,
            'pricing_type' => 'tiered_graduated',
            'usage' => $usage,
            'total_amount' => $total,
            'currency' => $this->currency,
            'breakdown' => $breakdown,
            'tiers_used' => count($breakdown),
        ];
    }

    /**
     * Calculate package pricing.
     *
     * @param  int  $usage
     * @return array
     */
    protected function calculatePackagePricing(int $usage): array
    {
        $packageSize = $this->rates['package_size'] ?? 1;
        $packagePrice = $this->rates['package_price'] ?? 0;

        $packagesUsed = ceil($usage / $packageSize);
        $totalAmount = $packagesUsed * $packagePrice;

        return [
            'rate_card_id' => $this->id,
            'pricing_type' => 'package',
            'usage' => $usage,
            'package_size' => $packageSize,
            'packages_used' => $packagesUsed,
            'package_price' => $packagePrice,
            'total_amount' => $totalAmount,
            'currency' => $this->currency,
            'breakdown' => [
                'included_usage' => $packagesUsed * $packageSize,
                'overage' => max(0, $usage - ($packagesUsed * $packageSize)),
            ],
        ];
    }

    /**
     * Calculate flat rate pricing.
     *
     * @param  int  $usage
     * @return array
     */
    protected function calculateFlatPricing(int $usage): array
    {
        $unitAmount = $this->rates['unit_amount'] ?? 0;
        $totalAmount = $usage * $unitAmount;

        return [
            'rate_card_id' => $this->id,
            'pricing_type' => 'flat',
            'usage' => $usage,
            'unit_amount' => $unitAmount,
            'total_amount' => $totalAmount,
            'currency' => $this->currency,
        ];
    }

    /**
     * Create corresponding Stripe price from this rate card.
     *
     * @param  array  $options
     * @return \Stripe\Price
     */
    public function createStripePrice(array $options = []): \Stripe\Price
    {
        $priceData = [
            'product' => $this->product_id,
            'currency' => $this->currency,
            'recurring' => [
                'interval' => $this->interval,
                'usage_type' => 'metered',
            ],
            'nickname' => $this->name,
            'metadata' => array_merge([
                'rate_card_id' => $this->id,
            ], $this->metadata ?? []),
        ];

        switch ($this->pricing_type) {
            case 'tiered':
                $priceData['billing_scheme'] = 'tiered';
                $priceData['tiers_mode'] = $this->rates['mode'] ?? 'graduated';
                $priceData['tiers'] = $this->formatTiersForStripe($this->rates['tiers'] ?? []);
                break;

            case 'package':
                $priceData['billing_scheme'] = 'per_unit';
                $priceData['unit_amount'] = $this->rates['package_price'];
                $priceData['transform_quantity'] = [
                    'divide_by' => $this->rates['package_size'],
                    'round' => 'up',
                ];
                break;

            case 'flat':
                $priceData['billing_scheme'] = 'per_unit';
                $priceData['unit_amount'] = $this->rates['unit_amount'];
                break;
        }

        return static::stripe()->prices->create(array_merge($priceData, $options));
    }

    /**
     * Format tiers for Stripe API.
     *
     * @param  array  $tiers
     * @return array
     */
    protected function formatTiersForStripe(array $tiers): array
    {
        return array_map(function ($tier) {
            return array_filter([
                'up_to' => $tier['up_to'] ?? null,
                'unit_amount' => $tier['unit_amount'] ?? null,
                'flat_amount' => $tier['flat_amount'] ?? null,
            ]);
        }, $tiers);
    }

    /**
     * Get active rate cards for a product.
     *
     * @param  string  $productId
     * @return \Illuminate\Support\Collection
     */
    public static function activeForProduct(string $productId): Collection
    {
        return static::where('product_id', $productId)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('effective_until')
                    ->orWhere('effective_until', '>', now());
            })
            ->where('effective_from', '<=', now())
            ->get();
    }

    /**
     * Compare this rate card against others for given usage.
     *
     * @param  int  $usage
     * @param  array  $otherRateCardIds
     * @return array
     */
    public function compareAgainst(int $usage, array $otherRateCardIds): array
    {
        $comparisons = [];

        // Calculate for this rate card
        $thisCalculation = $this->calculatePricing($usage);
        $comparisons[] = array_merge($thisCalculation, [
            'rate_card' => $this->toArray(),
        ]);

        // Calculate for other rate cards
        $otherCards = static::whereIn('id', $otherRateCardIds)->get();
        foreach ($otherCards as $card) {
            $calculation = $card->calculatePricing($usage);
            $comparisons[] = array_merge($calculation, [
                'rate_card' => $card->toArray(),
            ]);
        }

        // Sort by total amount
        usort($comparisons, fn ($a, $b) => $a['total_amount'] <=> $b['total_amount']);

        return [
            'usage' => $usage,
            'comparisons' => $comparisons,
            'most_economical' => $comparisons[0] ?? null,
            'current_card_rank' => array_search($this->id, array_column($comparisons, 'rate_card_id')) + 1,
        ];
    }

    /**
     * Deactivate this rate card.
     *
     * @param  \DateTimeInterface|null  $effectiveUntil
     * @return $this
     */
    public function deactivate($effectiveUntil = null): self
    {
        $this->update([
            'is_active' => false,
            'effective_until' => $effectiveUntil ?? now(),
        ]);

        return $this;
    }

    /**
     * Generate a pricing table array for display.
     *
     * @return array
     */
    public function generatePricingTable(): array
    {
        switch ($this->pricing_type) {
            case 'tiered':
                return $this->generateTieredTable();
            case 'package':
                return $this->generatePackageTable();
            case 'flat':
                return $this->generateFlatTable();
            default:
                return [];
        }
    }

    /**
     * Generate tiered pricing table.
     *
     * @return array
     */
    protected function generateTieredTable(): array
    {
        $tiers = $this->rates['tiers'] ?? [];
        $mode = $this->rates['mode'] ?? 'graduated';

        return [
            'type' => 'tiered',
            'mode' => $mode,
            'currency' => $this->currency,
            'tiers' => array_map(function ($tier, $index) {
                return [
                    'tier' => $index + 1,
                    'range' => $this->formatTierRange($tier, $index),
                    'unit_amount' => $tier['unit_amount'] ?? 0,
                    'flat_amount' => $tier['flat_amount'] ?? 0,
                    'display_price' => $this->formatPrice($tier['unit_amount'] ?? 0),
                ];
            }, $tiers, array_keys($tiers)),
        ];
    }

    /**
     * Generate package pricing table.
     *
     * @return array
     */
    protected function generatePackageTable(): array
    {
        return [
            'type' => 'package',
            'currency' => $this->currency,
            'package_size' => $this->rates['package_size'] ?? 1,
            'package_price' => $this->rates['package_price'] ?? 0,
            'display_price' => $this->formatPrice($this->rates['package_price'] ?? 0),
        ];
    }

    /**
     * Generate flat rate pricing table.
     *
     * @return array
     */
    protected function generateFlatTable(): array
    {
        return [
            'type' => 'flat',
            'currency' => $this->currency,
            'unit_amount' => $this->rates['unit_amount'] ?? 0,
            'display_price' => $this->formatPrice($this->rates['unit_amount'] ?? 0),
        ];
    }

    /**
     * Format tier range for display.
     *
     * @param  array  $tier
     * @param  int  $index
     * @return string
     */
    protected function formatTierRange(array $tier, int $index): string
    {
        $upTo = $tier['up_to'] ?? null;
        $previous = $index > 0 ? ($this->rates['tiers'][$index - 1]['up_to'] ?? 0) : 0;

        if ($upTo === null) {
            return ($previous + 1).'+';
        }

        return ($previous + 1).' - '.$upTo;
    }

    /**
     * Format price for display.
     *
     * @param  int  $amount
     * @return string
     */
    protected function formatPrice(int $amount): string
    {
        return ($this->currency === 'usd' ? '$' : strtoupper($this->currency).' ').
               number_format($amount / 100, 2);
    }
}

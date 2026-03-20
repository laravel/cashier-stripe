<?php

namespace Laravel\Cashier;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Laravel\Cashier\Database\Factories\RateCardFactory;

/**
 * Rate cards are used for local pricing calculations and are not synced with Stripe.
 *
 * This is by design — rate cards represent your application's own pricing models
 * (tiered, package, or flat-rate) for usage-based billing calculations. They exist
 * purely in your local database and are not tied to any Stripe pricing object.
 * Stripe pricing is managed separately through Stripe Price and Product objects.
 *
 * @see \Laravel\Cashier\SubscriptionBuilder for how Stripe prices are used
 */
class RateCard extends Model
{
    use HasFactory;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cashier_rate_cards';

    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'rates' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'effective_from' => 'datetime',
        'effective_until' => 'datetime',
    ];

    /**
     * Calculate pricing based on this rate card and a given usage amount.
     *
     * @param  int  $usage
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    public function calculatePricing(int $usage): array
    {
        return match ($this->pricing_type) {
            'tiered' => $this->calculateTieredPricing($usage),
            'package' => $this->calculatePackagePricing($usage),
            'flat' => $this->calculateFlatPricing($usage),
            default => throw new InvalidArgumentException("Unsupported pricing type: {$this->pricing_type}"),
        };
    }

    /**
     * Calculate tiered pricing (graduated or volume).
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
     * Calculate volume pricing where the entire usage is priced at the applicable tier.
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
            $applicableTier = end($tiers) ?: ['unit_amount' => 0, 'flat_amount' => 0];
        }

        $unitAmount = $applicableTier['unit_amount'] ?? 0;
        $flatAmount = $applicableTier['flat_amount'] ?? 0;

        return [
            'pricing_type' => 'tiered_volume',
            'usage' => $usage,
            'total_amount' => ($usage * $unitAmount) + $flatAmount,
            'currency' => $this->currency,
        ];
    }

    /**
     * Calculate graduated pricing where each tier prices only usage within that tier's range.
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
                ];

                $total += $tierTotal;
                $remainingUsage -= $tierUsage;
            }

            $previousLimit = $tierLimit;
        }

        return [
            'pricing_type' => 'tiered_graduated',
            'usage' => $usage,
            'total_amount' => $total,
            'currency' => $this->currency,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Calculate package pricing where usage is rounded up to the nearest package.
     *
     * @param  int  $usage
     * @return array
     */
    protected function calculatePackagePricing(int $usage): array
    {
        $packageSize = $this->rates['package_size'] ?? 1;
        $packagePrice = $this->rates['package_price'] ?? 0;

        $packagesUsed = (int) ceil($usage / max($packageSize, 1));

        return [
            'pricing_type' => 'package',
            'usage' => $usage,
            'package_size' => $packageSize,
            'packages_used' => $packagesUsed,
            'total_amount' => $packagesUsed * $packagePrice,
            'currency' => $this->currency,
        ];
    }

    /**
     * Calculate flat rate pricing (per-unit).
     *
     * @param  int  $usage
     * @return array
     */
    protected function calculateFlatPricing(int $usage): array
    {
        $unitAmount = $this->rates['unit_amount'] ?? 0;

        return [
            'pricing_type' => 'flat',
            'usage' => $usage,
            'unit_amount' => $unitAmount,
            'total_amount' => $usage * $unitAmount,
            'currency' => $this->currency,
        ];
    }

    /**
     * Deactivate this rate card.
     *
     * @param  \DateTimeInterface|null  $effectiveUntil
     * @return $this
     */
    public function deactivate(?\DateTimeInterface $effectiveUntil = null): static
    {
        $this->is_active = false;
        $this->effective_until = $effectiveUntil ?? Carbon::now();

        return $this;
    }

    /**
     * Scope to only active rate cards.
     *
     * @param  \Illuminate\Contracts\Database\Eloquent\Builder  $query
     * @return \Illuminate\Contracts\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('effective_until')
                    ->orWhere('effective_until', '>', Carbon::now());
            });
    }

    /**
     * Scope to rate cards for a specific product.
     *
     * @param  \Illuminate\Contracts\Database\Eloquent\Builder  $query
     * @param  string  $productId
     * @return \Illuminate\Contracts\Database\Eloquent\Builder
     */
    public function scopeForProduct($query, string $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return RateCardFactory::new();
    }
}

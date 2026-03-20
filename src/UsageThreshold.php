<?php

namespace Laravel\Cashier;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Cashier\Database\Factories\UsageThresholdFactory;

class UsageThreshold extends Model
{
    use HasFactory;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cashier_usage_thresholds';

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
        'threshold' => 'integer',
        'alert_options' => 'array',
    ];

    /**
     * Get the user that owns the usage threshold.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->owner();
    }

    /**
     * Get the model related to the usage threshold.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function owner(): BelongsTo
    {
        $model = Cashier::$customerModel;

        return $this->belongsTo($model, (new $model)->getForeignKey());
    }

    /**
     * Check if a given usage exceeds this threshold.
     *
     * @param  int|float  $currentUsage
     * @return bool
     */
    public function isExceeded(int|float $currentUsage): bool
    {
        return $currentUsage > $this->threshold;
    }

    /**
     * Get the usage as a percentage of this threshold.
     *
     * @param  int|float  $currentUsage
     * @return float
     */
    public function usagePercentage(int|float $currentUsage): float
    {
        if ($this->threshold <= 0) {
            return 0.0;
        }

        return round(($currentUsage / $this->threshold) * 100, 1);
    }

    /**
     * Calculate the overage amount beyond this threshold.
     *
     * @param  int|float  $currentUsage
     * @return int|float
     */
    public function overage(int|float $currentUsage): int|float
    {
        return max(0, $currentUsage - $this->threshold);
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return UsageThresholdFactory::new();
    }
}

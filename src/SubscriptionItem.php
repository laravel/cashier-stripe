<?php

namespace Laravel\Cashier;

use Illuminate\Database\Eloquent\Model;

/**
 * Class SubscriptionItem.
 */
final class SubscriptionItem extends Model
{
    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'created_at', 'updated_at',
    ];
}

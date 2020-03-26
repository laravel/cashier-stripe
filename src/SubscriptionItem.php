<?php

namespace Laravel\Cashier;

use Illuminate\Database\Eloquent\Model;

class SubscriptionItem extends Model
{
    /**
     * Get the subscription where the item belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
}

<?php

namespace Laravel\Cashier;

use Illuminate\Database\Eloquent\Model;
use Stripe\SubscriptionItem as StripeSubscriptionItem;

class SubscriptionItem extends Model
{
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
        'quantity' => 'integer',
    ];

    /**
     * Get the subscription where the item belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Get the subscription as a Stripe subscription item object.
     *
     * @return \Stripe\SubscriptionItem
     */
    public function asStripeSubscriptionItem()
    {
        return StripeSubscriptionItem::retrieve(
            $this->stripe_id,
            $this->subscription->owner->stripeOptions()
        );
    }
}

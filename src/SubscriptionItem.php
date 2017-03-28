<?php

namespace Laravel\Cashier;

use Carbon\Carbon;
use LogicException;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class SubscriptionItem extends Model
{
    /**
     * The attributes that aren't mass assignable.
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
    
    /**
     * Get the subscription as a Stripe subscription object.
     *
     * @return \Stripe\Subscription
     */
    public function asStripeSubscriptionItem()
    {
        $stripeSubscription = $this->subscription->asStripeSubscription();
        // strips out the "subscription" parameter which causes an API error (unknown parameter)
        $stripeSubscription->items->url = substr($stripeSubscription->items->url, 0, strpos($stripeSubscription->items->url, '?'));
        
        return $stripeSubscription->items->retrieve($this->stripe_id);
    }
    
    /**
     * Gets the subscription that contains this item
     */
    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
    
    /**
     * Increment the quantity of the subscription.
     *
     * @param  int  $count
     * @param  bool  $prorate
     * @return $this
     */
    public function incrementQuantity($count = 1, $prorate = true)
    {
        $this->updateQuantity($this->quantity + $count, $prorate);

        return $this;
    }

    /**
     *  Increment the quantity of the subscription, and invoice immediately.
     *
     * @param  int  $count
     * @param  bool  $prorate
     * @return $this
     */
    public function incrementAndInvoice($count = 1, $prorate = true)
    {
        $this->incrementQuantity($count, $prorate);

        $this->subscription->user->invoice();

        return $this;
    }

    /**
     * Decrement the quantity of the subscription.
     *
     * @param  int  $count
     * @param  bool  $prorate
     * @return $this
     */
    public function decrementQuantity($count = 1, $prorate = true)
    {
        $this->updateQuantity(max(1, $this->quantity - $count), $prorate);

        return $this;
    }

    /**
     * Update the quantity of the subscription.
     *
     * @param  int  $quantity
     * @param  bool  $prorate
     * @return $this
     */
    public function updateQuantity($quantity, $prorate = true)
    {
        $subscriptionItem = $this->asStripeSubscriptionItem();

        $subscriptionItem->quantity = $quantity;

        $subscriptionItem->prorate = $prorate;

        $subscriptionItem->save();

        $this->quantity = $quantity;

        $this->save();

        return $this;
    }
}
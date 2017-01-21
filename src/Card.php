<?php

namespace Laravel\Cashier;

use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * Get the user that owns the subscription.
     */
    public function user()
    {
        $model = getenv('STRIPE_MODEL') ?: config('services.stripe.model', 'User');

        return $this->belongsTo($model, 'user_id');
    }

    /**
     * Get the card as a Stripe card object.
     *
     * @return \Stripe\Card
     */
    public function asStripeCard()
    {
        return $this->user->asStripeCustomer()->sources->retrieve($this->card_id);
    }
}

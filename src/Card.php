<?php

namespace Laravel\Cashier;

use Stripe\Card as StripeCard;

class Card
{
    /**
     * The Stripe model instance.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $owner;

    /**
     * The Stripe card instance.
     *
     * @var \Stripe\Card
     */
    protected $card;

    /**
     * Create a new card instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $owner
     * @param  \Stripe\Card  $card
     * @return void
     */
    public function __construct($owner, StripeCard $card)
    {
        $this->card = $card;
        $this->owner = $owner;
    }

    /**
     * Delete the card.
     *
     * @return \Stripe\Card
     */
    public function delete()
    {
        return $this->card->delete();
    }

    /**
     * Get the Stripe card instance.
     *
     * @return \Stripe\Card
     */
    public function asStripeCard()
    {
        return $this->card;
    }

    /**
     * Dynamically get values from the Stripe card.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->card->{$key};
    }
}

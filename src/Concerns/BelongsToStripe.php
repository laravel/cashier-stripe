<?php

namespace Laravel\Cashier\Concerns;

use Laravel\Cashier\Cashier;

trait BelongsToStripe
{
    /**
     * The attribute key containing the Stripe ID.
     *
     * @var string
     */
    protected $stripeKey = 'stripe_id';

    /**
     * Retrieve the Stripe ID.
     *
     * @return string|null
     */
    public function stripeId()
    {
        return $this->{$this->stripeKey};
    }

    /**
     * Determine if the customer has a Stripe ID.
     *
     * @return bool
     */
    public function hasStripeId()
    {
        return ! is_null($this->{$this->stripeKey});
    }

    /**
     * Set the Stripe ID.
     *
     * @param string $stripeId
     *
     * @return $this
     */
    public function setStripeId($stripeId)
    {
        $this->{$this->stripeKey} = $stripeId;

        return $this;
    }

    /**
     * Get the Stripe SDK client.
     *
     * @param  array  $options
     * @return \Stripe\StripeClient
     */
    public static function stripe(array $options = [])
    {
        return Cashier::stripe($options);
    }
}

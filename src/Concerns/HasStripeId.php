<?php

namespace Laravel\Cashier\Concerns;

trait HasStripeId
{
    /**
     * Get the name of the model's "stripe_id" column.
     *
     * @return  string
     */
    public static function stripeIdColumn()
    {
        return 'stripe_id';
    }

    /**
     * Get the Stripe ID for the model.
     *
     * @return null|string
     */
    public function stripeId()
    {
        return $this->{static::stripeIdColumn()};
    }
}

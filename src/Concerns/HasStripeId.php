<?php

namespace Laravel\Cashier\Concerns;

trait HasStripeId
{

    /**
     * The name of the model's "stripe_id" column.
     *
     * @var string
     */
    public static string $stripeIdColumn = 'stripe_id';

    /**
     * Get the Stripe ID for the model.
     *
     * @return null|string
     */
    public function stripeId()
    {
        return $this->{static::$stripeIdColumn};
    }

}

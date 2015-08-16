<?php

namespace Laravel\Cashier;

interface BillableRepositoryInterface
{
    /**
     * Find a Billable implementation by Stripe ID.
     *
     * @param  string  $stripeId
     * @return \Laravel\Cashier\Contracts\Billable
     */
    public function find($stripeId);
}

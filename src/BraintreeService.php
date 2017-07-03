<?php

namespace Laravel\Cashier;

use Laravel\Cashier\Gateway\BraintreeGateway;

class BraintreeService
{
    /**
     * Backwards-compatible call to findPlan().
     *
     * @param  string  $id
     * @return \Braintree\Plan
     * @throws \Exception
     */
    public static function findPlan($id)
    {
        return BraintreeGateway::findPlan($id);
    }
}

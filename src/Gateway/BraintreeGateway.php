<?php

namespace Laravel\Cashier\Gateway;

class BraintreeGateway extends Gateway
{
    public function convertZeroDecimalValue($value)
    {
        return $value / 100;
    }
}

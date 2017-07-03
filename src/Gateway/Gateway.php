<?php

namespace Laravel\Cashier\Gateway;

use Laravel\Cashier\Cashier;

abstract class Gateway
{
    /**
     * Cashier instance.
     *
     * @var \Laravel\Cashier\Cashier
     */
    protected $cashier;

    /**
     * Create gateway.
     *
     * @param  \Laravel\Cashier\Cashier  $cashier
     */
    public function __construct(Cashier $cashier)
    {
        $this->cashier = $cashier;
    }

    /**
     * Convert a zero-decimal value (eg. cents) into the value appropriate for this gateway.
     *
     * @param  int  $value
     * @return int|float
     */
    public function convertZeroDecimalValue($value)
    {
        return $value;
    }
}

<?php

namespace Laravel\Cashier\Contracts;

use Laravel\Cashier\Cashier;

interface Gateway
{
    public function __construct(Cashier $cashier);
    public function convertZeroDecimalValue($value);
}

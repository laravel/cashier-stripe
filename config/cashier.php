<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cashier Settings
    |--------------------------------------------------------------------------
    |
    |
    */

    'default_gateway' => env('CASHIER_GATEWAY', 'stripe'),

    'single_gateway_attributes' => false,
];

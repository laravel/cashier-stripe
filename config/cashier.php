<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cashier Settings
    |--------------------------------------------------------------------------
    |
    |
    */

    'model' => 'App\\User',

    'default_gateway' => env('CASHIER_GATEWAY', 'stripe'),

    'single_gateway_attribute' => false,
];

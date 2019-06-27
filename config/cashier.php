<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Stripe Keys
    |--------------------------------------------------------------------------
    |
    | The Stripe publishable key and secret key give you access to Stripe's
    | API. The Publishable key allows you to interact with public requests
    | like the Stripe.js widgets and the secret key allows you to perform
    | signed requests to retrieve and write your private dashboard data.
    |
    */

    'key' => env('STRIPE_KEY'),

    'secret' => env('STRIPE_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Stripe Webhooks
    |--------------------------------------------------------------------------
    |
    | These settings control the webhook secret and tolerance level for
    | incoming Stripe webhook requests.
    |
    */

    'webhook' => [
        'secret' => env('STRIPE_WEBHOOK_SECRET'),
        'tolerance' => env('STRIPE_WEBHOOK_TOLERANCE', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cashier Model
    |--------------------------------------------------------------------------
    |
    | This is the model in your all that will implement the Billable trait.
    | It'll be the primary model to perform Cashier related methods on and
    | where subscriptions get attached to.
    |
    */

    'model' => env('CASHIER_MODEL', App\User::class),

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | This is the default currency that'll be used to make charges with.
    |
    */

    'currency' => env('CASHIER_CURRENCY', 'usd'),

    /*
    |--------------------------------------------------------------------------
    | Currency Locale
    |--------------------------------------------------------------------------
    |
    | This is the default locale in which your money values are formatted in.
    | To use other locales besides the default "en" locale, make sure you have
    | the ext-intl installed on your environment.
    |
    */

    'currency_locale' => env('CASHIER_CURRENCY_LOCALE', 'en'),

];

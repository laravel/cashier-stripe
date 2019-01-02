<?php

namespace Laravel\Cashier;

use Stripe\Stripe;
use Illuminate\Support\ServiceProvider;

class CashierServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'cashier');

        $this->publishes([
            __DIR__.'/../resources/views' => $this->app->basePath('resources/views/vendor/cashier'),
        ]);

        Stripe::setApiVersion(Cashier::$stripeVersion);
    }
}

<?php

namespace Laravel\Cashier;

use Illuminate\Support\Facades\Blade;
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

        $this->registerBladeExtensions();
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Register custom blade directives.
     *
     * @return void
     */
    public function registerBladeExtensions()
    {
        Blade::if('subscribed', function ($plan) {
            return auth()->user()->subscribed($plan);
        });
    }
}

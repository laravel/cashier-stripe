<?php

namespace Laravel\Cashier;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Gateway\BraintreeGateway;
use Laravel\Cashier\Gateway\StripeGateway;

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
            __DIR__.'/../config/cashier.php' => $this->app->basePath('config/cashier.php'),
        ]);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(Cashier::class, function(Container $app) {
            return Cashier::setInstance(new Cashier($app));
        });

        $this->app->singleton(StripeGateway::class, function(Container $app) {
            return new StripeGateway(
                $app->make(Cashier::class)
            );
        });

        $this->app->singleton(BraintreeGateway::class, function(Container $app) {
            return new BraintreeGateway(
                $app->make(Cashier::class)
            );
        });
    }
}

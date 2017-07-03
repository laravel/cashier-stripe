<?php

namespace Laravel\Cashier;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Arr;
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

        $viewPath = Arr::first(
            $this->app
                ->make('config')
                ->get('view.paths', [
                    $this->app->basePath('resources/views/'),
                ])
        );


        $this->publishes([
            __DIR__.'/../resources/views' => $viewPath.'vendor/cashier',
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
        $this->app->singleton(StripeGateway::class, function(Container $app) {
            return new StripeGateway();
        });

        $this->app->singleton(BraintreeGateway::class, function(Container $app) {
            return new BraintreeGateway();
        });

        $this->app->alias(Cashier::class, 'cashier');
        $this->app->alias(StripeGateway::class, 'cashier.stripe');
        $this->app->alias(BraintreeGateway::class, 'cashier.braintree');

        if (class_exists('Stripe\\Stripe')) {
            $this->app->make(StripeGateway::class)->register();
        }

        if (class_exists('Braintree\\Version')) {
            $this->app->make(BraintreeGateway::class)->register();
        }
    }
}

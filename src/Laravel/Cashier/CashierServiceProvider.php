<?php

namespace Laravel\Cashier;

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
        $this->loadViewsFrom(__DIR__.'/../../views', 'cashier');

        $this->publishes([
            __DIR__.'/../../views' => base_path('resources/views/vendor/cashier'),
        ]);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('Laravel\Cashier\BillableRepositoryInterface', function () {
            return new EloquentBillableRepository;
        });

        $this->app->singleton('command.cashier.table', function ($app) {
            return new CashierTableCommand;
        });

        $this->commands('command.cashier.table');
    }
}

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
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'cashier');

        if ($this->app->runningInConsole()) {
            $this->registerMigrations();

            $this->publishes([
                __DIR__.'/../database/migrations' => $this->app->databasePath('migrations'),
            ], 'cashier-migrations');

            $this->publishes([
                __DIR__.'/../resources/views' => $this->app->resourcePath('views/vendor/cashier'),
            ], 'cashier-views');
        }
    }

    /**
     * Register Cashier's migration files.
     *
     * @return void
     */
    protected function registerMigrations()
    {
        if (Cashier::$runsMigrations) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
    }
}

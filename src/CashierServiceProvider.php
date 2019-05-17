<?php

namespace Laravel\Cashier;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Routing\Registrar;

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

        $this->registerRoutes();

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

    /**
     * Register Cashier's routes.
     *
     * @return void
     */
    protected function registerRoutes()
    {
        $this->router()->group($this->routeConfiguration(), function (Registrar $router) {
            $router->post('webhook', 'WebhookController@handleWebhook')->name('webhook');
        });
    }

    /**
     * The Illuminate route registrar.
     *
     * @return \Illuminate\Contracts\Routing\Registrar
     */
    protected function router()
    {
        return $this->app->make(Registrar::class);
    }

    /**
     * Get the Cashier route group configuration array.
     *
     * @return array
     */
    protected function routeConfiguration()
    {
        return [
            'namespace' => 'Laravel\Cashier\Http\Controllers',
            'prefix' => 'stripe',
            'as' => 'cashier.',
        ];
    }
}

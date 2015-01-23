<?php namespace Laravel\Cashier;

use Illuminate\Support\ServiceProvider;

class CashierServiceProvider extends ServiceProvider {

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->loadViewsFrom('cashier', __DIR__.'/../../views');
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app->bindShared('Laravel\Cashier\BillableRepositoryInterface', function()
		{
			return new EloquentBillableRepository;
		});

		$this->app->bindShared('command.cashier.table', function($app)
		{
			return new CashierTableCommand;
		});

		$this->commands('command.cashier.table');
	}

}

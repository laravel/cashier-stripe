<?php namespace Laravel\Cashier;

use Illuminate\Support\Facades\Config;

class EloquentBillableRepository implements BillableRepositoryInterface {

	/**
	 * Find a BillableInterface implementation by Stripe ID.
	 *
	 * @param  string  $stripeId
	 * @return \Laravel\Cashier\BillableInterface
	 */
	public function find($stripeId)
	{
		$model = $this->createCashierModel(Config::get('services.stripe.model'));

		return $model->where($model->getStripeIdName(), $stripeId)->first();
	}

	/**
	 * Create a new instance of the Auth model.
	 *
	 * @param  string  $model
	 * @return \Laravel\Cashier\BillableInterface
	 */
	protected function createCashierModel($class)
	{
		$model = new $class;

		if ( ! $model instanceof BillableInterface)
		{
			throw new \InvalidArgumentException("Model does not implement BillableInterface.");
		}

		return $model;
	}

}
<?php namespace Laravel\Cashier;

use Illuminate\Support\Facades\Config;
use Laravel\Cashier\Contracts\Billable as BillableContract;

class EloquentBillableRepository implements BillableRepositoryInterface
{

    /**
     * Find a Billable implementation by Stripe ID.
     *
     * @param  string  $stripeId
     * @return \Laravel\Cashier\Contracts\Billable
     */
    public function find($stripeId)
    {
        $model = $this->createCashierModel(Config::get('services.stripe.model'));

        return $model->where($model->getStripeIdName(), $stripeId)->first();
    }

    /**
     * Create a new instance of the Auth model.
     *
     * @param  string  $class
     * @return \Laravel\Cashier\Contracts\Billable
     */
    protected function createCashierModel($class)
    {
        $model = new $class;

        if (! $model instanceof BillableContract) {
            throw new \InvalidArgumentException("Model does not implement Billable.");
        }

        return $model;
    }
}

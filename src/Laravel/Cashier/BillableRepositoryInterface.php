<?php namespace Laravel\Cashier;

interface BillableRepositoryInterface {

	/**
	 * Find a BillableInterface implementation by Stripe ID.
	 *
	 * @param  string  $stripeId
	 * @return \Laravel\Cashier\BillableInterface
	 */
	public function find($stripeId);

}
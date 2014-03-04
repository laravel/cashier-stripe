<?php namespace Laravel\Cashier;

interface PlanInterface {

	/**
	 * Get the Stripe ID for the plan.
	 *
	 * @return string
	 */
	public function getStripeId();

}
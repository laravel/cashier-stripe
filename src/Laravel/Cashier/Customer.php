<?php namespace Laravel\Cashier;

use Stripe_Customer;
use Stripe_Subscription;

class Customer extends Stripe_Customer {

	/**
	 * The subscription being managed by Cashier.
	 *
	 * @var \Stripe_Subscription
	 */
	public $subscription;

	/**
	 * Get the current subscription ID.
	 *
	 * @return string|null
	 */
	public function getStripeSubscription()
	{
		return $this->subscription ? $this->subscription->id : null;
	}

	/**
	 * Find a subscription by ID.
	 *
	 * @param  string  $id
	 * @return \Stripe_Subscription|null
	 */
	public function findSubscription($id)
	{
		foreach ($this->subscriptions as $subscription)
		{
			if ($subscription->id == $id) return $subscription;
		}
	}

	/**
	 * Create the current subscription with the given data.
	 *
	 * @param  array  $params
	 * @return void
	 */
	protected function createSubscription(array $params)
	{
		return $this->subscription = $this->subscriptions->create($params);
	}

	/**
	 * Update the current subscription with the given data.
	 *
	 * @param  array  $params
	 * @return void
	 */
	public function updateSubscription($params = null)
	{
		if (is_null($this->subscription))
		{
			return $this->createSubscription($params);
		}

		foreach ($params as $key => $value)
		{
			$this->subscription->{$key} = $value;
		}

		$this->subscription->save();
	}

	/**
	 * Cancel the current subscription.
	 *
	 * @param  array  $params
	 * @return void
	 */
	public function cancelSubscription($params = null)
	{
		return $this->subscription->cancel($params);
	}

}
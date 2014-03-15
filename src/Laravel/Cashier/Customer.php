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
	public function getSubscriptionId()
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
	 * @param  array  $data
	 * @return void
	 */
	protected function createSubscription(array $data)
	{
		return $this->subscription = $this->subscriptions->create($data);
	}

	/**
	 * Update the current subscription with the given data.
	 *
	 * @param  array  $data
	 * @return void
	 */
	public function updateSubscription(array $data)
	{
		if (is_null($this->subscription))
		{
			return $this->createSubscription($data);
		}

		foreach ($data as $key => $value)
		{
			$this->subscription->{$key} = $value;
		}

		$this->subscription->save();
	}

	/**
	 * Cancel the current subscription.
	 *
	 * @param  array  $data
	 * @return void
	 */
	public function cancelSubscription(array $data)
	{
		return $this->subscription->cancel($data);
	}

}
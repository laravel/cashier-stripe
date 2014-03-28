<?php namespace Laravel\Cashier;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Request;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends Controller {

	/**
	 * Handle a Stripe webhook call.
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function handleWebhook()
	{
		$payload = $this->getJsonPayload();

		switch ($payload['type'])
		{
			case 'invoice.payment_failed':
				return $this->handleFailedPayment($payload);
		}
	}

	/**
	 * Handle a failed payment from a Stripe subscription.
	 *
	 * @param  array  $payload
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	protected function handleFailedPayment(array $payload)
	{
		if ($this->tooManyFailedPayments($payload))
		{
			$billable = $this->getBillable($payload['data']['object']['customer']);

			if ($billable) $billable->subscription()->cancel();
		}

		return new Response('Webhook Handled', 200);
	}

	/**
	 * Determine if the invoiec has too many failed attempts.
	 *
	 * @param  array  $payload
	 * @return bool
	 */
	protected function tooManyFailedPayments(array $payload)
	{
		return $payload['data']['object']['attempt_count'] > 3;
	}

	/**
	 * Get the billable entity instance by Stripe ID.
	 *
	 * @param  string  $stripeId
	 * @return \Laravel\Cashier\BillableInterface
	 */
	protected function getBillable($stripeId)
	{
		return App::make('Laravel\Cashier\BillableRepositoryInterface')->find($stripeId);
	}

	/**
	 * Get the JSON payload for the request.
	 *
	 * @return array
	 */
	protected function getJsonPayload()
	{
		return (array) json_decode(Request::getContent(), true);
	}

}

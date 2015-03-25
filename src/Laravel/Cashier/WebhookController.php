<?php namespace Laravel\Cashier;

use Config;
use Exception;
use Stripe_Event;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class WebhookController extends Controller
{

    /**
     * Handle a Stripe webhook call.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handleWebhook()
    {
        $payload = $this->getJsonPayload();

        if (! $this->eventExistsOnStripe($payload['id'])) {
            return;
        }

        $method = 'handle'.studly_case(str_replace('.', '_', $payload['type']));

        if (method_exists($this, $method)) {
            return $this->{$method}($payload);
        } else {
            return $this->missingMethod();
        }
    }

    /**
     * Verify with Stripe that the event is genuine.
     *
     * @param  string  $id
     * @return bool
     */
    protected function eventExistsOnStripe($id)
    {
        try {
            return ! is_null(Stripe_Event::retrieve($id, Config::get('services.stripe.secret')));
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Handle a cancelled customer from a Stripe subscription.
     *
     * @param  array  $payload
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handleCustomerSubscriptionDeleted(array $payload)
    {
        $billable = $this->getBillable($payload['data']['object']['customer']);

        if (($billable) && ($billable->subscribed())) {
            $billable->subscription()->cancel();
        }

        return new Response('Webhook Handled', 200);
    }

    /**
     * Determine if the invoice has too many failed attempts.
     *
     * @deprecated Use Stripe webhook 'customer.subscription.deleted' instead.
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
     * @return \Laravel\Cashier\Contracts\Billable
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

    /**
     * Handle calls to missing methods on the controller.
     *
     * @param  array   $parameters
     * @return mixed
     */
    public function missingMethod($parameters = array())
    {
        return new Response;
    }
}

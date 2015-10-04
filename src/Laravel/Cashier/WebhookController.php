<?php

namespace Laravel\Cashier;

use Config;
use Exception;
use Stripe\Event as StripeEvent;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Request;
use Symfony\Component\HttpFoundation\Response;

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

        if (! $this->eventExistsOnStripe($payload['id']) && ! $this->isInTestingEnvironment()) {
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
            return ! is_null(StripeEvent::retrieve($id, Config::get('services.stripe.secret')));
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

        if ($billable && $billable->subscribed()) {
            $billable->subscription()->cancel();
        }

        return new Response('Webhook Handled', 200);
    }

    /**
     * Handle a failed payment.
     *
     * @param  array  $payload
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handleInvoicePaymentFailed(array $payload)
    {
        $billable = $this->getBillable($payload['data']['object']['customer']);

        if ($this->userIsSubscribedWithoutACard($billable)) {
            $billable->subscription->cancel(false);
        }

        return new Response('Webhook Handled', 200);
    }

    /**
     * Determine if the user is in a "subscribed" state but has no card on file.
     *
     * @param  \Laravel\Cashier\Contracts\Billable  $billable
     * @return bool
     */
    protected function userIsSubscribedWithoutACard($billable)
    {
        return ($billable && $billable->subscribed() &&
            ! $billable->onTrial() && is_null($billable->getLastFourCardDigits()));
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
     * Verify if cashier is in the testing environment.
     *
     * @return bool
     */
    protected function isInTestingEnvironment()
    {
        return getenv('CASHIER_ENV') === 'testing';
    }

    /**
     * Handle calls to missing methods on the controller.
     *
     * @param  array   $parameters
     * @return mixed
     */
    public function missingMethod($parameters = [])
    {
        return new Response;
    }
}

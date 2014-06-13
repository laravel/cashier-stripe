<?php namespace Laravel\Cashier;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Request;
use Symfony\Component\HttpFoundation\Response;
use Event;

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
            case StripeWebhookEvents::INVOICE_PAYMENT_FAILED:
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
     * Determine if the invoice has too many failed attempts.
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

    /**
     *  Will Fire a Event for each stripe webhook event sent to your site.
     *  The reason I used a large case block was so I could fire other events for my
     *  site as Ive integrated my site with Xero Api as well and I needed the two
     *  packages to be able to handel there own event so I did not want to tie my
     *  Xero package to fire a API request because of a Stripe Event.
     *  So if you only need to handle Stripe events you can replace this code with
     *
     *  Event::fire("cashier.stripe".$payload['type'], $payload);
     *
     * @param $payload
     */
    protected function emmitSystemEvent($payload)
    {
        switch($payload['type'])
        {
            case StripeWebhookEvents::CHARGE_SUCCEEDED:
                Event::fire("cashier.stripe".StripeWebhookEvents::CHARGE_SUCCEEDED, $payload);
                break;

            case StripeWebhookEvents::CHARGE_FAILED:
                Event::fire("cashier.stripe".StripeWebhookEvents::CHARGE_FAILED, $payload);
                break;

            case StripeWebhookEvents::CHARGE_REFUNDED:
                Event::fire("cashier.stripe".StripeWebhookEvents::CHARGE_REFUNDED, $payload);
                break;

            case StripeWebhookEvents::CHARGE_CAPTURED:
                Event::fire("cashier.stripe".StripeWebhookEvents::CHARGE_CAPTURED, $payload);
                break;

            case StripeWebhookEvents::CHARGE_UPDATED:
                Event::fire("cashier.stripe".StripeWebhookEvents::CHARGE_UPDATED, $payload);
                break;

            case StripeWebhookEvents::CHARGE_DISPUTE_CREATED:
                Event::fire("cashier.stripe".StripeWebhookEvents::CHARGE_DISPUTE_CREATED, $payload);
                break;

            case StripeWebhookEvents::CHARGE_DISPUTE_UPDATED:
                Event::fire("cashier.stripe".StripeWebhookEvents::CHARGE_DISPUTE_UPDATED, $payload);
                break;

            case StripeWebhookEvents::CHARGE_DISPUTE_CLOSED:
                Event::fire("cashier.stripe".StripeWebhookEvents::CHARGE_DISPUTE_CLOSED, $payload);
                break;

            case StripeWebhookEvents::CUSTOMER_CREATED:
                Event::fire("cashier.stripe".StripeWebhookEvents::CUSTOMER_CREATED, $payload);
                break;

            case StripeWebhookEvents::CUSTOMER_UPDATED:
                Event::fire("cashier.stripe".StripeWebhookEvents::CUSTOMER_UPDATED, $payload);
                break;

            case StripeWebhookEvents::CUSTOMER_DELETED:
                Event::fire("cashier.stripe".StripeWebhookEvents::CUSTOMER_DELETED, $payload);
                break;

            case StripeWebhookEvents::CUSTOMER_CARD_CREATED:
                Event::fire("cashier.stripe".StripeWebhookEvents::CUSTOMER_CARD_CREATED, $payload);
                break;

            case StripeWebhookEvents::CUSTOMER_CARD_UPDATED:
                Event::fire("cashier.stripe".StripeWebhookEvents::CUSTOMER_CARD_UPDATED, $payload);
                break;

            case StripeWebhookEvents::CUSTOMER_CARD_DELETED:
                Event::fire("cashier.stripe".StripeWebhookEvents::CUSTOMER_CARD_DELETED, $payload);
                break;

            case StripeWebhookEvents::CUSTOMER_SUBSCRIPTION_CREATED:
                Event::fire("cashier.stripe".StripeWebhookEvents::CUSTOMER_SUBSCRIPTION_CREATED, $payload);
                break;

            case StripeWebhookEvents::CUSTOMER_SUBSCRIPTION_UPDATED:
                Event::fire("cashier.stripe".StripeWebhookEvents::CUSTOMER_SUBSCRIPTION_UPDATED, $payload);
                break;

            case StripeWebhookEvents::CUSTOMER_SUBSCRIPTION_DELETED:
                Event::fire("cashier.stripe".StripeWebhookEvents::CUSTOMER_SUBSCRIPTION_DELETED, $payload);
                break;

            case StripeWebhookEvents::CUSTOMER_SUBSCRIPTION_TRIAL_WILL_END:
                Event::fire("cashier.stripe".StripeWebhookEvents::CUSTOMER_SUBSCRIPTION_TRIAL_WILL_END, $payload);
                break;

            case StripeWebhookEvents::CUSTOMER_DISCOUNT_CREATED:
                Event::fire("cashier.stripe".StripeWebhookEvents::CUSTOMER_DISCOUNT_CREATED, $payload);
                break;

            case StripeWebhookEvents::CUSTOMER_DISCOUNT_UPDATED:
                Event::fire("cashier.stripe".StripeWebhookEvents::CUSTOMER_DISCOUNT_UPDATED, $payload);
                break;

            case StripeWebhookEvents::CUSTOMER_DISCOUNT_DELETED:
                Event::fire("cashier.stripe".StripeWebhookEvents::CUSTOMER_DISCOUNT_DELETED, $payload);
                break;

            case StripeWebhookEvents::INVOICE_CREATED:
                Event::fire("cashier.stripe".StripeWebhookEvents::INVOICE_CREATED, $payload);
                break;

            case StripeWebhookEvents::INVOICE_UPDATED:
                Event::fire("cashier.stripe".StripeWebhookEvents::INVOICE_UPDATED, $payload);
                break;

            case StripeWebhookEvents::INVOICE_PAYMENT_SUCCEEDED:
                Event::fire("cashier.stripe".StripeWebhookEvents::INVOICE_PAYMENT_SUCCEEDED, $payload);
                break;

            case StripeWebhookEvents::INVOICE_PAYMENT_FAILED:
                Event::fire("cashier.stripe".StripeWebhookEvents::INVOICE_PAYMENT_FAILED, $payload);
                break;

            case StripeWebhookEvents::INVOICEITEM_CREATED:
                Event::fire("cashier.stripe".StripeWebhookEvents::INVOICEITEM_CREATED, $payload);
                break;

            case StripeWebhookEvents::INVOICEITEM_UPDATED:
                Event::fire("cashier.stripe".StripeWebhookEvents::INVOICEITEM_UPDATED, $payload);
                break;

            case StripeWebhookEvents::INVOICEITEM_DELETED:
                Event::fire("cashier.stripe".StripeWebhookEvents::INVOICEITEM_DELETED, $payload);
                break;

            case StripeWebhookEvents::PLAN_CREATED:
                Event::fire("cashier.stripe".StripeWebhookEvents::PLAN_CREATED, $payload);
                break;

            case StripeWebhookEvents::PLAN_UPDATED:
                Event::fire("cashier.stripe".StripeWebhookEvents::PLAN_UPDATED, $payload);
                break;

            case StripeWebhookEvents::PLAN_DELETED:
                Event::fire("cashier.stripe".StripeWebhookEvents::PLAN_DELETED, $payload);
                break;

            case StripeWebhookEvents::COUPON_CREATED:
                Event::fire("cashier.stripe".StripeWebhookEvents::COUPON_CREATED, $payload);
                break;

            case StripeWebhookEvents::COUPON_DELETED:
                Event::fire("cashier.stripe".StripeWebhookEvents::COUPON_DELETED, $payload);
                break;

            case StripeWebhookEvents::TRANSFER_CREATED:
                Event::fire("cashier.stripe".StripeWebhookEvents::TRANSFER_CREATED, $payload);
                break;

            case StripeWebhookEvents::TRANSFER_UPDATED:
                Event::fire("cashier.stripe".StripeWebhookEvents::TRANSFER_UPDATED, $payload);
                break;

            case StripeWebhookEvents::TRANSFER_PAID:
                Event::fire("cashier.stripe".StripeWebhookEvents::TRANSFER_PAID, $payload);
                break;

            case StripeWebhookEvents::TRANSFER_FAILED:
                Event::fire("cashier.stripe".StripeWebhookEvents::TRANSFER_FAILED, $payload);
                break;
        }
    }
}

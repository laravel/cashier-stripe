<?php

namespace Laravel\Cashier\Gateway\Braintree;

use Braintree\Customer as BraintreeCustomer;
use Braintree\PaymentMethod;
use Braintree\PayPalAccount;
use Braintree\Plan as BraintreePlan;
use Braintree\Subscription as BraintreeSubscription;
use Braintree\Transaction as BraintreeTransaction;
use Braintree\TransactionSearch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Laravel\Cashier\Billable;
use Laravel\Cashier\Gateway\Carbon;
use Laravel\Cashier\Gateway\Gateway as BaseGateway;
use Laravel\Cashier\Subscription;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Gateway extends BaseGateway
{
    /**
     * Get the Braintree plan that has the given ID.
     *
     * @param  string  $id
     * @return \Braintree\Plan
     * @throws \Laravel\Cashier\Gateway\Braintree\Exception
     */
    public static function findPlan($id)
    {
        $plans = BraintreePlan::all();

        foreach ($plans as $plan) {
            if ($plan->id === $id) {
                return $plan;
            }
        }

        throw new Exception("Unable to find Braintree plan with ID [{$id}].");
    }

    /**
     * Get the name of this gateway.
     *
     * @return string
     */
    public function getName()
    {
        return 'braintree';
    }

    /**
     * Convert value as cents into dollars.
     *
     * @param  int  $value
     * @return float
     */
    public function convertZeroDecimalValue($value)
    {
        return (float) $value / 100;
    }

    public function manageSubscription(Subscription $subscription)
    {
        return new SubscriptionManager($subscription);
    }

    public function buildSubscription(Model $billable, $subscription, $plan)
    {
        return new SubscriptionBuilder($billable, $subscription, $plan);
    }

    /**
     * Get the Braintree customer for the model.
     *
     * @return \Braintree\Customer
     */
    public function asCustomer(Billable $billable)
    {
        return BraintreeCustomer::find($billable->getPaymentGatewayIdAttribute());
    }

    /**
     * Create a Braintree customer for the given model.
     *
     * @param  string  $token
     * @param  array  $options
     * @return \Braintree\Customer
     * @throws \Exception
     */
    public function createAsCustomer(Billable $billable, $token, array $options = [])
    {
        $response = BraintreeCustomer::create(
            array_replace_recursive([
                'firstName' => Arr::get(explode(' ', $billable->name), 0),
                'lastName' => Arr::get(explode(' ', $billable->name), 1),
                'email' => $billable->email,
                'paymentMethodNonce' => $token,
                'creditCard' => [
                    'options' => [
                        'verifyCard' => true,
                    ],
                ],
            ], $options)
        );

        if (! $response->success) {
            throw new Exception('Unable to create Braintree customer: '.$response->message);
        }

        $paymentMethod = $response->customer->paymentMethods[0];

        $paypalAccount = $paymentMethod instanceof PayPalAccount;

        $billable->forceFill([
            'braintree_id' => $response->customer->id, // FIXME
            'paypal_email' => $paypalAccount ? $paymentMethod->email : null, // FIXME
            'card_brand' => ! $paypalAccount ? $paymentMethod->cardType : null,
            'card_last_four' => ! $paypalAccount ? $paymentMethod->last4 : null,
        ])->save();

        return $response->customer;
    }

    /**
     * Update customer's credit card.
     *
     * @param  string  $token
     * @param  array  $options
     * @return void
     * @throws \Exception
     */
    public function updateCard(Billable $billable, $token, array $options = [])
    {
        $customer = $this->asCustomer($billable);

        $response = PaymentMethod::create(
            array_replace_recursive([
                'customerId' => $customer->id,
                'paymentMethodNonce' => $token,
                'options' => [
                    'makeDefault' => true,
                    'verifyCard' => true,
                ],
            ], $options)
        );

        if (! $response->success) {
            throw new Exception('Braintree was unable to create a payment method: '.$response->message);
        }

        $paypalAccount = $response->paymentMethod instanceof PaypalAccount;

        $billable->forceFill([
            'paypal_email' => $paypalAccount ? $response->paymentMethod->email : null, // FIXME
            'card_brand' => $paypalAccount ? null : $response->paymentMethod->cardType,
            'card_last_four' => $paypalAccount ? null : $response->paymentMethod->last4,
        ])->save();

        $this->updateSubscriptionsToPaymentMethod($billable, $response->paymentMethod->token);
    }

    /**
     * Update the payment method token for all of the model's subscriptions.
     *
     * @param  string  $token
     * @return void
     */
    protected function updateSubscriptionsToPaymentMethod(Billable $billable, $token)
    {
        foreach ($billable->subscriptions as $subscription) {
            if ($subscription->active()) {
                BraintreeSubscription::update($subscription->getPaymentGatewayIdAttribute(), [
                    'paymentMethodToken' => $token,
                ]);
            }
        }
    }

    /**
     * Apply a coupon to the billable entity.
     *
     * @param  string  $coupon
     * @param  string  $subscription
     * @param  bool  $removeOthers
     * @return void
     * @throws \InvalidArgumentException
     */
    public function applyCoupon(Billable $billable, $coupon, $subscription = 'default', $removeOthers = false)
    {
        // FIXME: The signature is different between Stripe and Braintree
        
        $subscription = $billable->subscription($subscription);

        if (! $subscription) {
            throw new InvalidArgumentException("Unable to apply coupon. Subscription does not exist.");
        }

        $subscription->applyCoupon($coupon, $removeOthers); // TODO: Can this be done at Stripe, too?
    }

    /**
     * Make a "one off" charge on the customer for the given amount.
     *
     * @param  int  $amount
     * @param  array  $options
     * @return array
     * @throws \Exception
     */
    public function charge(Billable $billable, $amount, array $options = [])
    {
        $customer = $this->asCustomer($billable);

        $response = BraintreeTransaction::sale(array_merge([
            'amount' => (string) round($amount * (1 + ($billable->taxPercentage() / 100)), 2),
            'paymentMethodToken' => $customer->paymentMethods[0]->token,
            'options' => [
                'submitForSettlement' => true,
            ],
            'recurring' => true,
        ], $options));

        if (! $response->success) {
            throw new Exception('Braintree was unable to perform a charge: '.$response->message);
        }

        return $response;
    }

    public function refund(Billable $billable, $charge, array $options = [])
    {
        // FIXME
    }

    /**
     * Invoice the customer for the given amount.
     *
     * @param  string  $description
     * @param  int  $amount
     * @param  array  $options
     * @return array
     *
     * @throws \Exception
     */
    public function tab(Billable $billable, $description, $amount, array $options = [])
    {
        // FIXME: Issues with cross compat
        return $this->charge($billable, $amount, array_merge($options, [
            'customFields' => [
                'description' => $description,
            ],
        ]));
    }

    /**
     * Invoice the customer for the given amount (alias).
     *
     * @param  string  $description
     * @param  int  $amount
     * @param  array  $options
     * @return array
     *
     * @throws \Exception
     */
    public function invoiceFor(Billable $billable, $description, $amount, array $options = [])
    {
        return $this->tab($billable, $description, $amount, $options);
    }

    /**
     * Get a collection of the entity's invoices.
     *
     * @param  bool  $includePending
     * @param  array  $parameters
     * @return \Illuminate\Support\Collection
     */
    public function invoices(Billable $billable, $includePending = false, $parameters = [])
    {
        $invoices = [];

        $customer = $this->asCustomer($billable);

        $parameters = array_merge([
            'id' => TransactionSearch::customerId()->is($customer->id),
            'range' => TransactionSearch::createdAt()->between(
                Carbon::today()->subYears(2)->format('m/d/Y H:i'),
                Carbon::tomorrow()->format('m/d/Y H:i')
            ),
        ], $parameters);

        $transactions = BraintreeTransaction::search($parameters);

        // Here we will loop through the Braintree invoices and create our own custom Invoice
        // instance that gets more helper methods and is generally more convenient to work
        // work than the plain Braintree objects are. Then, we'll return the full array.
        if (null !== $transactions) {
            foreach ($transactions as $transaction) {
                if ($transaction->status == BraintreeTransaction::SETTLED || $includePending) {
                    $invoices[] = new Invoice($billable, $transaction); // FIXME
                }
            }
        }

        return new Collection($invoices);
    }

    /**
     * Find an invoice by ID.
     *
     * @param  string  $id
     * @return \Laravel\Cashier\Invoice|null
     */
    public function findInvoice(Billable $billable, $id)
    {
        try {
            $invoice = BraintreeTransaction::find($id);

            if ($invoice->customerDetails->id != $billable->getPaymentGatewayIdAttribute()) {
                return;
            }

            return new Invoice($billable, $invoice);
        } catch (Exception $e) {
            //
        }
    }

    /**
     * Find an invoice or throw a 404 error.
     *
     * @param  string  $id
     * @return \Laravel\Cashier\Invoice
     */
    public function findInvoiceOrFail(Billable $billable, $id)
    {
        $invoice = $this->findInvoice($billable, $id);

        if (null === $invoice) {
            throw new NotFoundHttpException;
        }

        return $invoice;
    }

    /**
     * Create an invoice download Response.
     *
     * @param  string  $id
     * @param  array  $data
     * @param  string  $storagePath
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function downloadInvoice(Billable $billable, $id, array $data, $storagePath = null)
    {
        return $this->findInvoiceOrFail($billable, $id)->download($data, $storagePath);
    }
}

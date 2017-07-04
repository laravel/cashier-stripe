<?php

namespace Laravel\Cashier\Gateway\Braintree;

use Braintree\Customer;
use Braintree\PaymentMethod;
use Braintree\PayPalAccount;
use Braintree\Subscription;
use Braintree\Transaction;
use Braintree\TransactionSearch;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Laravel\Cashier\Gateway\BillingManager as BaseManager;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class BillingManager
 *
 * @package Laravel\Cashier\Gateway\Stripe
 */
class BillingManager extends BaseManager
{
    /**
     * Get the Braintree customer for the model.
     *
     * @return \Braintree\Customer
     *
     * @throws \Laravel\Cashier\Exception
     */
    public function asGatewayCustomer()
    {
        $this->checkGateway();

        return Customer::find($this->billable->payment_gateway_id);
    }

    /**
     * Create a Braintree customer for the given model.
     *
     * @param  string  $token
     * @param  array  $options
     * @return \Braintree\Customer
     *
     * @throws \Laravel\Cashier\Gateway\Braintree\Exception
     * @throws \Laravel\Cashier\Exception
     */
    public function createAsGatewayCustomer($token, array $options = [])
    {
        $this->checkGateway();

        $response = Customer::create(
            array_replace_recursive([
                'firstName' => Arr::get(explode(' ', $this->billable->name), 0),
                'lastName' => Arr::get(explode(' ', $this->billable->name), 1),
                'email' => $this->billable->email,
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

        $this->billable->setPaymentGatewayId($response->customer->id, 'braintree');
        $this->fillPaymentDetails($paymentMethod);

        $this->billable->save();

        return $response->customer;
    }

    /**
     * Update customer's credit card.
     *
     * @param  string  $token
     * @param  array  $options
     * @return void
     *
     * @throws \Laravel\Cashier\Exception
     * @throws \Laravel\Cashier\Gateway\Braintree\Exception
     */
    public function updateCard($token, array $options = [])
    {
        $this->checkGateway();

        $customer = $this->asGatewayCustomer();

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

        $this->fillPaymentDetails($response->paymentMethod);
        $this->billable->save();

        $this->updateSubscriptionsToPaymentMethod($response->paymentMethod->token);
    }

    /**
     * Apply a coupon to the billable entity.
     *
     * @param  string  $coupon
     * @param  string  $subscription
     * @param  bool  $removeOthers
     * @return void
     *
     * @throws \InvalidArgumentException
     * @throws \Laravel\Cashier\Exception
     */
    public function applyCoupon($coupon, $subscription = 'default', $removeOthers = false)
    {
        // FIXME: The signature is different between Stripe and Braintree

        $this->checkGateway();

        $subscription = $this->billable->subscription($subscription);

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
     *
     * @throws \Laravel\Cashier\Exception
     * @throws \Laravel\Cashier\Gateway\Braintree\Exception
     */
    public function charge($amount, array $options = [])
    {
        $this->checkGateway();

        $customer = $this->asGatewayCustomer();

        $response = BraintreeTransaction::sale(array_merge([
            'amount' => (string) round($amount * (1 + ($this->billable->taxPercentage() / 100)), 2),
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

    public function refund($charge, array $options = [])
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
     * @throws \Laravel\Cashier\Exception
     */
    public function tab($description, $amount, array $options = [])
    {
        $this->checkGateway();

        // FIXME: Issues with cross compat
        return $this->charge($amount, array_merge($options, [
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
     * @throws \Laravel\Cashier\Exception
     */
    public function invoiceFor($description, $amount, array $options = [])
    {
        $this->checkGateway();

        return $this->tab($description, $amount, $options);
    }

    /**
     * Get a collection of the entity's invoices.
     *
     * @param  bool  $includePending
     * @param  array  $parameters
     * @return \Illuminate\Support\Collection
     *
     * @throws \Laravel\Cashier\Exception
     */
    public function invoices($includePending = false, $parameters = [])
    {
        $this->checkGateway();

        $invoices = [];

        $customer = $this->asGatewayCustomer();

        $parameters = array_merge([
            'id' => TransactionSearch::customerId()->is($customer->id),
            'range' => TransactionSearch::createdAt()->between(
                Carbon::today()->subYears(2)->format('m/d/Y H:i'),
                Carbon::tomorrow()->format('m/d/Y H:i')
            ),
        ], $parameters);

        // Here we will loop through the Braintree invoices and create our own custom Invoice
        // instance that gets more helper methods and is generally more convenient to work
        // work than the plain Braintree objects are. Then, we'll return the full array.
        if ($transactions = Transaction::search($parameters)) {
            foreach ($transactions as $transaction) {
                if ($transaction->status == Transaction::SETTLED || $includePending) {
                    $invoices[] = new Invoice($this->billable, $transaction); // FIXME
                }
            }
        }

        return new Collection($invoices);
    }

    /**
     * Find an invoice by ID.
     *
     * @param  string  $id
     * @return \Laravel\Cashier\Gateway\Invoice|null
     *
     * @throws \Laravel\Cashier\Exception
     */
    public function findInvoice($id)
    {
        $this->checkGateway();

        try {
            $invoice = Transaction::find($id);

            if ($invoice->customerDetails->id != $this->billable->payment_gateway_id) {
                return null;
            }

            return new Invoice($this->billable, $invoice);
        } catch (Exception $e) {
            //
        }
    }

    /**
     * Find an invoice or throw a 404 error.
     *
     * @param  string $id
     * @return \Laravel\Cashier\Gateway\Invoice
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     * @throws \Laravel\Cashier\Exception
     */
    public function findInvoiceOrFail($id)
    {
        $this->checkGateway();

        $invoice = $this->findInvoice($id);

        if (null === $invoice) {
            throw new NotFoundHttpException();
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
     *
     * @throws \Laravel\Cashier\Exception
     */
    public function downloadInvoice($id, array $data, $storagePath = null)
    {
        $this->checkGateway();

        return $this->findInvoiceOrFail($id)->download($data, $storagePath);
    }

    /**
     * Fills the model's properties with the source from Braintree.
     *
     * @param \Braintree\PaymentMethod|null $method
     * @return $this
     */
    protected function fillPaymentDetails(PaymentMethod $method = null)
    {
        if ($method) {
            $paypalAccount = $method instanceof PayPalAccount;

            $this->billable->forceFill([
                'paypal_email' => $paypalAccount ? $method->email : null,
                'card_brand' => ! $paypalAccount ? $method->cardType : null,
                'card_last_four' => ! $paypalAccount ? $method->last4 : null,
            ]);
        }

        return $this;
    }

    /**
     * Update the payment method token for all of the model's subscriptions.
     *
     * @param  string  $token
     * @return void
     */
    protected function updateSubscriptionsToPaymentMethod($token)
    {
        foreach ($this->billable->subscriptions as $subscription) {
            if ($subscription->active()) {
                Subscription::update($subscription->payment_gateway_id, [
                    'paymentMethodToken' => $token,
                ]);
            }
        }
    }
}

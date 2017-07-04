<?php

namespace Laravel\Cashier\Gateway\Stripe;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use Laravel\Cashier\Gateway\BillingManager as BaseManager;
use Laravel\Cashier\Gateway\Stripe\Invoice;
use Stripe\Charge;
use Stripe\Customer;
use Stripe\Error\InvalidRequest;
use Stripe\Invoice as StripeInvoice;
use Stripe\InvoiceItem;
use Stripe\Refund;
use Stripe\Token;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class BillingManager
 *
 * @package Laravel\Cashier\Gateway\Stripe
 */
class BillingManager extends BaseManager
{
    /**
     * @return \Stripe\Customer
     *
     * @throws \Laravel\Cashier\Exception
     */
    public function asGatewayCustomer()
    {
        $this->checkGateway();

        return Customer::retrieve(
            $this->billable->payment_gateway_id,
            Gateway::getApiKey()
        );
    }

    /**
     * Create a Stripe customer for the given Stripe model.
     *
     * @param  string $token
     * @param  array $options
     * @return \Stripe\Customer
     *
     * @throws \Laravel\Cashier\Exception
     */
    public function createAsGatewayCustomer($token, array $options = [])
    {
        $this->checkGateway();

        $options = array_key_exists('email', $options)
            ? $options
            : array_merge($options, ['email' => $this->billable->email]);

        // Here we will create the customer instance on Stripe and store the ID of the
        // user from Stripe. This ID will correspond with the Stripe user instances
        // and allow us to retrieve users from Stripe later when we need to work.
        $customer = Customer::create($options, Gateway::getApiKey());

        $this->billable->setPaymentGatewayId($customer->id, $this->gateway->getName());

        $this->billable->save();

        // Next we will add the credit card to the user's account on Stripe using this
        // token that was provided to this method. This will allow us to bill users
        // when they subscribe to plans or we need to do one-off charges on them.
        if (null !== $token) {
            $this->updateCard($token);
        }

        return $customer;
    }

    /**
     * Apply a coupon to the billable entity.
     *
     * @param  string $coupon
     * @return void
     *
     * @throws \Laravel\Cashier\Exception
     */
    public function applyCoupon($coupon, $subscription = 'default', $removeOthers = false)
    {
        // FIXME $subscription = 'default', $removeOthers = false

        $this->checkGateway();

        $customer = $this->asGatewayCustomer();

        $customer->coupon = $coupon;

        $customer->save();
    }

    /**
     * Update customer's credit card.
     *
     * @param  string $token
     * @param  array  $options
     * @return void
     *
     * @throws \Laravel\Cashier\Exception
     */
    public function updateCard($token, array $options = [])
    {
        $this->checkGateway();

        $customer = $this->asGatewayCustomer();

        $token = Token::retrieve($token, ['api_key' => Gateway::getApiKey()]);

        // If the given token already has the card as their default source, we can just
        // bail out of the method now. We don't need to keep adding the same card to
        // a model's account every time we go through this particular method call.
        if ($token->card->id === $customer->default_source) {
            return;
        }

        $card = $customer->sources->create(['source' => $token]);

        $customer->default_source = $card->id;

        $customer->save();

        // Next we will get the default source for this model so we can update the last
        // four digits and the card brand on the record in the database. This allows
        // us to display the information on the front-end when updating the cards.
        $source = $customer->default_source
            ? $customer->sources->retrieve($customer->default_source)
            : null;

        $this->fillCardDetails($source);

        $this->billable->save();
    }

    /**
     * Synchronises the customer's card from Stripe back into the database.
     *
     * @return $this
     *
     * @throws \Laravel\Cashier\Exception
     */
    public function updateCardFromStripe()
    {
        $this->checkGateway();

        $customer = $this->asGatewayCustomer();

        $defaultCard = null;

        foreach ($customer->sources->data as $card) {
            if ($card->id === $customer->default_source) {
                $defaultCard = $card;
                break;
            }
        }

        if ($defaultCard) {
            $this->fillCardDetails($defaultCard)->save();
        } else {
            $this->forceFill([
                'card_brand' => null,
                'card_last_four' => null,
            ])->save();
        }

        return $this->billable;
    }

    /**
     * Make a "one off" charge on the customer for the given amount.
     *
     * @param  int  $amount
     * @param  array  $options
     * @return \Stripe\Charge
     *
     * @throws \InvalidArgumentException
     * @throws \Laravel\Cashier\Exception
     */
    public function charge($amount, array $options = [])
    {
        $this->checkGateway();

        $options = array_merge([
            'currency' => $this->billable->preferredCurrency(),
        ], $options);

        $options['amount'] = $this->gateway->convertZeroDecimalValue($amount); // FIXME

        if (! array_key_exists('source', $options) && $this->billable->payment_gateway_id) {
            $options['customer'] = $this->billable->payment_gateway_id;
        }

        if (! array_key_exists('source', $options) && ! array_key_exists('customer', $options)) {
            throw new InvalidArgumentException('No payment source provided.');
        }

        return Charge::create($options, ['api_key' => Gateway::getApiKey()]);
    }

    /**
     * Refund a customer for a charge.
     *
     * @param  string $charge
     * @param  array $options
     * @return \Stripe\Refund
     *
     * @throws \Laravel\Cashier\Exception
     */
    public function refund($charge, array $options = [])
    {
        $this->checkGateway();

        $options['charge'] = $charge;

        return Refund::create($options, ['api_key' => Gateway::getApiKey()]);
    }

    /**
     * Add an invoice item to the customer's upcoming invoice.
     *
     * @param  string $description
     * @param  int $amount
     * @param  array $options
     * @return \Stripe\InvoiceItem
     *
     * @throws \InvalidArgumentException
     * @throws \Laravel\Cashier\Exception
     */
    public function tab($description, $amount, array $options = [])
    {
        $this->checkGateway();

        if (! $this->billable->payment_gateway_id) {
            throw new InvalidArgumentException(class_basename($this).' is not a Stripe customer. See Stripe\BillingManager::createAsGatewayCustomer().');
        }

        $options = array_merge([
            'customer' => $this->billable->payment_gateway_id,
            'amount' => $this->gateway->convertZeroDecimalValue($amount),
            'currency' => $this->billable->preferredCurrency(),
            'description' => $description,
        ], $options);

        return InvoiceItem::create($options, ['api_key' => Gateway::getApiKey()]); // FIXME: InvoiceItem
    }

    /**
     * Invoice the billable entity outside of regular billing cycle.
     *
     * @return bool|\Stripe\Invoice
     *
     * @throws \Laravel\Cashier\Exception
     */
    public function invoice()
    {
        $this->checkGateway();

        if ($this->billable->payment_gateway_id) {
            try {
                return StripeInvoice::create(['customer' => $this->billable->payment_gateway_id], Gateway::getApiKey())->pay();
            } catch (InvalidRequest $e) {
                return false;
            }
        }

        return true;
    }

    /**
     * Invoice the customer for the given amount and generate an invoice immediately.
     *
     * @param  string  $description
     * @param  int  $amount
     * @param  array  $options
     * @return bool|\Laravel\Cashier\Gateway\Invoice
     *
     * @throws \InvalidArgumentException
     * @throws \Laravel\Cashier\Exception
     */
    public function invoiceFor($description, $amount, array $options = [])
    {
        $this->checkGateway();

        $this->tab($description, $amount, $options);

        return $this->invoice();
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

        // Here we will loop through the Stripe invoices and create our own custom Invoice
        // instances that have more helper methods and are generally more convenient to
        // work with than the plain Stripe objects are. Then, we'll return the array.
        if ($stripeInvoices = $this->asGatewayCustomer()->invoices(array_merge(['limit' => 24], $parameters))) {
            foreach ($stripeInvoices->data as $invoice) {
                if ($invoice->paid || $includePending) {
                    $invoices[] = new Invoice($this->billable, $invoice); // FIXME: Braintree parity
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
            return new Invoice($this->billable, StripeInvoice::retrieve($id, Gateway::getApiKey()));
        } catch (\Exception $e) {
            //
        }
    }

    /**
     * Get the entity's upcoming invoice.
     *
     * @return \Laravel\Cashier\Gateway\Invoice|null
     *
     * @throws \Laravel\Cashier\Exception
     */
    public function upcomingInvoice()
    {
        $this->checkGateway();

        try {
            $stripeInvoice = StripeInvoice::upcoming([
                'customer' => $this->billable->payment_gateway_id
            ], ['api_key' => Gateway::getApiKey()]);

            return new Invoice($this->billable, $stripeInvoice);
        } catch (InvalidRequest $e) {
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
     * @param  string $id
     * @param  array $data
     * @param  string $storagePath
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     * @throws \Laravel\Cashier\Exception
     */
    public function downloadInvoice($id, array $data, $storagePath = null)
    {
        $this->checkGateway();

        return $this->findInvoiceOrFail($id)->download($data, $storagePath);
    }

    /**
     * Get a collection of the entity's cards.
     *
     * @param  array $parameters
     * @return Card[]|\Illuminate\Support\Collection
     *
     * @throws \Laravel\Cashier\Exception
     */
    public function cards($parameters = [])
    {
        // TODO: Add to braintree?

        $this->checkGateway();

        $cards = [];

        $parameters = array_merge(['limit' => 24], $parameters, ['object' => 'card']);

        if ($stripeCards = $this->asGatewayCustomer()->sources->all($parameters)) {
            foreach ($stripeCards->data as $card) {
                $cards[] = new Card($this->billable, $card);
            }
        }

        return new Collection($cards);
    }

    /**
     * Deletes the entity's cards.
     *
     * @return void
     *
     * @throws \Laravel\Cashier\Exception
     */
    public function deleteCards()
    {
        $this->checkGateway();

        $this->cards()->each(function (Card $card) {
            $card->delete();
        });
    }

    /**
     * Fills the model's properties with the source from Stripe.
     *
     * @param  \Stripe\Card|null  $card
     * @return \Laravel\Cashier\Billable
     */
    protected function fillCardDetails($card)
    {
        if ($card) {
            $this->billable->card_brand = $card->brand;
            $this->billable->card_last_four = $card->last4;
        }

        return $this->billable;
    }
}

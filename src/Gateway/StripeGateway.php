<?php

namespace Laravel\Cashier\Gateway;

use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Billable;
use Laravel\Cashier\Gateway\Stripe\SubscriptionBuilder;
use Laravel\Cashier\Gateway\Stripe\SubscriptionManager;
use Laravel\Cashier\Subscription;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Laravel\Cashier\Gateway\StripeGateway;
use Stripe\Customer as StripeCustomer;
use Stripe\Error\InvalidRequest as StripeErrorInvalidRequest;
use Stripe\Invoice as StripeInvoice;
use Stripe\InvoiceItem as StripeInvoiceItem;
use Stripe\Refund as StripeRefund;
use Stripe\Token as StripeToken;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StripeGateway extends Gateway
{
    /**
     * Stripe API key.
     *
     * @var string
     */
    protected static $apiKey;

    /**
     * Get the Stripe API key.
     *
     * @return string
     */
    public static function getApiKey()
    {
        if (null === static::$apiKey) {
            static::$apiKey = getenv('STRIPE_SECRET', config('services.stripe.secret'));
        }

        return static::$apiKey;
    }

    /**
     * Set the Stripe API key.
     *
     * @param  string $key
     * @return void
     */
    public static function setApiKey($key)
    {
        static::$apiKey = $key;
    }

    /**
     * Get the name of this gateway.
     *
     * @return string
     */
    public function getName()
    {
        return 'stripe';
    }

    /**
     * Manage a subscription.
     *
     * @param \Laravel\Cashier\Subscription $subscription
     * @return \Laravel\Cashier\Gateway\Stripe\SubscriptionManager
     */
    public function manageSubscription(Subscription $subscription)
    {
        return new SubscriptionManager($subscription);
    }

    public function buildSubscription(Model $billable, $subscription, $plan)
    {
        return new SubscriptionBuilder($billable, $subscription, $plan);
    }

    /**
     * Get the Stripe customer for the Stripe model.
     *
     * @param  \Laravel\Cashier\Billable  $billable
     * @return \Stripe\Customer
     */
    public function asCustomer(Billable $billable)
    {
        return StripeCustomer::retrieve($billable->getPaymentGatewayIdAttribute(), static::getApiKey());
    }

    /**
     * Create a Stripe customer for the given Stripe model.
     *
     * @param  string $token
     * @param  array $options
     * @return \Stripe\Customer
     */
    public function createAsCustomer(Billable $billable, $token, array $options = [])
    {
        $options = array_key_exists('email', $options) ? $options : array_merge($options, ['email' => $this->email]);

        // Here we will create the customer instance on Stripe and store the ID of the
        // user from Stripe. This ID will correspond with the Stripe user instances
        // and allow us to retrieve users from Stripe later when we need to work.
        $customer = StripeCustomer::create($options, $this->getStripeKey());

        $this->stripe_id = $customer->id;

        $this->save();

        // Next we will add the credit card to the user's account on Stripe using this
        // token that was provided to this method. This will allow us to bill users
        // when they subscribe to plans or we need to do one-off charges on them.
        if (! is_null($token)) {
            $this->updateCard($token);
        }

        return $customer;
    }

    /**
     * Update customer's credit card.
     *
     * @param  string $token
     * @return void
     */
    public function updateCard($token, array $options = [])
    {
        $customer = $this->asStripeCustomer();

        $token = StripeToken::retrieve($token, ['api_key' => $this->getStripeKey()]);

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
        $source = $customer->default_source ? $customer->sources->retrieve($customer->default_source) : null;

        $this->fillCardDetails($source);

        $this->save();
    }

    /**
     * Apply a coupon to the billable entity.
     *
     * @param  string $coupon
     * @return void
     */
    public function applyCoupon($coupon, $subscription = 'default', $removeOthers = false)
    {
        // FIXME $coupon, $subscription = 'default', $removeOthers = false

        $customer = $this->asStripeCustomer();

        $customer->coupon = $coupon;

        $customer->save();
    }

    /**
     * Make a "one off" charge on the customer for the given amount.
     *
     * @param  int  $amount
     * @param  array  $options
     * @return \Stripe\Charge
     *
     * @throws \InvalidArgumentException
     */
    public function charge($amount, array $options = [])
    {
        $options = array_merge([
            'currency' => $this->preferredCurrency(),
        ], $options);

        $options['amount'] = $amount;

        if (! array_key_exists('source', $options) && $this->stripe_id) {
            $options['customer'] = $this->stripe_id;
        }

        if (! array_key_exists('source', $options) && ! array_key_exists('customer', $options)) {
            throw new InvalidArgumentException('No payment source provided.');
        }

        return StripeCharge::create($options, ['api_key' => $this->getStripeKey()]);
    }

    /**
     * Refund a customer for a charge.
     *
     * @param  string $charge
     * @param  array $options
     * @return \Stripe\Charge
     *
     * @throws \InvalidArgumentException
     */
    public function refund($charge, array $options = [])
    {
        $options['charge'] = $charge;

        return StripeRefund::create($options, ['api_key' => $this->getStripeKey()]);
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
     */
    public function tab($description, $amount, array $options = [])
    {
        if (! $this->stripe_id) {
            throw new InvalidArgumentException(class_basename($this).' is not a Stripe customer. See the createAsStripeCustomer method.');
        }

        $options = array_merge([
            'customer' => $this->stripe_id,
            'amount' => $amount,
            'currency' => $this->preferredCurrency(),
            'description' => $description,
        ], $options);

        return StripeInvoiceItem::create($options, ['api_key' => $this->getStripeKey()]);
    }

    /**
     * Invoice the customer for the given amount and generate an invoice immediately.
     *
     * @param  string $description
     * @param  int $amount
     * @param  array $options
     * @return \Laravel\Cashier\Invoice|bool
     */
    public function invoiceFor($description, $amount, array $options = [])
    {
        $this->tab($description, $amount, $options);

        return $this->invoice();
    }

    /**
     * Invoice the billable entity outside of regular billing cycle.
     *
     * @return \Stripe\Invoice|bool
     */
    public function invoice()
    {
        // FIXME
        if ($this->stripe_id) {
            try {
                return StripeInvoice::create(['customer' => $this->stripe_id], $this->getStripeKey())->pay();
            } catch (StripeErrorInvalidRequest $e) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get a collection of the entity's invoices.
     *
     * @param  bool $includePending
     * @param  array $parameters
     * @return \Illuminate\Support\Collection
     */
    public function invoices($includePending = false, $parameters = [])
    {
        $invoices = [];

        $parameters = array_merge(['limit' => 24], $parameters);

        $stripeInvoices = $this->asStripeCustomer()->invoices($parameters);

        // Here we will loop through the Stripe invoices and create our own custom Invoice
        // instances that have more helper methods and are generally more convenient to
        // work with than the plain Stripe objects are. Then, we'll return the array.
        if (! is_null($stripeInvoices)) {
            foreach ($stripeInvoices->data as $invoice) {
                if ($invoice->paid || $includePending) {
                    $invoices[] = new Invoice($this, $invoice);
                }
            }
        }

        return new Collection($invoices);
    }

    /**
     * Create an invoice download Response.
     *
     * @param  string $id
     * @param  array $data
     * @param  string $storagePath
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function downloadInvoice($id, array $data, $storagePath = null)
    {
        return $this->findInvoiceOrFail($id)->download($data, $storagePath);
    }

    /**
     * Find an invoice or throw a 404 error.
     *
     * @param  string $id
     * @return \Laravel\Cashier\Invoice
     */
    public function findInvoiceOrFail($id)
    {
        $invoice = $this->findInvoice($id);

        if (is_null($invoice)) {
            throw new NotFoundHttpException;
        }

        return $invoice;
    }

    /**
     * Find an invoice by ID.
     *
     * @param  string $id
     * @return \Laravel\Cashier\Invoice|null
     */
    public function findInvoice($id)
    {
        try {
            return new Invoice($this, StripeInvoice::retrieve($id, $this->getStripeKey()));
        } catch (Exception $e) {
            //
        }
    }
}

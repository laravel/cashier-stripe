<?php

namespace Laravel\Cashier\Gateway;

use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Billable;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Subscription;

abstract class Gateway
{
    /**
     * Register gateway with Cashier.
     */
    public function register()
    {
        Cashier::addGateway($this);
    }

    /**
     * Convert a zero-decimal value (eg. cents) into the value appropriate for this gateway.
     *
     * @param  int  $value
     * @return int|float
     */
    public function convertZeroDecimalValue($value)
    {
        return $value;
    }

    abstract public function manageSubscription(Subscription $subscription);

    abstract public function buildSubscription(Model $billable, $subscription, $plan);

    /**
     * Get the name of the gateway.
     *
     * @return string
     */
    abstract public function getName();

    abstract public function asCustomer(Billable $billable);

    abstract public function createAsCustomer(Billable $billable, $token, array $options = []);

    abstract public function applyCoupon($coupon, $subscription = 'default', $removeOthers = false);

    abstract public function updateCard($token, array $options = []);

    abstract public function charge($amount, array $options = []);

    abstract public function refund($charge, array $options = []);

    abstract public function tab($description, $amount, array $options = []);

    abstract public function invoiceFor($description, $amount, array $options = []);

    abstract public function invoices($includePending = false, $parameters = []);

    abstract public function findInvoice($id);

    abstract public function findInvoiceOrFail($id);

    abstract public function downloadInvoice($id, array $data, $storagePath = null);
}

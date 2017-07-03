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

    abstract public function applyCoupon(Billable $billable, $coupon, $subscription = 'default', $removeOthers = false);

    abstract public function updateCard(Billable $billable, $token, array $options = []);

    abstract public function charge(Billable $billable, $amount, array $options = []);

    abstract public function refund(Billable $billable, $charge, array $options = []);

    abstract public function tab(Billable $billable, $description, $amount, array $options = []);

    abstract public function invoiceFor(Billable $billable, $description, $amount, array $options = []);

    abstract public function invoices(Billable $billable, $includePending = false, $parameters = []);

    abstract public function findInvoice(Billable $billable, $id);

    abstract public function findInvoiceOrFail(Billable $billable, $id);

    abstract public function downloadInvoice(Billable $billable, $id, array $data, $storagePath = null);
}

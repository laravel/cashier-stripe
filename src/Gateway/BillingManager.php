<?php

namespace Laravel\Cashier\Gateway;

use Laravel\Cashier\Billable;
use Laravel\Cashier\Exception;

abstract class BillingManager
{
    protected $billable;

    protected $gateway;

    public function __construct(Billable $billable, Gateway $gateway)
    {
        $this->billable = $billable;
        $this->gateway = $gateway;
    }

    public function getBillable()
    {
        return $this->billable;
    }

    public function getGateway()
    {
        return $this->gateway;
    }

    /**
     * Prevent gateway mismatches.
     *
     * @throws \Laravel\Cashier\Exception
     */
    protected function checkGateway()
    {
        $gateway = $this->billable->getAssignedPaymentGateway();

        if (!$gateway) {
            return;
        }

        $expected = $this->gateway->getName();
        if ($gateway !== $expected) {
            throw new Exception("Trying to call a '{$expected}' on a model using '{$gateway}'.");
        }
    }

    abstract public function asGatewayCustomer();

    abstract public function createAsGatewayCustomer($token, array $options = []);

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

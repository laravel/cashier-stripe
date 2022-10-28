<?php

namespace Laravel\Cashier\Concerns;

use Laravel\Cashier\Cashier;

trait HandlesTaxes
{
    /**
     * The IP address of the customer used to determine the tax location.
     *
     * @var string|null
     */
    public $customerIpAddress;

    /**
     * The pre-collected billing address used to estimate tax rates when performing "one-off" charges.
     *
     * @var array
     */
    public $estimationBillingAddress = [];

    /**
     * Indicates if Tax IDs should be collected during a Stripe Checkout session.
     *
     * @var bool
     */
    public $collectTaxIds = false;

    /**
     * Set the The IP address of the customer used to determine the tax location.
     *
     * @return $this
     */
    public function withTaxIpAddress($ipAddress)
    {
        $this->customerIpAddress = $ipAddress;

        return $this;
    }

    /**
     * Set a pre-collected billing address used to estimate tax rates when performing "one-off" charges.
     *
     * @param  string  $country
     * @param  string|null  $postalCode
     * @param  string|null  $state
     * @return $this
     */
    public function withTaxAddress($country, $postalCode = null, $state = null)
    {
        $this->estimationBillingAddress = array_filter([
            'country' => $country,
            'postal_code' => $postalCode,
            'state' => $state,
        ]);

        return $this;
    }

    /**
     * Get the payload for Stripe automatic tax calculation.
     *
     * @return array|null
     */
    protected function automaticTaxPayload()
    {
        return array_filter([
            'customer_ip_address' => $this->customerIpAddress,
            'enabled' => $this->isAutomaticTaxEnabled(),
            'estimation_billing_address' => $this->estimationBillingAddress,
        ]);
    }

    /**
     * Determine if automatic tax is enabled.
     *
     * @return bool
     */
    protected function isAutomaticTaxEnabled()
    {
        return Cashier::$calculatesTaxes;
    }

    /**
     * Indicate that Tax IDs should be collected during a Stripe Checkout session.
     *
     * @return $this
     */
    public function collectTaxIds()
    {
        $this->collectTaxIds = true;

        return $this;
    }
}

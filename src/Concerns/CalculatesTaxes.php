<?php

namespace Laravel\Cashier\Concerns;

trait CalculatesTaxes
{
    /**
     * Indicates if Cashier should automatically calculate tax for the new subscription.
     *
     * @var bool
     */
    protected $automaticTax = false;

    /**
     * The IP address of the customer used to determine tax location.
     *
     * @var string|null
     */
    protected $customerIPAddress;

    /**
     * The pre-collected billing address used to estimate tax rates when performing "one-off" charges.
     *
     * @var array
     */
    protected $estimationBillingAddress = [];

    /**
     * Allow taxes to be automatically calculated by Stripe.
     *
     * @return $this
     */
    public function withTax()
    {
        $this->automaticTax = true;

        return $this;
    }

    /**
     * Set the The IP address of the customer used to determine tax location.
     *
     * @return $this
     */
    public function withTaxIPAddress($ipAddress)
    {
        $this->customerIPAddress = $ipAddress;

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
     * Get the payload for the automatic tax calculation.
     *
     * @return array|null
     */
    protected function automaticTaxPayload()
    {
        return array_filter([
            'customer_ip_address' => $this->customerIPAddress,
            'enabled' => $this->automaticTax,
            'estimation_billing_address' => $this->estimationBillingAddress,
        ]);
    }
}

<?php

namespace Laravel\Cashier\Concerns;

trait Prorates
{
    /**
     * Indicates if the plan change should be prorated.
     *
     * @var string
     */
    protected $prorationBehavior = 'create_prorations';

    /**
     * Indicate that the plan change should not be prorated.
     *
     * @return $this
     */
    public function noProrate()
    {
        $this->prorationBehavior = 'none';

        return $this;
    }

    /**
     * Indicate that the plan change should be prorated.
     *
     * @return $this
     */
    public function prorate()
    {
        $this->prorationBehavior = 'create_prorations';

        return $this;
    }

    /**
     * Indicate that the plan change should always be invoiced.
     *
     * @return $this
     */
    public function alwaysInvoice()
    {
        $this->prorationBehavior = 'always_invoice';

        return $this;
    }

    /**
     * Set the prorating behavior.
     *
     * @param  string  $prorationBehavior
     * @return $this
     */
    public function setProrationBehavior($prorationBehavior)
    {
        $this->prorationBehavior = $prorationBehavior;

        return $this;
    }

    /**
     * Determine the prorating behavior when updating the subscription.
     *
     * @return string
     */
    public function prorateBehavior()
    {
        return $this->prorationBehavior;
    }
}

<?php

namespace Laravel\Cashier\Concerns;

trait Prorates
{
    /**
     * Indicates if the plan change should be prorated.
     *
     * @var bool
     */
    protected $prorate = true;

    /**
     * Indicate that the plan change should not be prorated.
     *
     * @return $this
     */
    public function noProrate()
    {
        $this->prorate = false;

        return $this;
    }

    /**
     * Indicate that the plan change should be prorated.
     *
     * @return $this
     */
    public function prorate()
    {
        $this->prorate = true;

        return $this;
    }

    /**
     * Determine the prorating behavior when updating the subscription.
     *
     * @return string
     */
    public function prorateBehavior()
    {
        return $this->prorate ? 'create_prorations' : 'none';
    }
}

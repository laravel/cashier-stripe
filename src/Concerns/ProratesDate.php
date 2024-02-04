<?php

namespace Laravel\Cashier\Concerns;

trait ProratesDate
{
    /**
     * Indicates when the price change should be prorated.
     */
    protected ?int $prorationDate = null;

    /**
     * Indicate that date for proration.
     */
    public function prorateDate(\DateTimeInterface|int $date = null): static
    {
        $this->prorationDate = $date instanceof \DateTimeInterface ? $date->getTimestamp() : $date;

        return $this;
    }
}

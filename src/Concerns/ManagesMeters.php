<?php

namespace Laravel\Cashier\Concerns;
use Illuminate\Support\Collection;

trait ManagesMeters
{
    /**
     * List all the billing meters.
     *
     * @param  array  $options
     * @param  array  $requestOptions
     * @return \Illuminate\Support\Collection
     */
    public function meters(array $options = [], array $requestOptions = []): Collection
    {
        return new Collection($this->stripe()->billing->meters->all($options, $requestOptions)->data);
    }
}

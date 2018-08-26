<?php

namespace Laravel\Cashier;

use Illuminate\Database\Eloquent\Collection;

class Subscriptions extends Collection
{
    /**
     * @return $this
     */
    public function onTrial()
    {
        return $this->filter->onTrial();
    }

    /**
     * @param string $subscription
     *
     * @return $this
     */
    public function subscribed($subscription = 'default')
    {
        return $this->filter->subscribed($subscription);
    }
}

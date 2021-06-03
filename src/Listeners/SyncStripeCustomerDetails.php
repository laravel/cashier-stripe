<?php

namespace Laravel\Cashier\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Laravel\Cashier\Cashier;

class SyncStripeCustomerDetails implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        if ($event->customer instanceof Cashier::$customerModel) {
            $event->customer->syncStripeCustomerDetails();
        }
    }
}

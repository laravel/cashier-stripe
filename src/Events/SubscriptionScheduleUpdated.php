<?php

namespace Laravel\Cashier\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionScheduleUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * The billable entity.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    public $billable;

    /**
     * The subscription schedule instance.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    public $subscriptionSchedule;

    /**
     * Create a new event instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $billable
     * @param  \Illuminate\Database\Eloquent\Model  $subscriptionSchedule
     * @return void
     */
    public function __construct(Model $billable, Model $subscriptionSchedule)
    {
        $this->billable = $billable;
        $this->subscriptionSchedule = $subscriptionSchedule;
    }
}

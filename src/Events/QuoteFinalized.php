<?php

namespace Laravel\Cashier\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuoteFinalized
{
    use Dispatchable, SerializesModels;

    /**
     * The billable entity.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    public $billable;

    /**
     * The quote instance.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    public $quote;

    /**
     * Create a new event instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $billable
     * @param  \Illuminate\Database\Eloquent\Model  $quote
     * @return void
     */
    public function __construct(Model $billable, Model $quote)
    {
        $this->billable = $billable;
        $this->quote = $quote;
    }
}

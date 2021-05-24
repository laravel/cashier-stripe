<?php

namespace Laravel\Cashier\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;

class User extends Model
{
    use Billable, Notifiable;

    public $taxRates = [];

    public $priceTaxRates = [];

    /**
     * Get the tax rates to apply to the subscription.
     *
     * @return array
     */
    public function taxRates()
    {
        return $this->taxRates;
    }

    /**
     * Get the tax rates to apply to individual subscription items.
     *
     * @return array
     */
    public function priceTaxRates()
    {
        return $this->priceTaxRates;
    }
}

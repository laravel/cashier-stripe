<?php namespace Laravel\Cashier;

class CurrencySymbol {

    /**
     * The Stripe Currency String
     *
     * @var string
     */
    protected $currency;

    /**
     * Create a new currency symbol instance
     *
     * @param  string  $currency The Stripe Currency String
     * @return void
     */
    public function __construct($currency)
    {
        $this->currency = $currency;
    }

    /**
     * Retrieve the currency symbol
     *
     * @return string
     */
    public function get()
    {
        $symbol = '$';

        switch( $this->currency )
        {
            case "gbp":
                $symbol = '£';
        }

        return $symbol;
    }

}

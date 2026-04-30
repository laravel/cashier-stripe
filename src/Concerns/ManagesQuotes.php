<?php

namespace Laravel\Cashier\Concerns;

use Laravel\Cashier\Quote;
use Laravel\Cashier\QuoteBuilder;

trait ManagesQuotes
{
    /**
     * Get all of the quotes for the Stripe model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function quotes()
    {
        return $this->hasMany(Quote::class, $this->getForeignKey())->orderBy('created_at', 'desc');
    }

    /**
     * Get a quote instance by Stripe ID.
     *
     * @param  string  $quoteId
     * @return \Laravel\Cashier\Quote|null
     */
    public function findQuote($quoteId)
    {
        return $this->quotes()->where('stripe_id', $quoteId)->first();
    }

    /**
     * Begin creating a new quote.
     *
     * @return \Laravel\Cashier\QuoteBuilder
     */
    public function newQuote()
    {
        return new QuoteBuilder($this);
    }
}

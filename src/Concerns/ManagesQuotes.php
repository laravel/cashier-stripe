<?php

namespace Laravel\Cashier\Concerns;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\QuoteBuilder;

trait ManagesQuotes
{
    /**
     * Get all of the quotes for the Stripe model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Cashier::$quoteModel, $this->getForeignKey())
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get a quote instance by Stripe ID.
     *
     * @param  string  $quoteId
     * @return \Laravel\Cashier\Quote|null
     */
    public function findQuote(string $quoteId)
    {
        return $this->quotes()->where('stripe_id', $quoteId)->first();
    }

    /**
     * Begin creating a new quote.
     *
     * @return \Laravel\Cashier\QuoteBuilder
     */
    public function newQuote(): QuoteBuilder
    {
        return new QuoteBuilder($this);
    }
}

<?php

namespace Laravel\Cashier\Concerns;

use Illuminate\Support\Collection;
use Laravel\Cashier\RateCard;

trait ManagesPricingModels
{
    use InteractsWithStripe;

    /**
     * Get all pricing models (rate cards) for the customer.
     *
     * @param  array  $options
     * @param  array  $requestOptions
     * @return \Illuminate\Support\Collection
     */
    public function pricingModels(array $options = [], array $requestOptions = []): Collection
    {
        return new Collection($this->stripe()->billing->rateCards->all($options, $requestOptions)->data);
    }

    /**
     * Create a rate card for the customer.
     *
     * @param  array  $config
     * @param  array  $options
     * @param  array  $requestOptions
     * @return \Laravel\Cashier\RateCard
     */
    public function createPricingModel(array $config, array $options = [], array $requestOptions = []): RateCard
    {
        $options = array_merge($config, $options);

        $stripeRateCard = $this->stripe()->billing->rateCards->create($options, $requestOptions);

        return new RateCard($stripeRateCard);
    }

    /**
     * Retrieve a specific pricing model by ID.
     *
     * @param  string  $rateCardId
     * @param  array  $requestOptions
     * @return \Laravel\Cashier\RateCard
     */
    public function findPricingModel(string $rateCardId, array $requestOptions = []): RateCard
    {
        $stripeRateCard = $this->stripe()->billing->rateCards->retrieve($rateCardId, [], $requestOptions);

        return new RateCard($stripeRateCard);
    }

    /**
     * Update a pricing model.
     *
     * @param  string  $rateCardId
     * @param  array  $options
     * @param  array  $requestOptions
     * @return \Laravel\Cashier\RateCard
     */
    public function updatePricingModel(string $rateCardId, array $options = [], array $requestOptions = []): RateCard
    {
        $stripeRateCard = $this->stripe()->billing->rateCards->update($rateCardId, $options, $requestOptions);

        return new RateCard($stripeRateCard);
    }
}

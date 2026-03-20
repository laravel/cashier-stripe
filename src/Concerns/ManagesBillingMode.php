<?php

namespace Laravel\Cashier\Concerns;

use InvalidArgumentException;
use Laravel\Cashier\Cashier;

trait ManagesBillingMode
{
    /**
     * The billing mode for the subscription.
     *
     * @var array|null
     */
    public ?array $billingMode = null;

    /**
     * Set the billing mode for the subscription.
     *
     * @param  'classic'|'flexible'  $type
     * @return $this
     */
    public function withBillingMode(string $type = 'flexible')
    {
        if (! in_array($type, ['classic', 'flexible'])) {
            throw new InvalidArgumentException("Invalid billing mode [{$type}]. Must be 'classic' or 'flexible'.");
        }

        $this->billingMode = ['type' => $type];

        return $this;
    }

    /**
     * Set the proration discounts behavior for flexible billing mode.
     *
     * This controls how prorations and discounts are displayed on invoices
     * when using flexible billing mode.
     *
     * @param  'included'|'itemized'  $behavior
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function withProrationDiscounts(string $behavior = 'included')
    {
        if (! in_array($behavior, ['included', 'itemized'])) {
            throw new InvalidArgumentException("Invalid proration discounts behavior [{$behavior}]. Must be 'included' or 'itemized'.");
        }

        // Ensure billing mode is set to flexible
        if (! isset($this->billingMode) || ($this->billingMode['type'] ?? null) !== 'flexible') {
            $this->withBillingMode('flexible');
        }

        $this->billingMode['flexible'] = ['proration_discounts' => $behavior];

        return $this;
    }

    /**
     * Get the default billing mode.
     *
     * @return string
     */
    protected function getDefaultBillingMode(): string
    {
        return Cashier::$defaultBillingMode;
    }

    /**
     * Get the effective billing mode.
     *
     * @return string
     */
    protected function getEffectiveBillingMode(): string
    {
        return $this->billingMode['type'] ?? $this->getDefaultBillingMode();
    }

    /**
     * Get the billing mode array for inclusion in a Stripe API payload.
     *
     * Returns null when the effective mode is 'classic' (Stripe's default)
     * to maintain backwards compatibility with existing integrations.
     *
     * @return array{type: string}|null
     */
    protected function getBillingModeForPayload(): ?array
    {
        $effectiveMode = $this->getEffectiveBillingMode();

        if ($effectiveMode === 'flexible') {
            $payload = ['type' => 'flexible'];

            if (isset($this->billingMode['flexible'])) {
                $payload['flexible'] = $this->billingMode['flexible'];
            }

            return $payload;
        }

        return null;
    }
}

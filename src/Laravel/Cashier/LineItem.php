<?php

namespace Laravel\Cashier;

use Laravel\Cashier\Contracts\Billable as BillableContract;

class LineItem
{
    /**
     * The billable instance.
     *
     * @var \Laravel\Cashier\Contracts\Billable
     */
    protected $billable;

    /**
     * The Stripe invoice line instance.
     *
     * @var object
     */
    protected $stripeLine;

    /**
     * Create a new line item instance.
     *
     * @param  Billable  $billable
     * @param  object  $stripeLine
     * @return void
     */
    public function __construct(BillableContract $billable, $stripeLine)
    {
        $this->billable = $billable;
        $this->stripeLine = $stripeLine;
    }

    /**
     * Get the total amount for the line item in dollars.
     *
     * @param  string $symbol The Symbol you want to show
     * @return string
     */
    public function dollars()
    {
        return $this->totalWithCurrency();
    }

    /**
     * Get the total amount for the line item with the currency symbol.
     *
     * @return string
     */
    public function totalWithCurrency()
    {
        if (starts_with($total = $this->total(), '-')) {
            return '-'.$this->billable->addCurrencySymbol(ltrim($total, '-'));
        } else {
            return $this->billable->addCurrencySymbol($total);
        }
    }

    /**
     * Get the total for the line item.
     *
     * @return float
     */
    public function total()
    {
        return $this->billable->formatCurrency($this->amount);
    }

    /**
     * Get a human readable date for the start date.
     *
     * @return string
     */
    public function startDateString()
    {
        if ($this->isSubscription()) {
            return date('M j, Y', $this->period->start);
        }
    }

    /**
     * Get a human readable date for the end date.
     *
     * @return string
     */
    public function endDateString()
    {
        if ($this->isSubscription()) {
            return date('M j, Y', $this->period->end);
        }
    }

    /**
     * Determine if the line item is for a subscription.
     *
     * @return bool
     */
    public function isSubscription()
    {
        return $this->type == 'subscription';
    }

    /**
     * Get the Stripe line item instance.
     *
     * @return object
     */
    public function getStripeLine()
    {
        return $this->stripeLine;
    }

    /**
     * Dynamically access the Stripe line item instance.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->stripeLine->{$key};
    }

    /**
     * Dynamically set values on the Stripe line item instance.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return mixed
     */
    public function __set($key, $value)
    {
        $this->stripeLine->{$key} = $value;
    }
}

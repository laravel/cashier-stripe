<?php namespace Laravel\Cashier;

class LineItem {

	/**
	 * The Stripe invoice line instance.
	 *
	 * @var object
	 */
	protected $stripeLine;

	/**
	 * The Currency The Line Item Is In
	 *
	 * @var string
	 */
	protected $currency;

	/**
	 * The Symbol to use for the currency this line item is in
	 *
	 * @var string
	 */
	protected $currencySymbol;

	/**
	 * Create a new line item instance.
	 *
	 * @param  object  $stripeLine
	 * @return void
	 */
	public function __construct($stripeLine)
	{
		$this->stripeLine = $stripeLine;
		$this->currency = isset( $stripeLine->currency ) ? $stripeLine->currency : 'usd';
		$this->currencySymbol = ( new CurrencySymbol( $this->currency ) )->get();
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
	 * Get the total amount for the line item in the currency symbol of your choice
	 *
	 * @param  string $symbol The Symbol you want to show
	 * @return string
	 */
	public function totalWithCurrency($symbol)
	{
		if (starts_with($total = $this->total(), '-'))
		{
			return '-'.$this->currencySymbol.ltrim($total, '-');
		}
		else
		{
			return $this->currencySymbol.$total;
		}
	}

	/**
	 * Get the total for the line item.
	 *
	 * @return float
	 */
	public function total()
	{
		return number_format($this->amount / 100, 2);
	}

	/**
	 * Get a human readable date for the start date.
	 *
	 * @return string
	 */
	public function startDateString()
	{
		if ($this->isSubscription())
		{
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
		if ($this->isSubscription())
		{
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
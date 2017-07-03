<?php

namespace Laravel\Cashier;

use Exception;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;
use Laravel\Cashier\Exception as CashierException;
use Laravel\Cashier\Gateway\Gateway;

class Cashier
{
    /**
     * Singleton instance to allow for backwards-compatible static calls.
     *
     * @var static
     */
    protected static $instance;

    /**
     * Cache of gateway instances.
     *
     * @var array
     */
    protected $gateways = [];

    /**
     * The current currency.
     *
     * @var string
     */
    protected $currency = 'usd';

    /**
     * The current currency symbol.
     *
     * @var string
     */
    protected $currencySymbol = '$';

    /**
     * The custom currency formatter.
     *
     * @var callable
     */
    protected $formatCurrencyUsing;

    /**
     * The service container instance.
     *
     * @var \Illuminate\Contracts\Container\Container
     */
    protected $app;

    /**
     * The default payment gateway.
     *
     * @var string
     */
    protected $defaultGateway = 'stripe';

    /**
     * Create a new Cashier instance.
     *
     * @param \Illuminate\Contracts\Container\Container $app
     */
    public function __construct(Container $app)
    {
        $this->app = $app;

        $config = $this->app->make(Repository::class);
        $this->defaultGateway = $config->get('cashier.default_gateway', $this->defaultGateway);
    }

    /**
     * Set the singleton instance.
     *
     * @param  \Laravel\Cashier\Cashier $instance
     * @return \Laravel\Cashier\Cashier
     */
    public static function setInstance(Cashier $instance)
    {
        static::$instance = $instance;

        return $instance;
    }

    /**
     * Allow for backwards-compatible static calls
     *
     * @param  string $name
     * @param  array $arguments
     * @return mixed
     */
    public static function __callStatic($name, $arguments)
    {
        return call_user_func_array([self::$instance], $arguments);
    }

    /**
     * Add a gateway to Cashier.
     *
     * @param  \Laravel\Cashier\Gateway\Gateway  $gateway
     * @return static
     */
    public function addGateway(Gateway $gateway)
    {
        $this->gateways[$gateway->getName()] = $gateway;

        return $this;
    }

    /**
     * @param string|null $name
     * @return \Laravel\Cashier\Gateway\Gateway
     * @throws \Laravel\Cashier\Exception
     */
    public function gateway($name = null)
    {
        if (!$this->hasGateway($name)) {
            throw new CashierException("Gateway '{$name}' not registered.");
        }

        return $this->gateways[$name];
    }

    /**
     * @param  string  $name
     * @return bool
     */
    public function hasGateway($name = null)
    {
        return isset($this->gateways[$name ?: $this->defaultGateway]);
    }

    /**
     * Get array of registered gateways.
     *
     * @return Gateway[]
     */
    public function getGateways()
    {
        return $this->gateways;
    }

    /**
     * Set the currency to be used when billing models.
     *
     * @param  string $currency
     * @param  string|null $symbol
     * @return void
     */
    public function useCurrency($currency, $symbol = null)
    {
        $this->currency = $currency;

        $this->useCurrencySymbol($symbol ?: static::guessCurrencySymbol($currency));
    }

    /**
     * Set the currency symbol to be used when formatting currency.
     *
     * @param  string $symbol
     * @return void
     */
    public function useCurrencySymbol($symbol)
    {
        $this->currencySymbol = $symbol;
    }

    /**
     * Guess the currency symbol for the given currency.
     *
     * @param  string $currency
     * @return string
     * @throws \Exception
     */
    protected static function guessCurrencySymbol($currency)
    {
        switch (strtolower($currency)) {
            case 'usd':
            case 'aud':
            case 'cad':
                return '$';
            case 'eur':
                return '€';
            case 'gbp':
                return '£';
            default:
                throw new Exception('Unable to guess symbol for currency. Please explicitly specify it.');
        }
    }

    /**
     * Get the currency currently in use.
     *
     * @return string
     */
    public function usesCurrency()
    {
        return $this->currency;
    }

    /**
     * Set the custom currency formatter.
     *
     * @param  callable $callback
     * @return void
     */
    public function formatCurrencyUsing(callable $callback)
    {
        $this->formatCurrencyUsing = $callback;
    }

    /**
     * Format the given amount into a displayable currency.
     *
     * @param  int $amount
     * @return string
     */
    public function formatAmount($amount)
    {
        if ($this->formatCurrencyUsing) {
            return call_user_func($this->formatCurrencyUsing, $amount);
        }

        $amount = number_format($amount / 100, 2);

        if (Str::startsWith($amount, '-')) {
            return '-'.$this->usesCurrencySymbol().ltrim($amount, '-');
        }

        return $this->usesCurrencySymbol().$amount;
    }

    /**
     * Get the currency symbol currently in use.
     *
     * @return string
     */
    public function usesCurrencySymbol()
    {
        return $this->currencySymbol;
    }

    /**
     * @param  string  $name
     * @param  array  $arguments
     * @return mixed
     */
    public function __call($name, array $arguments)
    {
        return call_user_func_array([$this->gateway(), $name], $arguments);
    }

    /**
     * @param  string  $name
     * @return bool
     */
    public function __isset($name)
    {
        return $this->hasGateway($name);
    }

    /**
     * @param  string  $name
     * @return \Laravel\Cashier\Contracts\Gateway
     */
    public function __get($name)
    {
        return $this->gateway($name);
    }

    /**
     * @param  string  $name
     * @param  mixed  $value
     * @throws \Exception
     */
    public function __set($name, $value)
    {
        throw new Exception('Cashier::__set() not allowed.');
    }
}

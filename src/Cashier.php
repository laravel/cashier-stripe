<?php

namespace Laravel\Cashier;

use Exception;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;
use Laravel\Cashier\Exception as CashierException;
use Laravel\Cashier\Gateway\Gateway;

/**
 * Class Cashier
 *
 * @package Laravel\Cashier
 */
class Cashier
{
    /**
     * Cache of gateway instances.
     *
     * @var array
     */
    protected static $gateways = [];

    /**
     * The current currency.
     *
     * @var string
     */
    protected static $currency = 'usd';

    /**
     * The current currency symbol.
     *
     * @var string
     */
    protected static $currencySymbol = '$';

    /**
     * The custom currency formatter.
     *
     * @var callable
     */
    protected static $formatCurrencyUsing;

    /**
     * The default payment gateway.
     *
     * @var string
     */
    protected static $defaultGateway = 'stripe';

    /**
     * Get the default gateway.
     *
     * @return string
     */
    public static function getDefaultGateway()
    {
        return static::$defaultGateway;
    }

    /**
     * Set the singleton instance.
     *
     * @param \Laravel\Cashier\Gateway\Gateway $gateway
     */
    public static function setDefaultGateway(Gateway $gateway)
    {
        static::addGateway($gateway);
        static::$defaultGateway = $gateway->getName();
    }

    /**
     * Add a gateway to Cashier.
     *
     * @param  \Laravel\Cashier\Gateway\Gateway $gateway
     */
    public static function addGateway(Gateway $gateway)
    {
        static::$gateways[$gateway->getName()] = $gateway;
    }

    /**
     * Allow for static calls.
     *
     * @param  string $name
     * @param  array $arguments
     * @return mixed
     */
    public static function __callStatic($name, $arguments)
    {
        return call_user_func_array([static::gateway(), $name], $arguments);
    }

    /**
     * @param string|null $name
     * @return \Laravel\Cashier\Gateway\Gateway
     * @throws \Laravel\Cashier\Exception
     */
    public static function gateway($name = null)
    {
        if (! static::hasGateway($name)) {
            throw new CashierException("Gateway '{$name}' not registered.");
        }

        return static::$gateways[$name];
    }

    /**
     * @param  string $name
     * @return bool
     */
    public static function hasGateway($name = null)
    {
        return isset(static::$gateways[$name ?: static::getDefaultGateway()]);
    }

    /**
     * Get array of registered gateways.
     *
     * @return Gateway[]
     */
    public static function getGateways()
    {
        return static::$gateways;
    }

    /**
     * Set the currency to be used when billing models.
     *
     * @param  string $currency
     * @param  string|null $symbol
     * @return void
     */
    public static function useCurrency($currency, $symbol = null)
    {
        static::$currency = $currency;

        static::useCurrencySymbol($symbol ?: static::guessCurrencySymbol($currency));
    }

    /**
     * Set the currency symbol to be used when formatting currency.
     *
     * @param  string $symbol
     * @return void
     */
    public static function useCurrencySymbol($symbol)
    {
        static::$currencySymbol = $symbol;
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
    public static function usesCurrency()
    {
        return static::$currency;
    }

    /**
     * Set the custom currency formatter.
     *
     * @param  callable $callback
     * @return void
     */
    public static function formatCurrencyUsing(callable $callback)
    {
        static::$formatCurrencyUsing = $callback;
    }

    /**
     * Format the given amount into a displayable currency.
     *
     * @param  int $amount
     * @return string
     */
    public static function formatAmount($amount)
    {
        if (static::$formatCurrencyUsing) {
            return call_user_func(static::$formatCurrencyUsing, $amount);
        }

        $amount = number_format($amount / 100, 2);

        if (Str::startsWith($amount, '-')) {
            return '-'.static::usesCurrencySymbol().ltrim($amount, '-');
        }

        return static::usesCurrencySymbol().$amount;
    }

    /**
     * Get the currency symbol currently in use.
     *
     * @return string
     */
    public static function usesCurrencySymbol()
    {
        return static::$currencySymbol;
    }
}

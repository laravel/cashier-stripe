<?php

namespace Laravel\Cashier;

use Money\Money;
use Money\Currency;
use NumberFormatter;
use Money\Currencies\ISOCurrencies;
use Money\Formatter\IntlMoneyFormatter;

class Cashier
{
    /**
     * The Stripe API version.
     *
     * @var string
     */
    const STRIPE_VERSION = '2019-03-14';

    /**
     * The publishable Stripe API key.
     *
     * @var string
     */
    protected static $stripeKey;

    /**
     * The secret Stripe API key.
     *
     * @var string
     */
    protected static $stripeSecret;

    /**
     * The current currency.
     *
     * @var string
     */
    protected static $currency = 'usd';

    /**
     * The locale used to format money values.
     *
     * To use more locales besides the default "en" locale, make
     * sure you have the ext-intl installed on your environment.
     *
     * @var string
     */
    protected static $currencyLocale = 'en';

    /**
     * The custom currency formatter.
     *
     * @var callable
     */
    protected static $formatCurrencyUsing;

    /**
     * Indicates if Cashier migrations will be run.
     *
     * @var bool
     */
    public static $runsMigrations = true;

    /**
     * Get the publishable Stripe API key.
     *
     * @return string
     */
    public static function stripeKey()
    {
        if (static::$stripeKey) {
            return static::$stripeKey;
        }

        if ($key = getenv('STRIPE_KEY')) {
            return $key;
        }

        return config('services.stripe.key');
    }

    /**
     * Set the publishable Stripe API key.
     *
     * @param  string  $key
     * @return void
     */
    public static function setStripeKey($key)
    {
        static::$stripeKey = $key;
    }

    /**
     * Get the secret Stripe API key.
     *
     * @return string
     */
    public static function stripeSecret()
    {
        if (static::$stripeSecret) {
            return static::$stripeSecret;
        }

        if ($key = getenv('STRIPE_SECRET')) {
            return $key;
        }

        return config('services.stripe.secret');
    }

    /**
     * Set the secret Stripe API key.
     *
     * @param  string  $key
     * @return void
     */
    public static function setStripeSecret($key)
    {
        static::$stripeSecret = $key;
    }

    /**
     * Get the default Stripe API options.
     *
     * @param  array  $options
     * @return array
     */
    public static function stripeOptions(array $options = [])
    {
        return array_merge([
            'api_key' => static::stripeSecret(),
            'stripe_version' => static::STRIPE_VERSION,
        ], $options);
    }

    /**
     * Get the class name of the billable model.
     *
     * @return string
     */
    public static function stripeModel()
    {
        return getenv('STRIPE_MODEL') ?: config('services.stripe.model', 'App\\User');
    }

    /**
     * Set the currency to be used when billing Stripe models.
     *
     * @param  string  $currency
     * @return void
     */
    public static function useCurrency($currency)
    {
        static::$currency = $currency;
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
     * Set the currency locale to format money.
     *
     * @param  string  $currencyLocale
     * @return void
     */
    public static function useCurrencyLocale($currencyLocale)
    {
        static::$currencyLocale = $currencyLocale;
    }

    /**
     * Set the custom currency formatter.
     *
     * @param  callable  $callback
     * @return void
     */
    public static function formatCurrencyUsing(callable $callback)
    {
        static::$formatCurrencyUsing = $callback;
    }

    /**
     * Format the given amount into a displayable currency.
     *
     * @param  int  $amount
     * @param  string|null  $currency
     * @return string
     */
    public static function formatAmount($amount, $currency = null)
    {
        if (static::$formatCurrencyUsing) {
            return call_user_func(static::$formatCurrencyUsing, $amount, $currency);
        }

        $money = new Money($amount, new Currency(strtoupper($currency ?? static::usesCurrency())));

        $numberFormatter = new NumberFormatter(static::$currencyLocale, NumberFormatter::CURRENCY);
        $moneyFormatter = new IntlMoneyFormatter($numberFormatter, new ISOCurrencies());

        return $moneyFormatter->format($money);
    }

    /**
     * Configure Cashier to not register its migrations.
     *
     * @return static
     */
    public static function ignoreMigrations()
    {
        static::$runsMigrations = false;

        return new static;
    }
}

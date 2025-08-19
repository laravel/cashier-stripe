<?php

namespace Laravel\Cashier;

use Illuminate\Database\Eloquent\SoftDeletes;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Formatter\IntlMoneyFormatter;
use Money\Money;
use NumberFormatter;
use Stripe\BaseStripeClient;
use Stripe\Customer as StripeCustomer;
use Stripe\StripeClient;
use Stripe\Util\ApiVersion as StripeApiVersion;

class Cashier
{
    /**
     * The Cashier library version.
     *
     * @var string
     */
    const VERSION = '16.0.0';

    /**
     * The Stripe API version.
     *
     * @var string
     */
    const STRIPE_VERSION = StripeApiVersion::CURRENT;

    /**
     * The base URL for the Stripe API.
     *
     * @var string
     */
    public static $apiBaseUrl = BaseStripeClient::DEFAULT_API_BASE;

    /**
     * The custom currency formatter.
     *
     * @var callable
     */
    protected static $formatCurrencyUsing;

    /**
     * Indicates if Cashier routes will be registered.
     *
     * @var bool
     */
    public static $registersRoutes = true;

    /**
     * Indicates if Cashier will mark past due subscriptions as inactive.
     *
     * @var bool
     */
    public static $deactivatePastDue = true;

    /**
     * Indicates if Cashier will mark incomplete subscriptions as inactive.
     *
     * @var bool
     */
    public static $deactivateIncomplete = true;

    /**
     * Indicates if Cashier will automatically calculate taxes using Stripe Tax.
     *
     * @var bool
     */
    public static $calculatesTaxes = false;

    /**
     * The default customer model class name.
     *
     * @var string
     */
    public static $customerModel = 'App\\Models\\User';

    /**
     * The subscription model class name.
     *
     * @var string
     */
    public static $subscriptionModel = Subscription::class;

    /**
     * The subscription item model class name.
     *
     * @var string
     */
    public static $subscriptionItemModel = SubscriptionItem::class;

    /**
     * Get the customer instance by its Stripe ID.
     *
     * @param  \Stripe\Customer|string|null  $stripeId
     * @return \Laravel\Cashier\Billable|null
     */
    public static function findBillable(StripeCustomer|string|null $stripeId)
    {
        $stripeId = $stripeId instanceof StripeCustomer ? $stripeId->id : $stripeId;

        $model = static::$customerModel;

        $builder = in_array(SoftDeletes::class, class_uses_recursive($model))
            ? $model::withTrashed()
            : new $model;

        return $stripeId ? $builder->where('stripe_id', $stripeId)->first() : null;
    }

    /**
     * Get the Stripe SDK client.
     *
     * @param  array  $options
     * @return \Stripe\StripeClient
     */
    public static function stripe(array $options = [])
    {
        $config = array_merge([
            'api_key' => $options['api_key'] ?? config('cashier.secret'),
            'stripe_version' => static::STRIPE_VERSION,
            'api_base' => static::$apiBaseUrl,
        ], $options);

        return app(StripeClient::class, ['config' => $config]);
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
     * @param  string|null  $locale
     * @param  array  $options
     * @return string
     */
    public static function formatAmount(int $amount, ?string $currency = null, ?string $locale = null, array $options = []): string
    {
        if (static::$formatCurrencyUsing) {
            return call_user_func(static::$formatCurrencyUsing, $amount, $currency, $locale, $options);
        }

        $money = new Money($amount, new Currency(strtoupper($currency ?? config('cashier.currency'))));

        $locale = $locale ?? config('cashier.currency_locale');

        $numberFormatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);

        if (isset($options['min_fraction_digits'])) {
            $numberFormatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $options['min_fraction_digits']);
        }

        $moneyFormatter = new IntlMoneyFormatter($numberFormatter, new ISOCurrencies());

        return $moneyFormatter->format($money);
    }

    /**
     * Configure Cashier to not register its routes.
     *
     * @return static
     */
    public static function ignoreRoutes()
    {
        static::$registersRoutes = false;

        return new static;
    }

    /**
     * Configure Cashier to maintain past due subscriptions as active.
     *
     * @return static
     */
    public static function keepPastDueSubscriptionsActive()
    {
        static::$deactivatePastDue = false;

        return new static;
    }

    /**
     * Configure Cashier to maintain incomplete subscriptions as active.
     *
     * @return static
     */
    public static function keepIncompleteSubscriptionsActive()
    {
        static::$deactivateIncomplete = false;

        return new static;
    }

    /**
     * Configure Cashier to automatically calculate taxes using Stripe Tax.
     *
     * @return static
     */
    public static function calculateTaxes()
    {
        static::$calculatesTaxes = true;

        return new static;
    }

    /**
     * Set the customer model class name.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $customerModel
     * @return void
     */
    public static function useCustomerModel(string $customerModel): void
    {
        static::$customerModel = $customerModel;
    }

    /**
     * Set the subscription model class name.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $subscriptionModel
     * @return void
     */
    public static function useSubscriptionModel(string $subscriptionModel): void
    {
        static::$subscriptionModel = $subscriptionModel;
    }

    /**
     * Set the subscription item model class name.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $subscriptionItemModel
     * @return void
     */
    public static function useSubscriptionItemModel(string $subscriptionItemModel): void
    {
        static::$subscriptionItemModel = $subscriptionItemModel;
    }
}

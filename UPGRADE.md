# Upgrade Guide

## Upgrading To 13.0 From 12.x

### Minimum Versions

The following required dependency versions have been updated:

- The minimum PHP version is now v7.3
- The minimum Laravel version is now v8.0
- The minimum Stripe SDK version is now v7.39

### Stripe API Version

PR: https://github.com/laravel/cashier-stripe/pull/905

The default Stripe API version for Cashier 13.x will be `2020-08-27`. If this is the latest Stripe API version at the time that you're upgrading to this Cashier version then it's also recommended that you upgrade your own Stripe API version settings [in your Stripe dashboard](https://dashboard.stripe.com/developers) to this version after deploying the Cashier upgrade. If this is no longer the latest Stripe API version, we recommend you do not modify your Stripe API version settings.

If you use the Stripe SDK directly, make sure to properly test your integration after updating.

#### Upgrading Your Webhook

You should ensure your webhook operates on the same API version as Cashier. To do so, you may use the `cashier:webhook` command from your production environment to create a new webhook that matches Cashier's Stripe API version:

```bash
php artisan cashier:webhook --disabled
```

This will create a new webhook with the same Stripe API version as Cashier [in your Stripe dashboard](https://dashboard.stripe.com/webhooks). The webhook will be immediately disabled so it doesn't interfere with your existing production application until you are ready to enable it. By default, the webhook will be created using the `APP_URL` environment variable to determine the proper URL for your application. If you need to use a different URL, you can use the `--url` flag when invoking the command:

```bash
php artisan cashier:webhook --disabled --url "http://example.com/stripe/webhook"
```

You may use the following upgrade checklist to properly enable to the new webhook:

1. If you have webhook signature verification enabled, disable it on production by temporarily removing the `STRIPE_WEBHOOK_SECRET` environment variable.
2. Add any extra Stripe events your application requires to the new webhook in your Stripe dashboard.
3. Disable the old webhook in your Stripe dashboard.
4. Enable the new webhook in your Stripe dashboard.
5. Re-enable the new webhook secret by re-adding the `STRIPE_WEBHOOK_SECRET` environment variable in production with the secret from the new webhook.
6. Remove the old webhook in your Stripe dashboard.

After following this process, your new webhook will be active and ready to receive events.

#### Tax Percentage Removal

Due to upgrading to a new Stripe API version, the `taxPercentage`, `syncTaxPercentage`, and `getTaxPercentageForPayload` methods have been removed from Cashier since they are deprecated by Stripe. We recommend that you upgrade to Stripe's new Tax Rates API. You can familiarize yourself with Tax Rates via Stripe's documentation on the topic:

Stripe migration guide: https://stripe.com/docs/billing/migration/taxes  
Tax Rates documentation: https://stripe.com/docs/billing/taxes/tax-rates  
Tax Rates on invoices: https://stripe.com/docs/billing/invoices/tax-rates  

Using Tax Rates with Laravel Cashier is also documented within the official Cashier documentation: https://laravel.com/docs/billing#subscription-taxes

### Renaming "Plans" To Prices

PR: https://github.com/laravel/cashier-stripe/pull/1166

To accommodate Stripe's phasing out of the "Plans" API, we've made the choice to completely migrate Cashier's public API to use the new "Prices" terminology. Doing so will bring us on par with Stripe's own documentation and will ease the translation between Stripe's documentation and Cashier's documentation and API.

The following methods were renamed:

```php
// Before...
$user->subscribedToPlan('price_xxx');
$user->onPlan('price_xxx');
$user->planTaxRates();

$user->newSubscription('default')->plan('price_xxx')->create($paymentMethod);
$user->newSubscription('default')->meteredPlan('price_xxx')->create($paymentMethod);

$user->subscription()->hasMultiplePlans();
$user->subscription()->hasSinglePlan();
$user->subscription()->hasPlan('price_xxx');
$user->subscription()->addPlan('price_xxx');
$user->subscription()->addPlanAndInvoice('price_xxx');
$user->subscription()->removePlan('price_xxx');

// After...
$user->subscribedToPrice('price_xxx');
$user->onPrice('price_xxx');
$user->priceTaxRates();

$user->newSubscription('default')->price('price_xxx')->create($paymentMethod);
$user->newSubscription('default')->meteredPrice('price_xxx')->create($paymentMethod);

$user->subscription()->hasMultiplePrices();
$user->subscription()->hasSinglePrice();
$user->subscription()->hasPrice('price_xxx');
$user->subscription()->addPrice('price_xxx');
$user->subscription()->addPriceAndInvoice('price_xxx');
$user->subscription()->removePrice('price_xxx');
```

All changes are mostly method renames.

Additionally, to fully migrate away from the "Plans" terminology, the `stripe_plan` columns on the `subscriptions` and `subscription_items` tables have been renamed to `stripe_price`.  You will need to write a migration to rename these columns:

```php
Schema::table('subscriptions', function (Blueprint $table) {
    $table->renameColumn('stripe_plan', 'stripe_price');
});

Schema::table('subscription_items', function (Blueprint $table) {
    $table->renameColumn('stripe_plan', 'stripe_price');
});
```

Running this migration requires you to [install the `doctrine/dbal` package](https://laravel.com/docs/migrations#renaming-columns).

### StripeClient Introduction

PR: https://github.com/laravel/cashier-stripe/pull/1169

Cashier now uses the new `StripeClient` object to do all API requests. This shouldn't have an impact on your application. However, improper usage of Stripe's API may cause exceptions as the new Stripe client is more strict than the previous resource based API calls.

Additionally, if you were overwriting the `stripeOptions` method in your billable model, you should now overwrite the `stripe` method instead.

### Billable Model Customization Changes

PR: https://github.com/laravel/cashier-stripe/pull/1100

The `cashier.model` configuration option has been removed from Cashier. Instead, you should use the `Cashier::useCustomerModel($customerModel)` method. Typically, this method should be called in the `boot` method of your application's `AppServiceProvider` class:

```php
use App\Models\Cashier\User;
use Laravel\Cashier\Cashier;

/**
 * Bootstrap any application services.
 *
 * @return void
 */
public function boot()
{
    Cashier::useCustomerModel(User::class);
}
```

### Stripe Sources Support Removed

PR: https://github.com/laravel/cashier-stripe/pull/1077

All support for the deprecated Stripe Sources API has been removed from Cashier. If you haven't already, we recommend that you upgrade to [the Payment Methods API](https://stripe.com/docs/payments/payment-methods).

This also means that the `defaultPaymentMethod` method no longer returns the `default_source` of a customer. 

### New Payment Methods Support

PR: https://github.com/laravel/cashier-stripe/pull/1074

Cashier v13 supports new payment methods. Because of this, the `card` columns in the database have been renamed to accommodate for all types of payment methods. You will need to write a migration to rename the billable model table's `card_brand` and `card_last_four` columns accordingly:

```php
Schema::table('users', function (Blueprint $table) {
    $table->renameColumn('card_brand', 'pm_type');
    $table->renameColumn('card_last_four', 'pm_last_four');
});
```

Running this migration requires you to [install the `doctrine/dbal` package](https://laravel.com/docs/migrations#renaming-columns).

### Simplified Payment Exceptions

PR: https://github.com/laravel/cashier-stripe/pull/1095

Payment exceptions thrown by Cashier on payment failures have been consolidated to a single `Laravel\Cashier\Exceptions\IncompletePayment` exception. Before, you could catch an exception based on the status of the payment intent object, such as `PaymentActionRequired` or `PaymentFailure`. However, because this status is already easily derived from the encapsulated payment intent object, we have simplified to just a single exception.

You can now derive the specific payment status by inspecting the `payment` property on the exception instance:

```php
use Laravel\Cashier\Exceptions\IncompletePayment;

try {
    $user->charge(1000, 'pm_card_threeDSecure2Required');
} catch (IncompletePayment $exception) {
    // Get the payment intent status...
    $exception->payment->status;
    
    // Check specific conditions...
    if ($exception->payment->requiresPaymentMethod()) {
        // ...
    } elseif ($exception->payment->requiresConfirmation()) {
        // ...
    }
}
```

### Cashier Receipt Changes

PR: https://github.com/laravel/cashier-stripe/pull/1136

Cashier receipts have been updated with additional information from the Stripe Invoice object. These updates primarily add more information, such as customer names and email addresses, to the receipt if the information was being stored in Stripe.

If you do not wish to receive these design updates, you should publish the receipt view *before* you update to Cashier v13:

```bash
php artisan vendor:publish --tag="cashier-views"
```

Please note that this command will also publish the`checkout.blade.php` and `payment.blade.php` templates. If you do not plan on customizing these published templates, you may delete them.

If you do decide to publish the `receipt.blade.php` template before updating Cashier, you should replace the "Discount" block within the template with the following changes that were introduced to the template:

https://github.com/laravel/cashier-stripe/blob/463c5c1c7a37e8748f5f5b5b1d54bd03c8f8137a/resources/views/receipt.blade.php#L249-L266

### Invoice Object Changes

PR: https://github.com/laravel/cashier-stripe/pull/1147

Due to introducing support for displaying multiple discounts on receipts, some substantial changes were made to the `Invoice` class. Normally, these changed methods were only used in the `receipt.blade.php` template. If you weren't using these methods directly and had not published the `receipt.blade.php` template, these changes should not affect your application. If you were using the changed methods directly or have previously exported the `receipt.blade.php` template, we recommend you review the PR linked above to determine any changes relevant to your application.

### Payment Exception Throwing

PR: https://github.com/laravel/cashier-stripe/pull/1155

A previous discrepancy between swapping subscriptions and increasing subscription quantities has been fixed. Previously, only the swapping of subscriptions would throw an `IncompletePayment` exception when a payment method failure occurred. This has been fixed so any use of the quantity methods on a subscription or subscription item will also throw this exception when a payment failure occurs.

PR: https://github.com/laravel/cashier-stripe/pull/1157

Additionally, swapping prices on subscription items will now also throw an `IncompletePayment` exception when a payment method failure occurs.

### Payment Page Updates

PR: https://github.com/laravel/cashier-stripe/pull/1120

The hosted payment page for handling payment method failures has been improved to provide support for additional payment methods. No changes to your application are required if you have not published the `payment.blade.php` template. However, all translation support has been removed. If you were relying on this functionality you should publish the view and re-add the appropriate calls to Laravel's translation services.

### Stripe Product Support

PR: https://github.com/laravel/cashier-stripe/pull/1185

Cashier Stripe v13 comes with support for checking Stripe Product identifiers. To provide support for this feature, a new `stripe_product` column should be added to the `subscription_items` table:

```php
Schema::table('subscription_items', function (Blueprint $table) {
    $table->string('stripe_product')->nullable()->after('stripe_id');
});
```

If you'd like to make use of the new `onProduct` & `subscribedToProduct` methods on your billable model, you should ensure the records in the `subscription_items` have their `stripe_product` column filled with the correct Product ID from Stripe.

## Upgrading To 12.8 From 12.7

### Metered Billing

Cashier v12.8.0 brings support for Metered Billing. In order to allow metered billing to work in your current Cashier Stripe application, you will need to write a migration to update the `subscription_items` table's `quantity` column to be nullable:

```php
Schema::table('subscription_items', function (Blueprint $table) {
    $table->integer('quantity')->nullable()->change();
});
```

Running this migration requires you to [install the `doctrine/dbal` package](https://laravel.com/docs/migrations#modifying-columns).


## Upgrading To 12.0 From 11.x

### Proration Changes

PR: https://github.com/laravel/cashier-stripe/pull/949

Cashier's proration features have been updated to make use of [all the new proration options](https://stripe.com/docs/api/subscriptions/update#update_subscription-proration_behavior) provided by Stripe. Previously, calling the `noProrate` method and calling any `xAndInvoice` method afterwards would not typically generate a new invoice. However, in Cashier 12.x, the `xAndInvoice` method will always generate a new invoice.

Although all other Cashier behavior should remain the same, there might be a slight difference in behavior if you were specifically relying on the invoice to be explicitly generated as a separate HTTP request through the invoice endpoint when using any `xAndInvoice` method. This is now accomplished in a single request using the `always_invoice` proration option. Of course, you will likely want to test your entire billing flow before deploying to production to make sure your application behaves as expected.

All underlying proration logic has been updated to accommodate for the new proration logic. If you were relying directly on the `$prorate` property, this has been renamed to `$prorationBehavior`. Similarly, the `setProrate` method has been renamed to `setProrationBehavior`.


## Upgrading To 11.0 From 10.x

### Minimum Versions

The following required dependency versions have been updated:

- The minimum PHP version is now v7.2
- The minimum Laravel version is now v6.0
- The minimum Stripe SDK version is now v7.0

### Stripe API Version

PR: https://github.com/laravel/cashier-stripe/pull/905

The Stripe API version for Cashier 11.x will be `2020-03-02`. Even though Cashier uses this version, it's recommended that you upgrade your own settings [in your Stripe dashboard](https://dashboard.stripe.com/developers) to this API version as well after deploying the Cashier upgrade. If you use the Stripe SDK directly, make sure to properly test your integration after updating.

### Multiplan Subscriptions

PR: https://github.com/laravel/cashier-stripe/pull/900

With Cashier 11.x, multiple plans per subscription is now supported. To support this, the `stripe_plan` and `quantity` attributes on the `Subscription` model may now be `null`. This will occur only when a subscription has multiple plans. To accommodate these changes, please execute the following migration against your database:

```php
Schema::table('subscriptions', function (Blueprint $table) {
     $table->string('stripe_plan')->nullable()->change();
     $table->integer('quantity')->nullable()->change();
});
```

If you have disabled Cashier's migrations then you should also manually create a new migration to add a table for subscription items:

```php
Schema::create('subscription_items', function (Blueprint $table) {
    $table->bigIncrements('id');
    $table->unsignedBigInteger('subscription_id');
    $table->string('stripe_id')->index();
    $table->string('stripe_plan');
    $table->integer('quantity');
    $table->timestamps();

    $table->unique(['subscription_id', 'stripe_plan']);
});
```

If you need to access the subscription's plans and their respective quantities you may do using the new `items` relationship available on the subscription:

```php
foreach ($subscription->items as $item) {
    $item->stripe_plan;
    $item->quantity;
}
```

For more information on subscriptions with multiple plans, please consult the [full Cashier documentation](https://laravel.com/docs/billing) available on the Laravel website.

### Tax Rates Support

PR: https://github.com/laravel/cashier-stripe/pull/830

Cashier 11.x includes support for Stripe's "Tax Rates" services. Several changes have been made to Cashier to support this new feature.

First, instead of defining a default tax percentage on the `Billable` model, an array of Tax Rate IDs must be returned. If you were overriding the `taxPercentage` method you should rename it to `taxRates`. Instead of returning a percentage you'll need to return an array containing Stripe ID of a Tax Rate that you define [in your Stripe Dashboard](https://dashboard.stripe.com/tax-rates).

Secondly, the `syncTaxPercentage` method has been renamed to `syncTaxRates` which, when using multiplan subscriptions, will also sync tax rates for any subscription items of the subscription.

Thirdly, the `InvoiceItem` class has been renamed to `InvoiceLineItem` which better represents what it actually is and is consistent with Stripe's own terminology. Several methods have also been renamed to better reflect this.

Lastly, the [`receipt.blade.php`](https://github.com/laravel/cashier-stripe/blob/11.x/resources/views/receipt.blade.php) view has been thoroughly updated. If you have previously exported this view we recommend that you export it again to receive these updates:

    php artisan vendor:publish --tag="cashier-views" --force

We also recommended that you familiarize yourself with Stripe's guides on Tax Rates:

Stripe migration guide: https://stripe.com/docs/billing/migration/taxes
Tax Rates documentation: https://stripe.com/docs/billing/taxes/tax-rates
Tax Rates on invoices: https://stripe.com/docs/billing/invoices/tax-rates

### hasPaymentMethod Changes

PR: https://github.com/laravel/cashier-stripe/pull/838

The `hasPaymentMethod` method previously returned `true` or `false` when the customer had a default payment method set. A new `hasDefaultPaymentMethod` method has been created for this purpose, while the `hasPaymentMethod` method will now return `true` or `false` when the customer has at least one payment method set.

### Loosened Exception Throwing

PR: https://github.com/laravel/cashier-stripe/pull/882

Previously, when a user wasn't yet a Stripe customer, the `upcomingInvoice`, `invoices`, and `paymentMethods` methods would throw an `InvalidStripeCustomer` exception. This has been adjusted so these methods return an empty collection for `invoices` and `paymentMethods`, and `null` for `upcomingInvoice`. An exception will no longer be thrown if the user is not a Stripe customer.

### Renamed Exceptions

PR: https://github.com/laravel/cashier-stripe/pull/881

The exception `Laravel\Cashier\Exceptions\InvalidStripeCustomer` has been split up into two new exceptions: `Laravel\Cashier\Exceptions\CustomerAlreadyCreated` and `Laravel\Cashier\Exceptions\InvalidCustomer`. The `createAsStripeCustomer` method will now throw the new `CustomerAlreadyCreated` exception while old usages of `InvalidStripeCustomer` are replaced by `InvalidCustomer`.

### Invoice Numbers

PR: https://github.com/laravel/cashier-stripe/pull/878

Previously, in the default `receipt.blade.php` view, Cashier made use of the Stripe identifier of an invoice for an invoice number. This has been corrected to the proper `$invoice->number` attribute.


## Upgrading To 10.0 From 9.x

Cashier 10.0 is a major release that provides support for new Stripe APIs as well as provide compliance with SCA regulations in Europe that begin September 2019. If you have a business in the EU, we recommend you review [Stripe's guide on PSD2 and SCA](https://stripe.com/guides/strong-customer-authentication) as well as [their documentation on the SCA API's](https://stripe.com/docs/strong-customer-authentication).

In this upgrade guide we'll try to cover as much as possible. Please read it thoroughly and also review the corresponding pull requests. Note that some code in the referenced pull requests may have been updated later by additional patches during the beta release process.

> If you would like to review all changes, review the code diff between the 9.0 branch and the 10.0 release: https://github.com/laravel/cashier-stripe/compare/9.0...v10.0.0

### Minimum Versions

The following dependencies were bumped to new minimum versions:

- The minimum Laravel version is now v5.8
- The minimum Symfony dependencies are now v4.3
- The minimum Stripe SDK version is now v6.40
- The minimum Carbon version is now v2.0

### Fixed API Version

PR: https://github.com/laravel/cashier-stripe/pull/643

The Stripe API version is now fixed by Cashier. By controlling the API version within Cashier, we can more easily prevent bugs due to API drift and update to new API versions gradually.

### Publishable Key / Secret Key

PR: https://github.com/laravel/cashier-stripe/pull/653

The `STRIPE_KEY` environment variable is now always used as the publishable key and the `STRIPE_SECRET` environment variable is always used as the secret key.

### Migrations

PR: https://github.com/laravel/cashier-stripe/pull/663

Just like in other Laravel packages, Cashier's migrations now ship with the package. These migrations are automatically registered and will be executed when you run `php artisan migrate`. If you have already run these migrations and want to disable additional migration being executed by Cashier, call `Cashier::ignoreMigrations();` from the `register` method in your `AppServiceProvider`.

<a name="configuration-file"></a>
### Configuration File

PR: https://github.com/laravel/cashier-stripe/pull/690

Cashier now ships with a dedicated configuration file like many other first-party Laravel packages. Settings that were previously stored in the `services.php` configuration file have been transferred to the new `cashier` configuration file. In addition, many methods from the `Cashier` class have been created as configuration options within this file.

The `STRIPE_MODEL` environment variable has been renamed to `CASHIER_MODEL`.

### Payment Intents Support

PR: https://github.com/laravel/cashier-stripe/pull/667

#### New Exceptions

Any payment action will now throw an exception when a payment either fails or when the payment requires a secondary confirmation action in order to be completed. This applies to single charges, invoicing customers, subscribing to a new plan, or swapping plans. After catching these exceptions, you have several options for how to properly handle them. You can either let Stripe handle everything for you (you may configure this in the Stripe dashboard) or use the new, built-in payment confirmation page that is included with Cashier.

```php
use Laravel\Cashier\Exceptions\IncompletePayment;

try {
    $subscription = $user->newSubscription('default', $planId)
        ->create($paymentMethod);
} catch (IncompletePayment $exception) {
    return redirect()->route(
        'cashier.payment',
        [$exception->payment->id, 'redirect' => route('home')]
    );
}
```

The `IncompletePayment` exception above could be an instance of a `PaymentFailure` when a card failure occurred or an instance of `PaymentActionRequired` when a secondary confirmation action is needed to complete the payment. In the example above, the user is redirected to a new, dedicated payment page which ships with Cashier. Here, the user can confirm their payment details and fulfill the secondary action (such as 3D Secure). After confirming their payment, the user will be redirected to the URL provided in the `redirect` route parameter.

Exceptions may be thrown for the following methods: `charge`, `invoiceFor`, and `invoice` on the `Billable` user. When handling subscriptions, the `create` method and `swap` methods may throw exceptions. The payment page provided by Cashier offers an easy transition to handling the new European SCA requirements.

> If you would like to let Stripe host your payment verification pages, [you may configure this in your Stripe settings](https://dashboard.stripe.com/account/billing/automatic). However, you should still handle payment exceptions in your application and inform the user they will receive an email with further payment confirmation instructions.

In addition, the subscription `create` method on the subscription builder previously immediately cancelled any subscription with an `incomplete` or `incomplete_expired` status and threw a `SubscriptionCreationFailed` exception when a subscription could not be created. This has been replaced with the behavior described above and the `SubscriptionCreationFailed` exception has been removed.

#### The Subscription `stripe_status` Column

A new `stripe_status` database column has been introduced for the `subscriptions` table, which corresponds with and is kept in sync via webhooks with the subscription status provided by Stripe. You can add this column to your `subscriptions` table using the migration below:

```php
Schema::table('subscriptions', function (Blueprint $table) {
    $table->string('stripe_status')->nullable();
});
```

When a subscription is put into an `incomplete_expired` state, Cashier will automatically delete it from the database. More information on subscription statuses can be found here: https://stripe.com/docs/billing/lifecycle

#### Past Due & Incomplete Subscriptions

PR: https://github.com/laravel/cashier-stripe/pull/707

During subscription operations that fail and require additional payment configuration, a subscription will receive a status of `incomplete` or `past_due`. When this happens, you will need to inform the user that their payment requires additional confirmation.

You can determine if a user needs to confirm a payment using the new `hasIncompletePayment` method provided by the `Billable` trait. If the user has an incomplete payment, you may use the `latestPayment` method on the `Subscription` model to retrieve the latest failed payment and redirect the user to Cashier's payment confirmation screen:

```blade
@if ($user->hasIncompletePayment())
    <a href="{{ route('cashier.payment', $user->subscription()->latestPayment()->id) }}">
        Please confirm your payment.
    </a>
@endif
```

> When a subscription is in an incomplete state, no plan changes can occur and an `SubscriptionUpdateFailure` exception will occur when you try to call the `swap` or `updateQuantity` methods.

#### Payment Notifications

> If you have enabled Stripe's built-in payment confirmation notifications then you do not need to configure the Cashier payment confirmation notifications.

Since SCA regulations require customers to occasionally verify their payment details even while their subscription is active, Cashier can send a payment notification to the customer when off-session payment confirmation is required. For example, this may occur when a subscription is renewing. Cashier's payment notification can be enabled by setting the `CASHIER_PAYMENT_NOTIFICATION` environment variable to a notification class. By default, this notification is disabled. Of course, Cashier includes a notification class you may use for this purpose, but you are free to provide your own notification class if desired:

    CASHIER_PAYMENT_NOTIFICATION=Laravel\Cashier\Notifications\ConfirmPayment

To ensure that off-session payment confirmation notifications are delivered, verify that [Stripe webhooks are configured](https://laravel.com/docs/billing#handling-stripe-webhooks) for your application and the `invoice.payment_action_required` webhook is enabled in your Stripe dashboard.

### Cards And Payment Methods

PR: https://github.com/laravel/cashier-stripe/pull/696
PR: https://github.com/laravel/cashier-stripe/pull/701

Cashier has migrated to the new recommended [Stripe Payment Methods API](https://stripe.com/docs/payments/payment-methods). This API effectively [replaces the former Sources and Tokens API](https://stripe.com/docs/payments/payment-methods#transitioning). At the moment, the Payment Methods API only supports cards but support for all of the other payment flows [is planned](https://stripe.com/docs/payments/payment-methods#transitioning).

Payment Methods are backwards compatible with the Sources and Tokens APIs, meaning that if you've saved cards as a source on a customer, they can be retrieved with the new Payment Methods API. However, at the moment there isn't a way to retrieve the default source from a customer through the Payment Methods API. Therefore, the `defaultPaymentMethod` method on the `Billable` user will return an instance of a `Stripe\Card` or `Stripe\BankAccount` if no default Payment Method could be found.

It's important to note that any default source set on a customer will still continue to work when creating new subscriptions. However, considering new SCA regulations which take affect September 2019, it's important that you update your integration to the new Payment Methods API as soon as possible and do not use the Sources or Tokens APIs any longer. In fact, if your users do not have a `Laravel\Cashier\PaymentMethod` attached to their account, you may wish to create one before creating a new subscription:

```php
use Stripe\Card as StripeCard;
use Stripe\BankAccount as StripeBankAccount;

$defaultPaymentMethod = $user->defaultPaymentMethod();

if ($defaultPaymentMethod instanceof StripeCard ||
    $defaultPaymentMethod instanceof StripeBankAccount) {
    // Gather payment method and store it using new payment method APIs...
}
```

Due to these changes, the `Laravel\Cashier\Card` class has been replaced with `Laravel\Cashier\PaymentMethod` class and the old card methods on the Billable trait were removed.

For more information regarding storing payment methods, review the [Setup Intents](#setup-intents) documentation below.

<a name="setup-intents"></a>
#### Setup Intents

PR: https://github.com/laravel/cashier-stripe/pull/700

When storing payment methods, you should now use the Stripe Setup Intent API if you want to ensure your off-session recurring payments for your subscription keep working and do not trigger secondary payment confirmation actions.

**To learn more about Setup Intents and creating payment methods for subscription billing or single charges, review the [full Cashier documentation](https://laravel.com/docs/billing/#payment-methods).**

### Single Charges

#### Payment Method

PR: https://github.com/laravel/cashier-stripe/pull/697

The `charge` method now requires a payment method identifier instead of a token. You will need to update your Stripe.js integration to retrieve a payment method identifier instead of a source token. "Payment methods" are now Stripe's recommended way of dealing with customer payment information. [More information about payments can be found in the official Stripe documentation](https://stripe.com/docs/payments/payment-intents/migration#web).

Because Stripe doesn't offer a way to set a default payment method for single charges, the charge method now explicitly requires a payment method identifier as its second parameter:

```php
$user->charge(1000, $paymentMethod);
```

**You can retrieve a payment method by implementing [the new payment method Stripe.js integration](https://laravel.com/docs/billing/#payment-methods).**

### Webhooks

PR: https://github.com/laravel/cashier-stripe/pull/672

Because of updates in Stripe's recommended payment and subscription handling, properly handling Stripe's webhooks is now required in order to use Cashier. Thankfully, Cashier provides a controller which will properly handle these webhooks for you. [You can read more about enabling webhooks in the full Cashier documentation](https://laravel.com/docs/billing#handling-stripe-webhooks).

The Cashier webhook handler route is now automatically registered for you and does not need to be manually added to your routes file anymore.

### Invoices

PR: https://github.com/laravel/cashier-stripe/pull/685
PR: https://github.com/laravel/cashier-stripe/pull/711
PR: https://github.com/laravel/cashier-stripe/pull/690

Cashier now uses the `moneyphp/money` library to format currency values for display on invoices. Because of this, the `useCurrencySymbol`, `usesCurrencySymbol` and `guessCurrencySymbol` methods have been removed.

The `useCurrency` method has been replaced by a configuration option in the new [Cashier configuration file](#configuration-file) and the `usesCurrency` method has been removed.

In addition, all `raw` methods on the `Invoice` object now return integers instead of floats. These integers represent money values in cents.

#### Invoice Object

All invoice methods in Cashier now return an instance of `Laravel\Cashier\Invoice` instead of a `Stripe\Invoice` object.

### Subscriptions

#### Swap Options

PR: https://github.com/laravel/cashier-stripe/pull/620

The `swap` method now accepts an `$options` argument.

#### Swapping And Invoicing

PR: https://github.com/laravel/cashier-stripe/pull/710

The `swap` method will no longer automatically invoice the customer. Instead, a dedicated `swapAndInvoice` method has been added to Cashier. The `swapAndInvoice` method may be used if you want to immediately invoice a customer when they change their plan.

### Customers

PR: https://github.com/laravel/cashier-stripe/pull/682

The following methods now require that the `Billable` user has an associated Stripe customer account created by your application: `tab`, `invoice`, `upcomingInvoice`, `invoices`, `applyCoupon`. An exception will be thrown if you attempt to call these methods without first creating a Stripe customer account for the user.


## Upgrading To 9.3 From 9.2

### Custom Subscription Creation Exception

[In their 2019-03-14 API update](https://stripe.com/docs/upgrades#2019-03-14), Stripe changed the way they handle new subscriptions when card payment fails. Instead of letting the creation of the subscription fail, the subscription is failed with an "incomplete" status. Because of this a Cashier customer will always get a successful subscription. Previously a card exception was thrown.

To accommodate for this new behavior from now on Cashier will cancel that subscription immediately and throw a custom `SubscriptionCreationFailed` exception when a subscription is created with an "incomplete" or "incomplete_expired" status. We've decided to do this because in general you want to let a customer only start using your product when payment was received.

If you were relying on catching the `\Stripe\Error\Card` exception before you should now rely on catching the `Laravel\Cashier\Exceptions\SubscriptionCreationFailed` exception instead.

### Card Failure When Swapping Plans

Previously, when a user attempted to change subscription plans and their payment failed, the resulting exception bubbled up to the end user and the update to the subscription in the application was not performed. However, the subscription was still updated in Stripe itself resulting in the application and Stripe becoming out of sync.

However, Cashier will now catch the payment failure exception while allowing the plan swap to continue. The payment failure will be handled by Stripe and Stripe may attempt to retry the payment at a later time. If the payment fails during the final retry attempt, Stripe will execute the action you have configured in your billing settings: https://stripe.com/docs/billing/lifecycle#settings

Therefore, you should ensure you have configured Cashier to handle Stripe's webhooks. When configured properly, this will allow Cashier to mark the subscription as cancelled when the final payment retry attempt fails and Stripe notifies your application via a webhook request. Please refer to our [instructions for setting up Stripe webhooks with Cashier.](https://laravel.com/docs/master/billing#handling-stripe-webhooks).


## Upgrading To 9.0 From 8.x

### PHP & Laravel Version Requirements

Like the latest releases of the Laravel framework, Laravel Cashier now requires PHP >= 7.1.3. We encourage you to upgrade to the latest versions of PHP and Laravel before upgrading to Cashier 9.0.

### The `createAsStripeCustomer` Method

The `updateCard` call was extracted from the `createAsStripeCustomer` method on the `Billable` trait in PR [#588](https://github.com/laravel/cashier-stripe/pull/588). In addition, the `$token` parameter was removed.

If you were calling the `createAsStripeCustomer` method directly you now should call the `updateCard` method separately after calling the `createAsStripeCustomer` method. This provides the opportunity for more granularity when handling errors for the two calls.

### WebhookController Changes

Instead of calling the Stripe API to verify incoming webhook events, Cashier now only uses webhook signatures to verify that events it receives are authentic as of [PR #591](https://github.com/laravel/cashier-stripe/pull/591).

The `VerifyWebhookSignature` middleware is now automatically added to the `WebhookController` if the `services.stripe.webhook.secret` value is set in your `services.php` configuration file. By default, this configuration value uses the `STRIPE_WEBHOOK_SECRET` environment variable.

If you manually added the `VerifyWebhookSignature` middleware to your Cashier webhook route, you may remove it since it will now be added automatically.

If you were using the `CASHIER_ENV` environment variable to test incoming webhooks, you should set the `STRIPE_WEBHOOK_SECRET` environment variable to `null` to achieve the same behavior.

More information about verifying webhooks can be found [in the Cashier documentation](https://laravel.com/docs/5.7/billing#verifying-webhook-signatures).

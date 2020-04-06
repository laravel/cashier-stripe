# Upgrade Guide

## Upgrading To 11.0 From 10.0

### Minimum Versions

The following dependencies were bumped to new minimum versions:

- The minimum PHP version is now v7.2
- The minimum Laravel version is now v6.0
- The minimum Stripe SDK version is now v7.0

### Stripe API Version

PR: https://github.com/laravel/cashier/pull/905

The Stripe API version for v11 will be `2020-03-02`. Even though Cashier uses this version, it's recommended that you upgrade your own settings [in your Stripe dashboard](https://dashboard.stripe.com/developers) to this API version as well after deploying the Cashier upgrade. If you use the Stripe SDK directly, make sure to properly test your integration after updating.

## Multiplan Subscriptions

PR: https://github.com/laravel/cashier/pull/900

With Cashier v11, multiplan subscription support has finally landed. With this addition, you should keep in mind that from now on the `stripe_plan` and `quantity` attributes on the `Subscription` model can be `null`. This is only the case when a subscription has multiple plans. If you need to determine the subscription's plans and quantities you can reference the individual items:

```php
foreach ($subscription->items as $item) {
    $item->stripe_plan;
    $item->quantity;
}
```

You should make sure that the `stripe_plan` and `quantity` columns on subscriptions are nullable fields. You can do so with this migration: 

```php
Schema::table('subscriptions', function (Blueprint $table) {
     $table->string('stripe_plan')->nullable()->change();
     $table->integer('quantity')->nullable()->change();
});
```

And if you've disabled Cashier's migrations then you should also manually add a new table for the new subscription items:

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

## Tax Rates Support

PR: https://github.com/laravel/cashier/pull/830

Cashier v11 ships with support for Stripe's Tax Rates. This brings along several changes.

First of all, instead of defining a default tax percentage on the Billable model, an array of Tax Rate ids needs to be returned. If you were overriding the `taxPercentage` method you need to rename it to `taxRates`. Instead of returning a percentage you'll need to return an array with a an Stripe ID of a Tax Rate that you define [in your Stripe Dashboard](https://dashboard.stripe.com/tax-rates).

Secondly, the `syncTaxPercentage` method has been renamed to `syncTaxRates` which will now also sync tax rates for any subscription items of the subscription.

Thirdly, the `InvoiceItem` class has been renamed to `InvoiceLineItem` which better represents what it actually is and is the same naming that Stripe uses. Several methods have also been renamed to better reflect this.

Lastly, the [`receipt.blade.php`](https://github.com/laravel/cashier/blob/11.x/resources/views/receipt.blade.php) view has gotten a thorough update. If you exported the view it's best that you do so again:

    php artisan vendor:publish --tag="cashier-views" --force

It is also highly recommended that you read up on Stripe's guides on Tax Rates:

Stripe migration guide: https://stripe.com/docs/billing/migration/taxes
Tax Rates documentation: https://stripe.com/docs/billing/taxes/tax-rates
Tax Rates on invoices: https://stripe.com/docs/billing/invoices/tax-rates

## `hasPaymentMethod` Changes

PR: https://github.com/laravel/cashier/pull/838

The method `hasPaymentMethod` previously returned `true` or `false` when the customer had a default payment method set. Instead, a dedicated `hasDefaultPaymentMethod` method has been added for this. The old `hasPaymentMethod` method will now return `true` or `false` when the customer has at least one payment method set.

## Loosened Exception Throwing

PR: https://github.com/laravel/cashier/pull/882

Previously, when a customer wasn't a Stripe customer yet, the methods `upcomingInvoice`, `invoices` and `paymentMethods` would throw an `InvalidStripeCustomer` exception. This has been adjusted so these methods return an empty collection for `invoices` and `paymentMethods` and `null` for `upcomingInvoice` without the need for creating a Stripe customer first.

## Renamed Exceptions

The exception `Laravel\Cashier\Exceptions\InvalidStripeCustomer` has been split up into two new ones: `Laravel\Cashier\Exceptions\CustomerAlreadyCreated` and `Laravel\Cashier\Exceptions\InvalidCustomer`. The `createAsStripeCustomer` method will now throw the new `CustomerAlreadyCreated` exception while old usages of `InvalidStripeCustomer` are replaced by `InvalidCustomer`.


## Upgrading To 10.0 From 9.0

Cashier 10.0 is a major release that provides support for new Stripe APIs as well as provide compliance with SCA regulations in Europe that begin September 2019. If you have a business in the EU, we recommend you review [Stripe's guide on PSD2 and SCA](https://stripe.com/guides/strong-customer-authentication) as well as [their documentation on the SCA API's](https://stripe.com/docs/strong-customer-authentication).

In this upgrade guide we'll try to cover as much as possible. Please read it thoroughly and also review the corresponding pull requests. Note that some code in the referenced pull requests may have been updated later by additional patches during the beta release process.

> If you would like to review all changes, review the code diff between the 9.0 branch and the 10.0 release: https://github.com/laravel/cashier/compare/9.0...v10.0.0

### Minimum Versions

The following dependencies were bumped to new minimum versions:

- The minimum Laravel version is now v5.8
- The minimum Symfony dependencies are now v4.3
- The minimum Stripe SDK version is now v6.40
- The minimum Carbon version is now v2.0

### Fixed API Version

PR: https://github.com/laravel/cashier/pull/643

The Stripe API version is now fixed by Cashier. By controlling the API version within Cashier, we can more easily prevent bugs due to API drift and update to new API versions gradually.

### Publishable Key / Secret Key

PR: https://github.com/laravel/cashier/pull/653

The `STRIPE_KEY` environment variable is now always used as the publishable key and the `STRIPE_SECRET` environment variable is always used as the secret key.

### Migrations

PR: https://github.com/laravel/cashier/pull/663

Just like in other Laravel packages, Cashier's migrations now ship with the package. These migrations are automatically registered and will be executed when you run `php artisan migrate`. If you have already run these migrations and want to disable additional migration being executed by Cashier, call `Cashier::ignoreMigrations();` from the `register` method in your `AppServiceProvider`.

<a name="configuration-file"></a>
### Configuration File

PR: https://github.com/laravel/cashier/pull/690

Cashier now ships with a dedicated configuration file like many other first-party Laravel packages. Settings that were previously stored in the `services.php` configuration file have been transferred to the new `cashier` configuration file. In addition, many methods from the `Cashier` class have been created as configuration options within this file.

The `STRIPE_MODEL` environment variable has been renamed to `CASHIER_MODEL`.

### Payment Intents Support

PR: https://github.com/laravel/cashier/pull/667

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

PR: https://github.com/laravel/cashier/pull/707

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

PR: https://github.com/laravel/cashier/pull/696  
PR: https://github.com/laravel/cashier/pull/701

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

PR: https://github.com/laravel/cashier/pull/700

When storing payment methods, you should now use the Stripe Setup Intent API if you want to ensure your off-session recurring payments for your subscription keep working and do not trigger secondary payment confirmation actions.

**To learn more about Setup Intents and creating payment methods for subscription billing or single charges, review the [full Cashier documentation](https://laravel.com/docs/billing/#payment-methods).**

### Single Charges

#### Payment Method

PR: https://github.com/laravel/cashier/pull/697

The `charge` method now requires a payment method identifier instead of a token. You will need to update your Stripe.js integration to retrieve a payment method identifier instead of a source token. "Payment methods" are now Stripe's recommended way of dealing with customer payment information. [More information about payments can be found in the official Stripe documentation](https://stripe.com/docs/payments/payment-intents/migration#web).

Because Stripe doesn't offer a way to set a default payment method for single charges, the charge method now explicitly requires a payment method identifier as its second parameter:

```php
$user->charge(1000, $paymentMethod);
```

**You can retrieve a payment method by implementing [the new payment method Stripe.js integration](https://laravel.com/docs/billing/#payment-methods).**

### Webhooks

PR: https://github.com/laravel/cashier/pull/672

Because of updates in Stripe's recommended payment and subscription handling, properly handling Stripe's webhooks is now required in order to use Cashier. Thankfully, Cashier provides a controller which will properly handle these webhooks for you. [You can read more about enabling webhooks in the full Cashier documentation](https://laravel.com/docs/billing#handling-stripe-webhooks).

The Cashier webhook handler route is now automatically registered for you and does not need to be manually added to your routes file anymore.

### Invoices

PR: https://github.com/laravel/cashier/pull/685
PR: https://github.com/laravel/cashier/pull/711
PR: https://github.com/laravel/cashier/pull/690

Cashier now uses the `moneyphp/money` library to format currency values for display on invoices. Because of this, the `useCurrencySymbol`, `usesCurrencySymbol` and `guessCurrencySymbol` methods have been removed.

The `useCurrency` method has been replaced by a configuration option in the new [Cashier configuration file](#configuration-file) and the `usesCurrency` method has been removed.

In addition, all `raw` methods on the `Invoice` object now return integers instead of floats. These integers represent money values in cents.

#### Invoice Object

All invoice methods in Cashier now return an instance of `Laravel\Cashier\Invoice` instead of a `Stripe\Invoice` object.

### Subscriptions

#### Swap Options

PR: https://github.com/laravel/cashier/pull/620

The `swap` method now accepts an `$options` argument.

#### Swapping And Invoicing

PR: https://github.com/laravel/cashier/pull/710

The `swap` method will no longer automatically invoice the customer. Instead, a dedicated `swapAndInvoice` method has been added to Cashier. The `swapAndInvoice` method may be used if you want to immediately invoice a customer when they change their plan.

### Customers

PR: https://github.com/laravel/cashier/pull/682

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


## Upgrading To 9.0 From 8.0

### PHP & Laravel Version Requirements

Like the latest releases of the Laravel framework, Laravel Cashier now requires PHP >= 7.1.3. We encourage you to upgrade to the latest versions of PHP and Laravel before upgrading to Cashier 9.0.

### The `createAsStripeCustomer` Method

The `updateCard` call was extracted from the `createAsStripeCustomer` method on the `Billable` trait in PR [#588](https://github.com/laravel/cashier/pull/588). In addition, the `$token` parameter was removed.

If you were calling the `createAsStripeCustomer` method directly you now should call the `updateCard` method separately after calling the `createAsStripeCustomer` method. This provides the opportunity for more granularity when handling errors for the two calls.

### WebhookController Changes

Instead of calling the Stripe API to verify incoming webhook events, Cashier now only uses webhook signatures to verify that events it receives are authentic as of [PR #591](https://github.com/laravel/cashier/pull/591).

The `VerifyWebhookSignature` middleware is now automatically added to the `WebhookController` if the `services.stripe.webhook.secret` value is set in your `services.php` configuration file. By default, this configuration value uses the `STRIPE_WEBHOOK_SECRET` environment variable.

If you manually added the `VerifyWebhookSignature` middleware to your Cashier webhook route, you may remove it since it will now be added automatically.

If you were using the `CASHIER_ENV` environment variable to test incoming webhooks, you should set the `STRIPE_WEBHOOK_SECRET` environment variable to `null` to achieve the same behavior.

More information about verifying webhooks can be found [in the Cashier documentation](https://laravel.com/docs/5.7/billing#verifying-webhook-signatures).

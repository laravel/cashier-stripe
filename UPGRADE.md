# Upgrade Guide

## Upgrading To 10.0 From 9.0

Cashier 10.0 is a major release that provides support for new Stripe API's which cover new SCA regulations in Europe that begin during September 2019. If you have a business in the EU, we recommend you review [Stripe's guide on PSD2 and SCA](https://stripe.com/en-be/guides/strong-customer-authentication) as well as [their docs on the new SCA API's](https://stripe.com/docs/strong-customer-authentication).

In this upgrade guide we'll try to cover as much as possible. Please read it thoroughly and also review the mentioned pull requests. Note that some code in the referenced pull requests were updated later one by patches during the beta.

> If you would like to review all changes, review the code diff between the 9.0 branch and the 10.0 release: https://github.com/laravel/cashier/compare/9.0...v10.0.0

### Minimum Versions

The following libraries were bumped to new minimum versions:

- The minimum Laravel version is now v5.8
- The minimum Symfony dependencies are now v4.3
- The minimum Stripe SDK version is now v6.40
- The minimum Carbon version is now v2.0

### Fixed API Version

PR: https://github.com/laravel/cashier/pull/643

The Stripe API version is now fixed by Cashier. By controlling the API version within Cashier, we can more easily prevent bugs and make updates to newer API versions gradually.

### Publishable Key / Secret Key

PR: https://github.com/laravel/cashier/pull/653

The `STRIPE_KEY` is now always used as the publishable key and the `STRIPE_SECRET` is always used as the secret key.

### Migrations

PR: https://github.com/laravel/cashier/pull/663

Just like in other Laravel packages, Cashier's migrations now ship with its package. They're automatically registered and will be executed when you run `php artisan migrate`. If you already have these migrations run you would want to disable this by adding `Cashier::ignoreMigrations();` to the `register` method in your `AppServiceProvider`.

### Config File

PR: https://github.com/laravel/cashier/pull/690

Cashier now ships with a dedicated config file like many of the other Laravel packages. This means that previous settings from the `services.php` file are transferred to the new `cashier` config file. Many methods from the `Cashier` class have been transferred as settings to the config file.

The `STRIPE_MODEL` env variable has been renamed to `CASHIER_MODEL`.

### Payment Intents Support

PR: https://github.com/laravel/cashier/pull/667

#### New Exceptions

Any payment action will now throw an exception when a payment either fails or when the payment requires a secondary action in order to be completed. This applies to single charges, invoicing customers directly, subscribing to a new plan or swapping plans. You may catch these exceptions and decide for yourself how to handle them. You can either let Stripe handle everything for you (to be set up in the Stripe dashboard) or use the new custom, built-in payment confirmation page:

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

The `IncompletePayment` exception above could be an instance of a `PaymentFailure` when a card failure occurred or of `PaymentActionRequired` when a secondary action is needed to complete the payment. In the example above, the user is redirected to a new, dedicated payment page which ships with Cashier. Here, the user can provide its payment details again and fulfill the secondary action (such as 3D Secure). Then, they're redirected to the URL provided in the `redirect` parameter.

Exceptions can be thrown for the following methods: `charge`, `invoiceFor`, and `invoice` on the `Billable` user. When handling subscriptions, the `create` method and `swap` methods may throw exceptions. The payment page provided by Cashier offers an easy transition to handling the new SCA requirements. 

> If you would like to let Stripe host your payment verification pages, [you may configure this in your Stripe settings](https://dashboard.stripe.com/account/billing/automatic). However, you should still handle payment exceptions in your application and inform the user they will receive an email with further payment confirmation instructions.

In addition, the subscription `create` method on the subscription builder previously immediately canceled any subscription with an `incomplete` or `incomplete_expired` status and throw a `SubscriptionCreationFailed` exception when a subscription could not be created. This has been replaced with the behavior described above and the `SubscriptionCreationFailed` exception has been removed.

#### The Subscription `stripe_status` Column

A new `stripe_status` database column has been introduced for the `subscriptions` table. This will sync the status from within Stripe. This status column will be use the new `past_due` and `incomplete` statuses from Stripe within Cashier, amongst else. A webhook will be used to keep this column up to date. You can add this column to your `subscriptions` table using the migration below:

```php
Schema::table('subscriptions', function (Blueprint $table) {
    $table->string('stripe_status')->nullable();
});
```

When a subscription is put into an `incomplete_expired` state, it'll be deleted from the database. More info on subscription statuses can be found here: https://stripe.com/docs/billing/lifecycle

#### Past Due and Incomplete Subscriptions

PR: https://github.com/laravel/cashier/pull/707

As said above, when a subscription has been put into an incomplete or past due state, you'll need to inform the user inside your app that they need to confirm their payment. When first creating your subscription or swapping to a different one you can catch an exception for this. But if a user leaves your app and later returns you still need to be able to inform them.

You can do this by using the new `hasIncompletePayment` method on the `Billable` trait. Afterwards you may use the `latestPayment` method on the `Subscription` model to retrieve the latest failed payment and redirect the user to the payment screen. An example of this implementation in a view could be this:

```blade
@if ($user->hasIncompletePayment())
    <a href="{{ route('cashier.payment', $user->subscription()->latestPayment()->id) }}">
        Please confirm your payment.
    </a>
@endif
```

Note that when a subscription is in an incomplete state, no plan changes can occur and an `SubscriptionUpdateFailure` exception will occur when you try to call the `swap` or `updateQuantity` method. This is a limitation by Stripe.

#### Payment Notification

Since SCA regulations require customers to occasionally verify their payment details even while their subscription is active, Cashier can now send reminder emails to the customer when off-session payment confirmation is required. For example, this may occur when a subscription is renewing. By default, these emails are disabled. Cashier's confirmation notification can be enabled by setting the `CASHIER_PAYMENT_NOTIFICATION` environment variable to the corresponding shipped class

    CASHIER_PAYMENT_NOTIFICATION=Laravel\Cashier\Notifications\ConfirmPayment

You can easily swap this notification out with a custom one to support more notification channels as you wish:

    CASHIER_PAYMENT_NOTIFICATION=App\Notifications\ConfirmPayment

### Cards And Payment Methods

PR: https://github.com/laravel/cashier/pull/696  
PR: https://github.com/laravel/cashier/pull/701

Cashier has been entirely migrated to use the new recommended [Payment Methods API](https://stripe.com/docs/payments/payment-methods). This API effectively [replaces the former Sources and Tokens API](https://stripe.com/docs/payments/payment-methods#transitioning). At the moment, the Payment Methods API only supports cards but support for all of the other payment flows [is planned by Stripe](https://stripe.com/docs/payments/payment-methods#transitioning).

Payment Methods are backwards compatible with the Sources and Tokens APIs, meaning that if you've saved cards as a source on a customer, they can be retrieved with the new Payment Methods API. However, at the moment there isn't a way to retrieve the default source from a customer through the Payment Methods API. Therefore, the `defaultPaymentMethod` method on the `Billable` user will return an instance of a `Stripe\Card` or `Stripe\BankAccount` if no default Payment Method could be found.

It's important to note that any default source set on a customer will still continue to work when creating new subscriptions. However, considering new SCA regulations which take affect September 2019, it's important that you update your integration to the new Payment Methods API as soon as possible and do not use the Sources or Tokens APIs any longer. It's best that you check if a customer hasn't got a default payment method yet that you transition them first before creating a new subscription. You can check that with the example below:

```php
use Stripe\Card as StripeCard;
use Stripe\BankAccount as StripeBankAccount;

$defaultPaymentMethod = $user->defaultPaymentMethod();

if ($defaultPaymentMethod instanceof StripeCard || $defaultPaymentMethod instanceof StripeBankAccount) {
    // Inform the user to re-add their payment info. 
}
```

Saving payment methods for recurring use (like subscriptions) is detailed below in the "Setup Intents" part.

The `Card` class in Cashier has been replaced with a new `PaymentMethod` class.

#### Setup Intents

PR: https://github.com/laravel/cashier/pull/700

When saving payment methods it's important that you use the new Setup Intent API if you want to make sure your off-session recurring payments for your subscription keep working and don't trigger secondary payment actions.

The best guide on this is the following one: https://stripe.com/docs/payments/cards/saving-cards#saving-card-without-payment

The guide above describes how to create a setup intent, pass its secret to the JS integration and then create a new payment method. After that you can use the payment method identifier to create a new subscription. A dedicated method to start a new setup intent session was added in Cashier:

```php
// Get a new \Stripe\SetupIntent instance.
$setupIntent = $user->createSetupIntent();
```

### Single Charges

These notes involve changes made to the `charge` method on the `Billable` trait.

#### Payment Method

The `charge` method now requires a payment method instead of a token. You will need to update your JS integration to retrieve a payment method id instead of a source token. These changes were done because this is now the recommended way by Stripe to work with payments. [More info about that can be found here](https://stripe.com/docs/payments/payment-intents/migration#web).

#### Payment Method Required

PR: https://github.com/laravel/cashier/pull/697

Because Stripe doesn't offer a way to set a default payment method for single charges, the charge method now explicitly requires a payment method as a second parameter.

```php
$user->charge(1000, ['source' => $token]);
```

Change the above to:

```php
$user->charge(1000, $paymentMethod);
```

You can retrieve a payment method by implementing the new payment method JS integration from Stripe: https://stripe.com/docs/payments/payment-intents/migration#web

#### Stripe Customer

PR: https://github.com/laravel/cashier/pull/683

Another minor change is that if a payment method was provided but the billable user is already a Stripe customer, then that customer ID will **always** be passed to Stripe to make sure the payment is associated with the customer you're performing the payment with. Before this when providing a payment source, the customer id on the billable user was ignored and the payment wasn't associated with the customer at all. We considered this to be a bug.

### Webhooks

#### Webhooks Are Now Required

With the latest updates in v10 and the way Stripe has shifted to an asynchronous workflow with payment intents, webhooks are now an essential part of this workflow and need to be enabled in order for your app to properly update subscriptions when handling payments. [You can read more about enabling webhooks here](https://laravel.com/docs/billing#handling-stripe-webhooks).

#### Webhooks Are Now Auto-loaded

PR: https://github.com/laravel/cashier/pull/672

The webhooks route is now also automatically loaded for you and doesn't needs to be added manually to your routes file anymore.

### Invoices

PR: https://github.com/laravel/cashier/pull/685
PR: https://github.com/laravel/cashier/pull/711

Internals in Cashier on how to properly format money values have been refactored. Cashier now makes use of the `moneyphp/money` library to format these values. Because of this refactor, the `useCurrencySymbol`, `usesCurrencySymbol`, `guessCurrencySymbol` methods, and the `$symbol` parameter on the `useCurrency` have been removed.

The starting balance is now no longer subtracted from the subtotal of an invoice. 

All `raw` methods on the `Invoice` object now return integers instead of floats. These integers represent money values starting from cents.

The invoice PDF also got a new layout. See an example in the above pull request.

All invoice methods in Cashier will now also return an instance of `Laravel\Cashier\Invoice` instead of the `Stripe\Invoice` object.

### Subscriptions

#### Swap Options

PR: https://github.com/laravel/cashier/pull/620

The `swap` method now accepts a new `$options` argument to easily set extra options on the subscription.

#### Swap And Invoicing

PR: https://github.com/laravel/cashier/pull/710

The `swap` method will now not also automatically invoice the customer. Instead, a dedicated `swapAndInvoice` method was added. This is more conform the default Stripe behavior of letting the invoicing happen at the regular billing period. If you still want to immediately invoice a customer when they change their plan, you should call the `swapAndInvoice` method instead. This also brings functionality on pair with the `incrementAndInvoice` method.

### Customers

PR: https://github.com/laravel/cashier/pull/682

The following methods now require that the `Billable` user is a customer registered with Stripe before they can be called: `tab`, `invoice`, `upcomingInvoice`, `invoices`, `cards`, `updateCard`, `applyCoupon`. If you attempt to call them without first creating a Stripe customer, these methods will thrown an exception.


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

# Upgrade Guide

## Upgrading To 9.3 From 9.2

### Custom Subscription Creation Exception

[In their 2019-03-14 API update](https://stripe.com/docs/upgrades#2019-03-14), Stripe changed the way they handle new subscriptions when card payment fails. Instead of letting the creation of the subscription fail, the subscription is failed with an "incomplete" status. Because of this a Cashier customer will always get a successful subscription. Previously a card exception was thrown.

To accommodate for this new behavior from now on Cashier will cancel that subscription immediately and throw a custom `SubscriptionCreationFailed` exception when a subscription is created with an "incomplete" or "incomplete_expired" status. We've decided to do this because in general you want to let a customer only start using your product when payment was received.

If you were relying on catching the `\Stripe\Error\Card` exception before you should now rely on catching the `Laravel\Cashier\Exceptions\SubscriptionCreationFailed` exception instead. 

### Card failure upon plan swapping

Previously, when a plan swap was attempted and payment failed, the exception was cascaded to the end user and the update to the subscription in the app was not performed. However, the update to the subscription in Stripe itself was performed and the two states would be out of sync unless you implemented webhooks. 

We've decided to catch the card failure exception and allow the plan swap to continue regardless of the failed payment. This leaves the subscription in a "past_due" state. This is because payment failure will be handled by Stripe and Stripe may attempt to retry the payment later on. When payment finally fails on its last attempt Stripe will send out a webhook to update the subscription in the way you specified in its settings: https://stripe.com/docs/billing/lifecycle#settings

The change you should accommodate for is to implement Stripe's webhooks to let Cashier update the subscription automatically. [See our instructions for setting up Stripe webhooks with Cashier.](https://laravel.com/docs/master/billing#handling-stripe-webhooks)

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

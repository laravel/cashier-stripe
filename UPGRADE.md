# Upgrade Guide

## Upgrading To 9.0 From 8.0

### PHP & Laravel Version Requirements

Support for PHP 7.0 and Laravel <= 5.6 was dropped in [#595](https://github.com/laravel/cashier/pull/595). We encourage you to upgrade to the latest versions of PHP and Laravel before upgrading to Cashier 9.0.

### The `createAsStripeCustomer` Method

The `updateCard` call was extracted from the `createAsStripeCustomer` method on the `Billable` trait in PR [#588](https://github.com/laravel/cashier/pull/588). In addition, the `$token` parameter was removed. If you were using this method directly you should call the `updateCard` method separately after calling the `createAsStripeCustomer` method. This provides better granularity when handling errors for the two calls separately.

### WebhookController Changes

Instead of calling the Stripe API, Cashier now only uses webhook signatures to verify that events it receives are authentic as of [PR #591](https://github.com/laravel/cashier/pull/591).

The `VerifyWebhookSignature` middleware is now automatically added to the `WebhookController` if the `services.stripe.webhook.secret` value is set in your `services.php` configuration file. By default, this configuration value uses the `STRIPE_WEBHOOK_SECRET` environment variable.

If you manually added the `VerifyWebhookSignature` middleware to your Cashier webhook route, you may remove it since it will now be added automatically.

If you were using the `CASHIER_ENV` environment variable to test incoming webhooks, you should may set the `STRIPE_WEBHOOK_SECRET` environment variable to `null` to achieve the same behavior.

More information about verifying webhooks can be found [in the Cashier documentation](https://laravel.com/docs/5.7/billing#verifying-webhook-signatures).

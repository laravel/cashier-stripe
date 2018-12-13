# Upgrade Guide

## Upgrading To 9.0 From 8.0

### PHP 7.0 & Laravel <=5.6

Support for PHP 7.0 and Laravel <=5.6 was dropped in [#595](https://github.com/laravel/cashier/pull/595). We encourage you to upgrade to the latest versions of PHP and Laravel before upgrading.

### `createAsStripeCustomer` Changes

The `updateCard` call was extracted from the `createAsStripeCustomer` method on the `Billable` trait in PR [#588](https://github.com/laravel/cashier/pull/588) as well as the `$token` parameter. If you were using this method directly you may call the `updateCard` method after it.

### WebhookController Changes

Manually checking for events through the Stripe API and the `CASHIER_ENV` environment variable were removed in [#591](https://github.com/laravel/cashier/pull/591). Further more, support for the `VerifyWebhookSignature` middleware was added directly inside the `WebhookController`'s constructor. If you are using incoming webhooks you should verify the events with the middleware by setting the `STRIPE_WEBHOOK_SECRET` environment variable. 

If you were using the `CASHIER_ENV` environment variable to test incoming webhooks you may disable the `STRIPE_WEBHOOK_SECRET` environment variable to achieve the same behavior.

More information about verifying webhooks can be found [in the Cashier documentation](https://laravel.com/docs/5.7/billing#verifying-webhook-signatures).

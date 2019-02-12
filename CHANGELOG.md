# Release Notes

## [v9.0.1](https://github.com/laravel/cashier/compare/v9.0.0...v9.0.1)

### Added
- Allow Carbon 2 installs ([a3b9d36](https://github.com/laravel/cashier/commit/a3b9d3688e21d3d9d3ae72ef58db585c80d96fa3))

### Changed
- Test against latest Stripe API version ([#603](https://github.com/laravel/cashier/pull/603))

### Fixed
- Correct PHP Doc @return tag ([#601](https://github.com/laravel/cashier/pull/601))

## [v9.0.0 (2018-12-17)](https://github.com/laravel/cashier/compare/v8.0.1...v9.0.0)

### Changed
- Removed support for PHP 7.0 ([#595](https://github.com/laravel/cashier/pull/595))
- Require Laravel 5.7 as minimum version ([#595](https://github.com/laravel/cashier/pull/595))
- Extract `updateCard` from `createAsStripeCustomer` method ([#588](https://github.com/laravel/cashier/pull/588))
- Remove `CASHIER_ENV` and event checks and encourage usage of `VerifyWebhookSignature` middleware ([#591](https://github.com/laravel/cashier/pull/591))
- The `invoice` method now accepts an `$options` param ([#598](https://github.com/laravel/cashier/pull/598))
- The `invoiceFor` method now accepts an `$invoiceOptions` param ([#598](https://github.com/laravel/cashier/pull/598))

### Fixed
- Fixed some DocBlocks ([#594](https://github.com/laravel/cashier/pull/594))
- Fixed a bug where the `swap` and `incrementAndInvoice` methods on the `Subscription` model would sometimes invoice other pending invoice items ([#598](https://github.com/laravel/cashier/pull/598))

## Version 2.0.4

- Allow user to pass paramaters when fetching invoices.
- Added a method to get the current subscription period's end date.
- If a webhook endpoint is not defined for a given hook, an empty 200 response will be returned.

## Version 2.0.3

- Added space for extra / VAT information in receipts.
- Implemented missing method on web hook controller.

## Version 2.0.2

- Fixed how credit cards are updated.

## Version 2.0.1

- Renamed WebhookController's failed payment method to handleInvoicePaymentFailed.
- Added ability to webhook controller to automatically route all webhooks to appropriately named methods of they area available.

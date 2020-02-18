# Release Notes

## [Unreleased](https://github.com/laravel/cashier/compare/v10.6.0...master)

### Changed
- Dropped Laravel 5.8 support ([b6256a2](https://github.com/laravel/cashier/commit/b6256a2a2e486478a26043cb2926dc744dc0a42a))


## [v10.6.0 (2020-02-18)](https://github.com/laravel/cashier/compare/v10.5.3...v10.6.0)

### Added
- Add `findBillable` method ([#869](https://github.com/laravel/cashier/pull/869))

### Fixed
- Prevent `createAsStripeCustomer` when `stripe_id` is set ([#871](https://github.com/laravel/cashier/pull/871))


## [v10.5.3 (2020-01-14)](https://github.com/laravel/cashier/compare/v10.5.2...v10.5.3)

### Fixed
- Fix `findInvoiceOrFail` behavior ([#853](https://github.com/laravel/cashier/pull/853))


## [v10.5.2 (2020-01-07)](https://github.com/laravel/cashier/compare/v10.5.1...v10.5.2)

### Changed
- Assert customer exists before retrieving ([#834](https://github.com/laravel/cashier/pull/834))
- Simplify refund method ([#837](https://github.com/laravel/cashier/pull/837))

### Fixed
- Fix typo in exception message ([#846](https://github.com/laravel/cashier/pull/846))
- Fix overriding notification subject translation ([59faaf3](https://github.com/laravel/cashier/commit/59faaf3d6163de95fd977511121bcee695fb6bbe))


## [v10.5.1 (2019-11-26)](https://github.com/laravel/cashier/compare/v10.5.0...v10.5.1)

### Added
- Symfony 5 support ([#822](https://github.com/laravel/cashier/pull/822))


## [v10.5.0 (2019-11-12)](https://github.com/laravel/cashier/compare/v10.4.0...v10.5.0)

### Added
- Webhook events ([#810](https://github.com/laravel/cashier/pull/810))

### Fixed
- Add missing `@throws` tags ([#813](https://github.com/laravel/cashier/pull/813))
- Properly return `null` for find methods ([#817](https://github.com/laravel/cashier/pull/817))


## [v10.4.0 (2019-10-29)](https://github.com/laravel/cashier/compare/v10.3.0...v10.4.0)

### Added
- Add findPaymentMethod method ([#801](https://github.com/laravel/cashier/pull/801))
- Allow to set past due as active ([#802](https://github.com/laravel/cashier/pull/802))


## [v10.3.0 (2019-10-01)](https://github.com/laravel/cashier/compare/v10.2.1...v10.3.0)

### Added
- Configure Stripe logger ([#790](https://github.com/laravel/cashier/pull/790), [4a53b46](https://github.com/laravel/cashier/commit/4a53b4620ea5a082f3d6e69d881a971889e8c3eb))

### Changed
- Add language line for full name placeholder ([#782](https://github.com/laravel/cashier/pull/782))
- Update Stripe SDK to v7 ([#784](https://github.com/laravel/cashier/pull/784))
- Refactor handling of invalid webhook signatures ([#791](https://github.com/laravel/cashier/pull/791))
- Remove config repository dependency from webhook middleware ([#793](https://github.com/laravel/cashier/pull/793))

### Fixed
- Remove extra sign off from `ConfirmPayment` notification ([#779](https://github.com/laravel/cashier/pull/779))


## [v10.2.1 (2019-09-10)](https://github.com/laravel/cashier/compare/v10.2.0...v10.2.1)

### Fixed
- Ensure SVG icons are visible even with a long success or error message ([#772](https://github.com/laravel/cashier/pull/772))


## [v10.2.0 (2019-09-03)](https://github.com/laravel/cashier/compare/v10.1.1...v10.2.0)

### Added
- Add ability to ignore cashier routes ([#763](https://github.com/laravel/cashier/pull/763))

### Fixed
- Only mount card element if payment has not succeeded or been cancelled ([#765](https://github.com/laravel/cashier/pull/765))
- Set off_session parameter to true when creating a new subscription ([#764](https://github.com/laravel/cashier/pull/764))


## [v10.1.1 (2019-08-27)](https://github.com/laravel/cashier/compare/v10.1.0...v10.1.1)

### Fixed
- Remove collation from migrations ([#761](https://github.com/laravel/cashier/pull/761))


## [v10.1.0 (2019-08-20)](https://github.com/laravel/cashier/compare/v10.0.0...v10.1.0)

### Added
- Multiple stripe accounts ([#754](https://github.com/laravel/cashier/pull/754))
- Set Stripe library info ([#756](https://github.com/laravel/cashier/pull/756))
- Paper size can be set in config file ([#752](https://github.com/laravel/cashier/pull/752), [cb837d1](https://github.com/laravel/cashier/commit/cb837d13f570353b85b27bd381e8669d1fee3491))

### Changed
- Update Stripe API version to `2019-08-14` ([#749](https://github.com/laravel/cashier/pull/749))

### Fixed
- `syncStripeStatus` trying to update incorrect status column ([#748](https://github.com/laravel/cashier/pull/748))


## [v10.0.0 (2019-08-13)](https://github.com/laravel/cashier/compare/v10.0.0-beta.2...v10.0.0)

### Added
- Allow hasIncompletePayment() to check other subscriptions than “default” ([#733](https://github.com/laravel/cashier/pull/733))
- Add indexes to those columns used to lookup data in the database ([#739](https://github.com/laravel/cashier/pull/739))

### Fixed
- Fixed a label with an incorrect for attribute ([#732](https://github.com/laravel/cashier/pull/732))


## [v10.0.0-beta.2 (2019-08-02)](https://github.com/laravel/cashier/compare/v10.0.0-beta...v10.0.0-beta.2)

### Added
- Add latestPayment method on Subscription ([#705](https://github.com/laravel/cashier/pull/705))
- Allow custom filename for invoice download ([#723](https://github.com/laravel/cashier/pull/723))

### Changed
- Improve stripe statuses ([#707](https://github.com/laravel/cashier/pull/707))
- Refactor active subscription state ([#712](https://github.com/laravel/cashier/pull/712))
- Return invoice object when applicable ([#711](https://github.com/laravel/cashier/pull/711))
- Refactor webhook responses ([#722](https://github.com/laravel/cashier/pull/722))
- Refactor confirm payment mail to notification ([#727](https://github.com/laravel/cashier/pull/727))

### Fixed
- Fix createSetupIntent ([#704](https://github.com/laravel/cashier/pull/704))
- Fix subscription invoicing ([#710](https://github.com/laravel/cashier/pull/710))
- Fix `null` return for `latestPayment` method ([#730](https://github.com/laravel/cashier/pull/730))

### Removed
- Remove unused `$customer` parameter on `updateQuantity` method ([#729](https://github.com/laravel/cashier/pull/729))


## [v10.0.0-beta (2019-07-17)](https://github.com/laravel/cashier/compare/v9.3.5...v10.0.0-beta)

Cashier 10.0 is a major release. Please review [the upgrade guide](UPGRADE.md) thoroughly.


## [v9.3.5 (2019-07-30)](https://github.com/laravel/cashier/compare/v9.3.4...v9.3.5)

### Changed
- Remove old 5.9 version constraints ([c7664fc](https://github.com/laravel/cashier/commit/c7664fc90d0310d6fa3a52bec45e94868bff995d))

### Fixed
- Don't try and find a user when stripeId is null ([#721](https://github.com/laravel/cashier/pull/721))


## [v9.3.4 (2019-07-29)](https://github.com/laravel/cashier/compare/v9.3.3...v9.3.4)

### Changed
- Updated version constraints for Laravel 6.0 ([4a4c5c2](https://github.com/laravel/cashier/commit/4a4c5c226bb98aa0726f57bb5970115d3eaab377))


## [v9.3.3 (2019-06-14)](https://github.com/laravel/cashier/compare/v9.3.2...v9.3.3)

### Fixed
- Fix hasStartingBalance and subtotal on `Invoice` ([#684](https://github.com/laravel/cashier/pull/684))


## [v9.3.2 (2019-06-04)](https://github.com/laravel/cashier/compare/v9.3.1...v9.3.2)

### Changed
- `VerifyWebhookSignature` is no longer `final` ([260de04](https://github.com/laravel/cashier/commit/260de0458fc76708f90eb955ddddef0ee6d68798))
- Remove strict type check for `trialUntil()` ([#678](https://github.com/laravel/cashier/pull/678))


## [v9.3.1 (2019-05-07)](https://github.com/laravel/cashier/compare/v9.3.0...v9.3.1)

### Fixed
- Fixing `defaultCard()` exception when user is not a Stripe customer ([#660](https://github.com/laravel/cashier/pull/660))


## [v9.3.0 (2019-04-16)](https://github.com/laravel/cashier/compare/v9.2.1...v9.3.0)

### Added
- Able to update a Stripe customer ([#634](https://github.com/laravel/cashier/pull/634))

### Fixed
- Handle incomplete subscriptions upon creation ([#631](https://github.com/laravel/cashier/pull/631))
- Handle card failure in plan swap ([#641](https://github.com/laravel/cashier/pull/641))


## [v9.2.1 (2019-03-19)](https://github.com/laravel/cashier/compare/v9.2.0...v9.2.1)

### Fixed
- Use new created property on invoice ([4714ba4](https://github.com/laravel/cashier/commit/4714ba4ad909092a61bfe2d0704b3fce6070ed5b))


## [v9.2.0 (2019-03-12)](https://github.com/laravel/cashier/compare/v9.1.0...v9.2.0)

### Added
- Add subscription state scopes ([#609](https://github.com/laravel/cashier/pull/609))

### Changed
- Test latest Stripe API version ([#611](https://github.com/laravel/cashier/pull/611))


## [v9.1.0 (2019-02-12)](https://github.com/laravel/cashier/compare/v9.0.1...v9.1.0)

### Added
- Laravel 5.8 support ([291f4b2](https://github.com/laravel/cashier/commit/291f4b217ddbbd8a641072d8476fb11805b9801f))


## [v9.0.1 (2019-02-03)](https://github.com/laravel/cashier/compare/v9.0.0...v9.0.1)

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

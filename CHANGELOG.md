# Release Notes

## [Unreleased](https://github.com/laravel/cashier/compare/v13.8.6...master)

### Changed
- Cascade Stripe exceptions when invoicing ([#1210](https://github.com/laravel/cashier-stripe/pull/1210))

### Removed
- Drop PHP 7.3 support ([#1186](https://github.com/laravel/cashier-stripe/pull/1186))

## [v13.8.6](https://github.com/laravel/cashier-stripe/compare/v13.8.5...v13.8.6) - 2022-04-12

### Fixed

- Fix issue with zero tax on invoice by @driesvints in https://github.com/laravel/cashier-stripe/pull/1343

## [v13.8.5](https://github.com/laravel/cashier-stripe/compare/v13.8.4...v13.8.5) - 2022-04-05

### Changed

- Pass locale to custom format amount by @driesvints in https://github.com/laravel/cashier-stripe/pull/1340

## [v13.8.4](https://github.com/laravel/cashier-stripe/compare/v13.8.3...v13.8.4) - 2022-03-15

### Changed

- Make use of anonymous classes by @mmachatschek in https://github.com/laravel/cashier-stripe/pull/1329
- Allow normal and metered prices in builder by @driesvints in https://github.com/laravel/cashier-stripe/pull/1336

## [v13.8.3](https://github.com/laravel/cashier-stripe/compare/v13.8.2...v13.8.3) - 2022-02-22

### Changed

- Fix rawDiscountFor method by @driesvints in https://github.com/laravel/cashier-stripe/pull/1325
- Fix swapping metered price of subscription item by @pietrantonio91 in https://github.com/laravel/cashier-stripe/pull/1328

## [v13.8.2](https://github.com/laravel/cashier-stripe/compare/v13.8.1...v13.8.2) - 2022-02-08

### Fixed

- Fallback to null quantity for metered price when swapping a subscription ([#1319](https://github.com/laravel/cashier-stripe/pull/1319))

## [v13.8.1 (2022-02-01)](https://github.com/laravel/cashier/compare/v13.8.0...v13.8.1)

### Fixed

- Prevent duplicate subscription creation ([#1308](https://github.com/laravel/cashier-stripe/pull/1308))

## [v13.8.0 (2022-01-25)](https://github.com/laravel/cashier/compare/v13.7.0...v13.8.0)

### Added

- Implement invoice renderer ([#1304](https://github.com/laravel/cashier-stripe/pull/1304))

## [v13.7.0 (2022-01-12)](https://github.com/laravel/cashier-stripe/compare/v13.6.1...v13.7.0)

### Added

- Add deletePaymentMethod ([#1298](https://github.com/laravel/cashier-stripe/pull/1298))

### Changed

- Laravel 9 Support ([#1299](https://github.com/laravel/cashier-stripe/pull/1299))

## [v13.6.1 (2021-11-23)](https://github.com/laravel/cashier-stripe/compare/v13.6.0...v13.6.1)

### Changed

- Simplify cancelation check ([#1283](https://github.com/laravel/cashier-stripe/pull/1283))
- Rename cancelled to canceled ([#1284](https://github.com/laravel/cashier-stripe/pull/1284))
- Allow latest moneyphp version ([#1280](https://github.com/laravel/cashier-stripe/pull/1280), [#1286](https://github.com/laravel/cashier-stripe/pull/1286))

### Fixed

- Fix factory canceled state ([#1282](https://github.com/laravel/cashier-stripe/pull/1282))

## [v13.6.0 (2021-11-09)](https://github.com/laravel/cashier-stripe/compare/v13.5.6...v13.6.0)

### Added

- Add `createAndSendInvoice` method ([#1276](https://github.com/laravel/cashier-stripe/pull/1276))

### Changed

- Allow the base url for the Stripe API to be customised ([#1273](https://github.com/laravel/cashier-stripe/pull/1273))

## [v13.5.6 (2021-10-26)](https://github.com/laravel/cashier-stripe/compare/v13.5.5...v13.5.6)

### Fixed

- Fix enabling auto collecting in checkout ([#1270](https://github.com/laravel/cashier-stripe/pull/1270))

## [v13.5.5 (2021-10-19)](https://github.com/laravel/cashier-stripe/compare/v13.5.4...v13.5.5)

### Fixed

- Fix confirming payments on the payment page ([#1268](https://github.com/laravel/cashier-stripe/pull/1268))

## [v13.5.4 (2021-10-12)](https://github.com/laravel/cashier-stripe/compare/v13.5.3...v13.5.4)

### Fixed

- Handle Stripe Object lock timeout ([#1259](https://github.com/laravel/cashier-stripe/pull/1259))

## [v13.5.3 (2021-09-05)](https://github.com/laravel/cashier-stripe/compare/v13.5.2...v13.5.3)

### Fixed

- Fix a stripe notice with SubscriptionBuilder and metered prices ([#1261](https://github.com/laravel/cashier-stripe/pull/1261))

## [v13.5.2 (2021-09-28)](https://github.com/laravel/cashier-stripe/compare/v13.5.1...v13.5.2)

### Changed

- Use default name for subscription factory ([#1250](https://github.com/laravel/cashier-stripe/pull/1250))
- PHP 8.1 compatibility ([#1251](https://github.com/laravel/cashier-stripe/pull/1251))

### Fixed

- Fallback to null quantity for metered price ([#1255](https://github.com/laravel/cashier-stripe/pull/1255))

## [v13.5.1 (2021-09-01)](https://github.com/laravel/cashier-stripe/compare/v13.5.0...v13.5.1)

### Fixed

- Fix setting name on subscription ([5d00c21](https://github.com/laravel/cashier-stripe/commit/5d00c21d3b9d8d54d77845dcc07406b7e72d3be0))

## [v13.5.0 (2021-08-31)](https://github.com/laravel/cashier-stripe/compare/v13.4.5...v13.5.0)

### Added

- Add invoices methods to subscription ([#1245](https://github.com/laravel/cashier-stripe/pull/1245))

### Fixed

- Fix webhook order issue ([#1243](https://github.com/laravel/cashier-stripe/pull/1243))
- Send data when upcoming invoice is refreshed ([#1244](https://github.com/laravel/cashier-stripe/pull/1244))
- Update customer details when tax id is collected ([#1246](https://github.com/laravel/cashier-stripe/pull/1246))

## [v13.4.5 (2021-08-23)](https://github.com/laravel/cashier-stripe/compare/v13.4.4...v13.4.5)

### Fixed

- Fix quantity filling on subscriptions ([#1238](https://github.com/laravel/cashier-stripe/pull/1238))
- Fix unreachable status value ([#1240](https://github.com/laravel/cashier-stripe/pull/1240))

## [v13.4.4 (2021-08-17)](https://github.com/laravel/cashier-stripe/compare/v13.4.3...v13.4.4)

### Changed

- Add support for inline price data ([#1235](https://github.com/laravel/cashier-stripe/pull/1235))

## [v13.4.3 (2021-08-10)](https://github.com/laravel/cashier-stripe/compare/v13.4.2...v13.4.3)

### Changed

- Allow promo codes on subscription updates ([#1230](https://github.com/laravel/cashier-stripe/pull/1230))

### Fixed

- Fix `asStripeCustomerBalanceTransaction` ([#1234](https://github.com/laravel/cashier-stripe/pull/1234))

## [v13.4.2 (2021-08-03)](https://github.com/laravel/cashier-stripe/compare/v13.4.1...v13.4.2)

### Fixed

- Fix issue with `requires_action` ([#1226](https://github.com/laravel/cashier-stripe/pull/1226))
- Fix async issue with webhooks ([#1227](https://github.com/laravel/cashier-stripe/pull/1227))

## [v13.4.1 (2021-07-13)](https://github.com/laravel/cashier-stripe/compare/v13.4.0...v13.4.1)

### Changed

- Implement server side Checkout redirect ([#1218](https://github.com/laravel/cashier-stripe/pull/1218))

## [v13.4.0 (2021-07-06)](https://github.com/laravel/cashier-stripe/compare/v13.3.0...v13.4.0)

### Added

- Implement customer balances ([#1216](https://github.com/laravel/cashier-stripe/pull/1216))

## [v13.3.0 (2021-06-29)](https://github.com/laravel/cashier-stripe/compare/v13.2.1...v13.3.0)

### Added

- Add `invoicePrice` and `tabPrice` methods ([#1213](https://github.com/laravel/cashier-stripe/pull/1213))

## [v13.2.1 (2021-06-24)](https://github.com/laravel/cashier-stripe/compare/v13.2.0...v13.2.1)

### Fixed

- Fix collecting tax ids ([#1209](https://github.com/laravel/cashier-stripe/pull/1209))

## [v13.2.0 (2021-06-22)](https://github.com/laravel/cashier-stripe/compare/v13.1.0...v13.2.0)

### Added

- Implement new `calculateTaxes` call ([#1198](https://github.com/laravel/cashier-stripe/pull/1198))
- Implement webhook command ([#1202](https://github.com/laravel/cashier-stripe/pull/1202), [1d9cce8](https://github.com/laravel/cashier-stripe/commit/1d9cce83d7f39014fe2957edadfb0cc7604563fb))

### Changed

- Prevent tax calculation for one-off charges ([#1206](https://github.com/laravel/cashier-stripe/pull/1206))

### Fixed

- Use correct method visibility ([#1200](https://github.com/laravel/cashier-stripe/pull/1200))

## [v13.1.0 (2021-06-15)](https://github.com/laravel/cashier-stripe/compare/v13.0.0...v13.1.0)

### Added

- Implement support for Stripe Tax ([#1190](https://github.com/laravel/cashier-stripe/pull/1190))
- Collect Tax IDs in Checkout ([#1191](https://github.com/laravel/cashier-stripe/pull/1191))

### Fixed

- Fix adding metered plan to subscription ([#1189](https://github.com/laravel/cashier-stripe/pull/1189))

## [v13.0.0 (2021-06-08)](https://github.com/laravel/cashier-stripe/compare/v12.15.0...v13.0.0)

### Added

- Support more payment method types ([#1074](https://github.com/laravel/cashier-stripe/pull/1074))
- Cashier Stripe Factories ([#1096](https://github.com/laravel/cashier-stripe/pull/1096))
- Multiple discounts on receipts ([#1147](https://github.com/laravel/cashier-stripe/pull/1147))
- Preview upcoming invoice ([#1146](https://github.com/laravel/cashier-stripe/pull/1146))
- Add new metered price methods ([#1177](https://github.com/laravel/cashier-stripe/pull/1177))
- Allow customers to be synced with Stripe ([#1178](https://github.com/laravel/cashier-stripe/pull/1178), [#1183](https://github.com/laravel/cashier-stripe/pull/1183))
- Add `stripe_product` column to `subscriptions_items` table ([#1185](https://github.com/laravel/cashier-stripe/pull/1185))

### Changed

- Rename plans to prices ([#1166](https://github.com/laravel/cashier-stripe/pull/1166))
- Stripe SDK refactor ([#1169](https://github.com/laravel/cashier-stripe/pull/1169))
- Update stripe api version ([#1001](https://github.com/laravel/cashier-stripe/pull/1001))
- Make plans optional for newSubscription ([#1066](https://github.com/laravel/cashier-stripe/pull/1066))
- Drop PHP 7.2 support ([#1065](https://github.com/laravel/cashier-stripe/pull/1065))
- Drop Laravel 6 & 7 support ([#1064](https://github.com/laravel/cashier-stripe/pull/1064))
- Refactor payment exceptions ([#1095](https://github.com/laravel/cashier-stripe/pull/1095))
- Refactor model config option ([#1100](https://github.com/laravel/cashier-stripe/pull/1100))
- Billing portal arguments ([#1104](https://github.com/laravel/cashier-stripe/pull/1104))
- Refactor receipts with more data from Stripe ([#1136](https://github.com/laravel/cashier-stripe/pull/1136))
- Add array types ([#1152](https://github.com/laravel/cashier-stripe/pull/1152))
- Throw payment exception for quantity methods ([#1155](https://github.com/laravel/cashier-stripe/pull/1155))
- Throw payment exceptions on item swap ([#1157](https://github.com/laravel/cashier-stripe/pull/1157))
- Payment page updates ([#1120](https://github.com/laravel/cashier-stripe/pull/1120))

### Fixed

- Fix trialEndsAt more closely represents onTrial ([#1129](https://github.com/laravel/cashier-stripe/pull/1129))

### Removed

- Remove legacy sources support ([#1077](https://github.com/laravel/cashier-stripe/pull/1077))

## [v12.15.0 (2021-06-22)](https://github.com/laravel/cashier-stripe/compare/v12.14.1...v12.15.0)

### Added

- Implement webhook command ([#1202](https://github.com/laravel/cashier-stripe/pull/1202))

## [v12.14.1 (2021-06-01)](https://github.com/laravel/cashier-stripe/compare/v12.14.0...v12.14.1)

### Fixed

- Fix broken `unit_amount` with `tab` ([9246063](https://github.com/laravel/cashier-stripe/commit/9246063882a09c29521e61a86e84f34cb86098c1))

## [v12.14.0 (2021-05-25)](https://github.com/laravel/cashier-stripe/compare/v12.13.1...v12.14.0)

### Added

- Support prorations while extending trials ([#1151](https://github.com/laravel/cashier-stripe/pull/1151))
- Add extra methods to invoice object ([#1167](https://github.com/laravel/cashier-stripe/pull/1167))

### Fixed

- Add extra 10 seconds of trial time for checkout session ([#1160](https://github.com/laravel/cashier-stripe/pull/1160))
- Fix adding invoice item with quantities ([#1161](https://github.com/laravel/cashier-stripe/pull/1161))
- Fix coupons with Stripe Checkout sessions ([#1165](https://github.com/laravel/cashier-stripe/pull/1165))
- Fix checkout owner ([7bbfe23](https://github.com/laravel/cashier-stripe/commit/7bbfe234c5f1657eee1223f9aee73b03bdef9894))

## [v12.13.1 (2021-05-11)](https://github.com/laravel/cashier-stripe/compare/v12.13.0...v12.13.1)

### Fixed

- Fix discount calculation ([#1144](https://github.com/laravel/cashier-stripe/pull/1144))

## [v12.13.0 (2021-04-27)](https://github.com/laravel/cashier-stripe/compare/v12.12.0...v12.13.0)

### Added

- Add methods for managing TaxIDs ([#1137](https://github.com/laravel/cashier-stripe/pull/1137))

### Fixed

- Fix receipt comments ([#1131](https://github.com/laravel/cashier-stripe/pull/1131))

## [v12.12.0 (2021-04-13)](https://github.com/laravel/cashier-stripe/compare/v12.11.0...v12.12.0)

### Added

- Implement object serialization ([#1116](https://github.com/laravel/cashier-stripe/pull/1116))

### Changed

- Replace `symfony/intl` dependency ([#1114](https://github.com/laravel/cashier-stripe/pull/1114))
- Extract creating subscription to a separate method ([#1124](https://github.com/laravel/cashier-stripe/pull/1124))

### Fixed

- Fix `latest_invoice` retrieval ([#1115](https://github.com/laravel/cashier-stripe/pull/1115))
- Fix coupon label on receipts ([#1118](https://github.com/laravel/cashier-stripe/pull/1118))

## [v12.11.0 (2021-03-30)](https://github.com/laravel/cashier-stripe/compare/v12.10.0...v12.11.0)

### Added

- Implement `cancelAt` method ([#1106](https://github.com/laravel/cashier-stripe/pull/1106))

## [v12.10.0 (2021-03-16)](https://github.com/laravel/cashier-stripe/compare/v12.9.3...v12.10.0)

### Added

- Added new payment status helper methods ([#1068](https://github.com/laravel/cashier-stripe/pull/1068))
- Add capture method ([#1099](https://github.com/laravel/cashier-stripe/pull/1099))

### Changed

- Update description on receipt ([#1098](https://github.com/laravel/cashier-stripe/pull/1098))
- Update payment page ([#1094](https://github.com/laravel/cashier-stripe/pull/1094))

## [v12.9.3 (2021-03-09)](https://github.com/laravel/cashier-stripe/compare/v12.9.2...v12.9.3)

### Changed

- Use DejaVu Sans for receipts ([#1083](https://github.com/laravel/cashier-stripe/pull/1083))

### Fixed

- Subscription Update Webhook bugfix ([#1085](https://github.com/laravel/cashier-stripe/pull/1085), [3184afc](https://github.com/laravel/cashier-stripe/commit/3184afce07b65986d7aaa6f570b9ac44f502303c))

## [v12.9.2 (2021-03-05)](https://github.com/laravel/cashier-stripe/compare/v12.9.1...v12.9.2)

### Fixed

- Fix missing method on WebhookController ([#1080](https://github.com/laravel/cashier-stripe/pull/1080))
- Allow relative urls for redirect ([#1082](https://github.com/laravel/cashier-stripe/pull/1082))

## [v12.9.1 (2021-02-23)](https://github.com/laravel/cashier-stripe/compare/v12.9.0...v12.9.1)

### Changed

- Allow model swapping ([#1067](https://github.com/laravel/cashier-stripe/pull/1067))

### Fixed

- Fix styles overwriting checkout button when class is set ([#1070](https://github.com/laravel/cashier-stripe/pull/1070))

## [v12.9.0 (2021-02-19)](https://github.com/laravel/cashier-stripe/compare/v12.8.1...v12.9.0)

### Added

- Add `endTrial` method ([#1062](https://github.com/laravel/cashier-stripe/pull/1062))

### Fixed

- Fix removing tax rates ([d803ae5](https://github.com/laravel/cashier-stripe/commit/d803ae57ae20ee1e38ff8bf47484dfada7eef79d))

## [v12.8.1 (2021-02-16)](https://github.com/laravel/cashier-stripe/compare/v12.8.0...v12.8.1)

### Fixed

- Fix removing tax rates ([#1059](https://github.com/laravel/cashier-stripe/pull/1059))

## [v12.8.0 (2021-02-09)](https://github.com/laravel/cashier-stripe/compare/v12.7.1...v12.8.0)

### Added

- Metered billing ([#1048](https://github.com/laravel/cashier-stripe/pull/1048))

### Changed

- Allow Stripe dashboard subscriptions ([#1058](https://github.com/laravel/cashier-stripe/pull/1058))

### Fixed

- Fix return type for invoice line items ([#1053](https://github.com/laravel/cashier-stripe/pull/1053))

## [v12.7.1 (2021-02-04)](https://github.com/laravel/cashier-stripe/compare/v12.7.0...v12.7.1)

### Fixed

- Fix tax rates for subscription checkouts ([#1050](https://github.com/laravel/cashier-stripe/pull/1050))

## [v12.7.0 (2021-02-02)](https://github.com/laravel/cashier-stripe/compare/v12.6.3...v12.7.0)

### Added

- Stripe Checkout Support ([#1007](https://github.com/laravel/cashier-stripe/pull/1007))

## [v12.6.3 (2021-01-19)](https://github.com/laravel/cashier-stripe/compare/v12.6.2...v12.6.3)

### Fixed

- Fix image support in PDF invoices ([#1045](https://github.com/laravel/cashier-stripe/pull/1045), [bb7d17e8](https://github.com/laravel/cashier-stripe/commit/bb7d17e8707700e3f71cb30ef3c60dc68071bb8f))

## [v12.6.2 (2021-01-05)](https://github.com/laravel/cashier-stripe/compare/v12.6.1...v12.6.2)

### Changed

- Bump dompdf to v0.8.4 ([8a4b495](https://github.com/laravel/cashier-stripe/commit/8a4b495b5feffd9359e20c11e8ac9febc2d3995a))
- Allow dompdf v1.0 ([4949af9](https://github.com/laravel/cashier-stripe/commit/4949af98b8e890fb062c134177f178cb5f375752))

## [v12.6.1 (2020-12-08)](https://github.com/laravel/cashier-stripe/compare/v12.6.0...v12.6.1)

### Fixed

- Fix proration behaviour being forced when syncing tax rates ([#1028](https://github.com/laravel/cashier-stripe/pull/1028))

## [v12.6.0 (2020-11-24)](https://github.com/laravel/cashier-stripe/compare/v12.5.0...v12.6.0)

### Added

- Add `withPromotionCode` method ([#1032](https://github.com/laravel/cashier-stripe/pull/1032))

### Changed

- Refactor duplicated code ([#1029](https://github.com/laravel/cashier-stripe/pull/1029))

## [v12.5.0 (2020-11-03)](https://github.com/laravel/cashier-stripe/compare/v12.4.2...v12.5.0)

### Added

- PHP 8 Support ([#1020](https://github.com/laravel/cashier-stripe/pull/1020))

## [v12.4.2 (2020-10-20)](https://github.com/laravel/cashier-stripe/compare/v12.4.1...v12.4.2)

### Fixed

- Fix trial ends at check ([#1015](https://github.com/laravel/cashier-stripe/pull/1015))

## [v12.4.1 (2020-10-06)](https://github.com/laravel/cashier-stripe/compare/v12.4.0...v12.4.1)

### Fixed

- Fix n+1 problem with subscription retrieval ([#1009](https://github.com/laravel/cashier-stripe/pull/1009))

## [v12.4.0 (2020-09-29)](https://github.com/laravel/cashier-stripe/compare/v12.3.1...v12.4.0)

### Added

- Implement `trialEndsAt` ([#1000](https://github.com/laravel/cashier-stripe/pull/1000))

### Changed

- Simplify subscription method ([#1003](https://github.com/laravel/cashier-stripe/pull/1003))

### Fixed

- Fix quantity preserving ([#999](https://github.com/laravel/cashier-stripe/pull/999))
- Fix Models namespace for Laravel 8 ([9024107](https://github.com/laravel/cashier-stripe/commit/9024107f51bcdd69607395dc5606fdcd35498f85))

## [v12.3.1 (2020-09-01)](https://github.com/laravel/cashier-stripe/compare/v12.3.0...v12.3.1)

### Fixed

- Fix double payment method ([#987](https://github.com/laravel/cashier-stripe/pull/987))

## [v12.3.0 (2020-08-25)](https://github.com/laravel/cashier-stripe/compare/v12.2.0...v12.3.0)

### Added

- Support Laravel 8 ([#985](https://github.com/laravel/cashier-stripe/pull/985))

### Changed

- Stripe SDK minimum version is now `^7.39` ([#981](https://github.com/laravel/cashier-stripe/pull/981))

### Fixed

- Fix url checking for invalid urls ([#984](https://github.com/laravel/cashier-stripe/pull/984))

## [v12.2.0 (2020-07-21)](https://github.com/laravel/cashier-stripe/compare/v12.1.0...v12.2.0)

### Added

- Apply prorate and invoice_now for cancelNow ([#975](https://github.com/laravel/cashier-stripe/pull/975))

## [v12.1.0 (2020-06-30)](https://github.com/laravel/cashier-stripe/compare/v12.0.1...v12.1.0)

### Added

- Add support for Stripe's Customer Portal ([#966](https://github.com/laravel/cashier-stripe/pull/966))

## [v12.0.1 (2020-06-16)](https://github.com/laravel/cashier-stripe/compare/v12.0.0...v12.0.1)

### Fixed

- Fix validating payment intent ([#959](https://github.com/laravel/cashier-stripe/pull/959))

## [v12.0.0 (2020-06-09)](https://github.com/laravel/cashier-stripe/compare/v11.3.1...v12.0.0)

### Changed

- Implement new proration and pending updates ([#949](https://github.com/laravel/cashier-stripe/pull/949))

## [v11.3.1 (2020-10-31)](https://github.com/laravel/cashier-stripe/compare/v11.3.0...v11.3.1)

### Fixed

- Fail url checking when url is invalid ([#1021](https://github.com/laravel/cashier-stripe/pull/1021))

## [v11.3.0 (2020-05-26)](https://github.com/laravel/cashier-stripe/compare/v11.2.4...v11.3.0)

### Added

- Add convenience methods to update stripe objects ([#943](https://github.com/laravel/cashier-stripe/pull/943))

### Fixed

- Send invoice when charging automatically ([#942](https://github.com/laravel/cashier-stripe/pull/942))
- Remove unnecessary if statement on receipt ([#946](https://github.com/laravel/cashier-stripe/pull/946))

## [v11.2.4 (2020-05-08)](https://github.com/laravel/cashier-stripe/compare/v11.2.3...v11.2.4)

### Fixed

- Fix undefined redirect error ([0fc4c6e](https://github.com/laravel/cashier-stripe/commit/0fc4c6e7b3b44ea05cb7803820a87d11ddb29baa))

## [v11.2.3 (2020-05-05)](https://github.com/laravel/cashier-stripe/compare/v11.2.2...v11.2.3)

### Security

- Protect against host mismatch ([#930](https://github.com/laravel/cashier-stripe/pull/930))

## [v11.2.2 (2020-04-28)](https://github.com/laravel/cashier-stripe/compare/v11.2.1...v11.2.2)

### Fixed

- Fix add and remove plan ([#926](https://github.com/laravel/cashier-stripe/pull/926))
- Fix quantity prorating ([#924](https://github.com/laravel/cashier-stripe/pull/924))
- Fix multiplan subscription swapping ([#925](https://github.com/laravel/cashier-stripe/pull/925))

## [v11.2.1 (2020-04-21)](https://github.com/laravel/cashier-stripe/compare/v11.2.0...v11.2.1)

### Fixed

- Fix quantity methods ([#919](https://github.com/laravel/cashier-stripe/pull/919))

## [v11.2.0 (2020-04-16)](https://github.com/laravel/cashier-stripe/compare/v11.1.0...v11.2.0)

### Changed

- Re-add tax percentage ([#916](https://github.com/laravel/cashier-stripe/pull/916))

### Fixed

- Fix syncing of tax rates ([0489030](https://github.com/laravel/cashier-stripe/commit/0489030c50c0f5443abe37849c73935a34699387))
- Remove null assignment ([8520443](https://github.com/laravel/cashier-stripe/commit/85204430cbc1681d776b50ca42d5b9194e8c14d7))

## [v11.1.0 (2020-04-14)](https://github.com/laravel/cashier-stripe/compare/v11.0.0...v11.1.0)

### Added

- Multiplan swapping ([#915](https://github.com/laravel/cashier-stripe/pull/915))

## [v11.0.0 (2020-04-07)](https://github.com/laravel/cashier-stripe/compare/v10.7.1...v11.0.0)

### Added

- Multiplan subscriptions ([#900](https://github.com/laravel/cashier-stripe/pull/900))
- Tax Rates ([#830](https://github.com/laravel/cashier-stripe/pull/830))
- Add new has payment method ([#838](https://github.com/laravel/cashier-stripe/pull/838))

### Changed

- Update stripe api version ([#905](https://github.com/laravel/cashier-stripe/pull/905))
- Require PHP 7.2 ([f0f8cd1](https://github.com/laravel/cashier-stripe/commit/f0f8cd1c58751e98ad1d9387b37bf7cfe9883c4a))
- Dropped Laravel 5.8 support ([b6256a2](https://github.com/laravel/cashier-stripe/commit/b6256a2a2e486478a26043cb2926dc744dc0a42a))
- Allow for subscription options ([#868](https://github.com/laravel/cashier-stripe/pull/868), [#901](https://github.com/laravel/cashier-stripe/pull/901))
- Use proper invoice number ([#878](https://github.com/laravel/cashier-stripe/pull/878))
- Loosen exception throwing ([#882](https://github.com/laravel/cashier-stripe/pull/882))
- Rename some exceptions ([#881](https://github.com/laravel/cashier-stripe/pull/881))
- Allow for custom filename with downloadInvoice method ([#889](https://github.com/laravel/cashier-stripe/pull/889))
- Split `Billable` trait into multiple concerns ([#898](https://github.com/laravel/cashier-stripe/pull/898))

## [v10.7.1 (2020-03-24)](https://github.com/laravel/cashier-stripe/compare/v10.7.0...v10.7.1)

### Fixed

- Send along status for payment page ([#896](https://github.com/laravel/cashier-stripe/pull/896))

## [v10.7.0 (2020-03-03)](https://github.com/laravel/cashier-stripe/compare/v10.6.0...v10.7.0)

### Added

- Add getters for owner instances to objects ([#877](https://github.com/laravel/cashier-stripe/pull/877))
- Implement extending trials ([#884](https://github.com/laravel/cashier-stripe/pull/884))
- Allow for custom email address attribute ([#887](https://github.com/laravel/cashier-stripe/pull/887))
- Re-enable proration ([#886](https://github.com/laravel/cashier-stripe/pull/886))

### Changed

- Add @throws declaration to methods on Billable which can throw Payment exceptions ([#872](https://github.com/laravel/cashier-stripe/pull/872))
- Update payment page with new JS method ([#879](https://github.com/laravel/cashier-stripe/pull/879))

## [v10.6.0 (2020-02-18)](https://github.com/laravel/cashier-stripe/compare/v10.5.3...v10.6.0)

### Added

- Add `findBillable` method ([#869](https://github.com/laravel/cashier-stripe/pull/869))

### Fixed

- Prevent `createAsStripeCustomer` when `stripe_id` is set ([#871](https://github.com/laravel/cashier-stripe/pull/871))

## [v10.5.3 (2020-01-14)](https://github.com/laravel/cashier-stripe/compare/v10.5.2...v10.5.3)

### Fixed

- Fix `findInvoiceOrFail` behavior ([#853](https://github.com/laravel/cashier-stripe/pull/853))

## [v10.5.2 (2020-01-07)](https://github.com/laravel/cashier-stripe/compare/v10.5.1...v10.5.2)

### Changed

- Assert customer exists before retrieving ([#834](https://github.com/laravel/cashier-stripe/pull/834))
- Simplify refund method ([#837](https://github.com/laravel/cashier-stripe/pull/837))

### Fixed

- Fix typo in exception message ([#846](https://github.com/laravel/cashier-stripe/pull/846))
- Fix overriding notification subject translation ([59faaf3](https://github.com/laravel/cashier-stripe/commit/59faaf3d6163de95fd977511121bcee695fb6bbe))

## [v10.5.1 (2019-11-26)](https://github.com/laravel/cashier-stripe/compare/v10.5.0...v10.5.1)

### Added

- Symfony 5 support ([#822](https://github.com/laravel/cashier-stripe/pull/822))

## [v10.5.0 (2019-11-12)](https://github.com/laravel/cashier-stripe/compare/v10.4.0...v10.5.0)

### Added

- Webhook events ([#810](https://github.com/laravel/cashier-stripe/pull/810))

### Fixed

- Add missing `@throws` tags ([#813](https://github.com/laravel/cashier-stripe/pull/813))
- Properly return `null` for find methods ([#817](https://github.com/laravel/cashier-stripe/pull/817))

## [v10.4.0 (2019-10-29)](https://github.com/laravel/cashier-stripe/compare/v10.3.0...v10.4.0)

### Added

- Add findPaymentMethod method ([#801](https://github.com/laravel/cashier-stripe/pull/801))
- Allow to set past due as active ([#802](https://github.com/laravel/cashier-stripe/pull/802))

## [v10.3.0 (2019-10-01)](https://github.com/laravel/cashier-stripe/compare/v10.2.1...v10.3.0)

### Added

- Configure Stripe logger ([#790](https://github.com/laravel/cashier-stripe/pull/790), [4a53b46](https://github.com/laravel/cashier-stripe/commit/4a53b4620ea5a082f3d6e69d881a971889e8c3eb))

### Changed

- Add language line for full name placeholder ([#782](https://github.com/laravel/cashier-stripe/pull/782))
- Update Stripe SDK to v7 ([#784](https://github.com/laravel/cashier-stripe/pull/784))
- Refactor handling of invalid webhook signatures ([#791](https://github.com/laravel/cashier-stripe/pull/791))
- Remove config repository dependency from webhook middleware ([#793](https://github.com/laravel/cashier-stripe/pull/793))

### Fixed

- Remove extra sign off from `ConfirmPayment` notification ([#779](https://github.com/laravel/cashier-stripe/pull/779))

## [v10.2.1 (2019-09-10)](https://github.com/laravel/cashier-stripe/compare/v10.2.0...v10.2.1)

### Fixed

- Ensure SVG icons are visible even with a long success or error message ([#772](https://github.com/laravel/cashier-stripe/pull/772))

## [v10.2.0 (2019-09-03)](https://github.com/laravel/cashier-stripe/compare/v10.1.1...v10.2.0)

### Added

- Add ability to ignore cashier routes ([#763](https://github.com/laravel/cashier-stripe/pull/763))

### Fixed

- Only mount card element if payment has not succeeded or been canceled ([#765](https://github.com/laravel/cashier-stripe/pull/765))
- Set off_session parameter to true when creating a new subscription ([#764](https://github.com/laravel/cashier-stripe/pull/764))

## [v10.1.1 (2019-08-27)](https://github.com/laravel/cashier-stripe/compare/v10.1.0...v10.1.1)

### Fixed

- Remove collation from migrations ([#761](https://github.com/laravel/cashier-stripe/pull/761))

## [v10.1.0 (2019-08-20)](https://github.com/laravel/cashier-stripe/compare/v10.0.0...v10.1.0)

### Added

- Multiple stripe accounts ([#754](https://github.com/laravel/cashier-stripe/pull/754))
- Set Stripe library info ([#756](https://github.com/laravel/cashier-stripe/pull/756))
- Paper size can be set in config file ([#752](https://github.com/laravel/cashier-stripe/pull/752), [cb837d1](https://github.com/laravel/cashier-stripe/commit/cb837d13f570353b85b27bd381e8669d1fee3491))

### Changed

- Update Stripe API version to `2019-08-14` ([#749](https://github.com/laravel/cashier-stripe/pull/749))

### Fixed

- `syncStripeStatus` trying to update incorrect status column ([#748](https://github.com/laravel/cashier-stripe/pull/748))

## [v10.0.0 (2019-08-13)](https://github.com/laravel/cashier-stripe/compare/v10.0.0-beta.2...v10.0.0)

### Added

- Allow hasIncompletePayment() to check other subscriptions than “default” ([#733](https://github.com/laravel/cashier-stripe/pull/733))
- Add indexes to those columns used to lookup data in the database ([#739](https://github.com/laravel/cashier-stripe/pull/739))

### Fixed

- Fixed a label with an incorrect for attribute ([#732](https://github.com/laravel/cashier-stripe/pull/732))

## [v10.0.0-beta.2 (2019-08-02)](https://github.com/laravel/cashier-stripe/compare/v10.0.0-beta...v10.0.0-beta.2)

### Added

- Add latestPayment method on Subscription ([#705](https://github.com/laravel/cashier-stripe/pull/705))
- Allow custom filename for invoice download ([#723](https://github.com/laravel/cashier-stripe/pull/723))

### Changed

- Improve stripe statuses ([#707](https://github.com/laravel/cashier-stripe/pull/707))
- Refactor active subscription state ([#712](https://github.com/laravel/cashier-stripe/pull/712))
- Return invoice object when applicable ([#711](https://github.com/laravel/cashier-stripe/pull/711))
- Refactor webhook responses ([#722](https://github.com/laravel/cashier-stripe/pull/722))
- Refactor confirm payment mail to notification ([#727](https://github.com/laravel/cashier-stripe/pull/727))

### Fixed

- Fix createSetupIntent ([#704](https://github.com/laravel/cashier-stripe/pull/704))
- Fix subscription invoicing ([#710](https://github.com/laravel/cashier-stripe/pull/710))
- Fix `null` return for `latestPayment` method ([#730](https://github.com/laravel/cashier-stripe/pull/730))

### Removed

- Remove unused `$customer` parameter on `updateQuantity` method ([#729](https://github.com/laravel/cashier-stripe/pull/729))

## [v10.0.0-beta (2019-07-17)](https://github.com/laravel/cashier-stripe/compare/v9.3.5...v10.0.0-beta)

Cashier 10.0 is a major release. Please review [the upgrade guide](UPGRADE.md) thoroughly.

## [v9.3.5 (2019-07-30)](https://github.com/laravel/cashier-stripe/compare/v9.3.4...v9.3.5)

### Changed

- Remove old 5.9 version constraints ([c7664fc](https://github.com/laravel/cashier-stripe/commit/c7664fc90d0310d6fa3a52bec45e94868bff995d))

### Fixed

- Don't try and find a user when stripeId is null ([#721](https://github.com/laravel/cashier-stripe/pull/721))

## [v9.3.4 (2019-07-29)](https://github.com/laravel/cashier-stripe/compare/v9.3.3...v9.3.4)

### Changed

- Updated version constraints for Laravel 6.0 ([4a4c5c2](https://github.com/laravel/cashier-stripe/commit/4a4c5c226bb98aa0726f57bb5970115d3eaab377))

## [v9.3.3 (2019-06-14)](https://github.com/laravel/cashier-stripe/compare/v9.3.2...v9.3.3)

### Fixed

- Fix hasStartingBalance and subtotal on `Invoice` ([#684](https://github.com/laravel/cashier-stripe/pull/684))

## [v9.3.2 (2019-06-04)](https://github.com/laravel/cashier-stripe/compare/v9.3.1...v9.3.2)

### Changed

- `VerifyWebhookSignature` is no longer `final` ([260de04](https://github.com/laravel/cashier-stripe/commit/260de0458fc76708f90eb955ddddef0ee6d68798))
- Remove strict type check for `trialUntil()` ([#678](https://github.com/laravel/cashier-stripe/pull/678))

## [v9.3.1 (2019-05-07)](https://github.com/laravel/cashier-stripe/compare/v9.3.0...v9.3.1)

### Fixed

- Fixing `defaultCard()` exception when user is not a Stripe customer ([#660](https://github.com/laravel/cashier-stripe/pull/660))

## [v9.3.0 (2019-04-16)](https://github.com/laravel/cashier-stripe/compare/v9.2.1...v9.3.0)

### Added

- Able to update a Stripe customer ([#634](https://github.com/laravel/cashier-stripe/pull/634))

### Fixed

- Handle incomplete subscriptions upon creation ([#631](https://github.com/laravel/cashier-stripe/pull/631))
- Handle card failure in plan swap ([#641](https://github.com/laravel/cashier-stripe/pull/641))

## [v9.2.1 (2019-03-19)](https://github.com/laravel/cashier-stripe/compare/v9.2.0...v9.2.1)

### Fixed

- Use new created property on invoice ([4714ba4](https://github.com/laravel/cashier-stripe/commit/4714ba4ad909092a61bfe2d0704b3fce6070ed5b))

## [v9.2.0 (2019-03-12)](https://github.com/laravel/cashier-stripe/compare/v9.1.0...v9.2.0)

### Added

- Add subscription state scopes ([#609](https://github.com/laravel/cashier-stripe/pull/609))

### Changed

- Test latest Stripe API version ([#611](https://github.com/laravel/cashier-stripe/pull/611))

## [v9.1.0 (2019-02-12)](https://github.com/laravel/cashier-stripe/compare/v9.0.1...v9.1.0)

### Added

- Laravel 5.8 support ([291f4b2](https://github.com/laravel/cashier-stripe/commit/291f4b217ddbbd8a641072d8476fb11805b9801f))

## [v9.0.1 (2019-02-03)](https://github.com/laravel/cashier-stripe/compare/v9.0.0...v9.0.1)

### Added

- Allow Carbon 2 installs ([a3b9d36](https://github.com/laravel/cashier-stripe/commit/a3b9d3688e21d3d9d3ae72ef58db585c80d96fa3))

### Changed

- Test against latest Stripe API version ([#603](https://github.com/laravel/cashier-stripe/pull/603))

### Fixed

- Correct PHP Doc @return tag ([#601](https://github.com/laravel/cashier-stripe/pull/601))

## [v9.0.0 (2018-12-17)](https://github.com/laravel/cashier-stripe/compare/v8.0.1...v9.0.0)

### Changed

- Removed support for PHP 7.0 ([#595](https://github.com/laravel/cashier-stripe/pull/595))
- Require Laravel 5.7 as minimum version ([#595](https://github.com/laravel/cashier-stripe/pull/595))
- Extract `updateCard` from `createAsStripeCustomer` method ([#588](https://github.com/laravel/cashier-stripe/pull/588))
- Remove `CASHIER_ENV` and event checks and encourage usage of `VerifyWebhookSignature` middleware ([#591](https://github.com/laravel/cashier-stripe/pull/591))
- The `invoice` method now accepts an `$options` param ([#598](https://github.com/laravel/cashier-stripe/pull/598))
- The `invoiceFor` method now accepts an `$invoiceOptions` param ([#598](https://github.com/laravel/cashier-stripe/pull/598))

### Fixed

- Fixed some DocBlocks ([#594](https://github.com/laravel/cashier-stripe/pull/594))
- Fixed a bug where the `swap` and `incrementAndInvoice` methods on the `Subscription` model would sometimes invoice other pending invoice items ([#598](https://github.com/laravel/cashier-stripe/pull/598))

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

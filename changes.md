# Cashier Change Log

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

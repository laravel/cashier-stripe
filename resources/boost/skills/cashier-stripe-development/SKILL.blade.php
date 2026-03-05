---
name: cashier-stripe-development
description: "Handles Laravel Cashier Stripe integration including subscriptions, webhooks, Stripe Checkout, invoices, and payment failure handling. Activates when installing Cashier, configuring billable models, setting up subscriptions or webhooks, handling SCA/3DS payment failures, working with Stripe Checkout or invoices, or when the user mentions Cashier, Billable, IncompletePayment, stripe_id, or Stripe subscriptions."
license: MIT
metadata:
  author: laravel
---
@php
/** @var \Laravel\Boost\Install\GuidelineAssist $assist */
@endphp

# Cashier Stripe Development

## When to Apply

Activate this skill when:

- Installing or configuring Laravel Cashier Stripe
- Setting up subscriptions, trials, or plan swapping
- Handling webhooks or SCA/3DS payment failures
- Working with Stripe Checkout, invoices, or charges
- Writing tests for billing functionality

## Documentation

Use `search-docs` for full method signatures and extended code examples.

## Setup

Publish migrations before the first `migrate` — the tag is `cashier-migrations`, not `cashier`:

```bash
{{ $assist->artisanCommand('vendor:publish --tag="cashier-migrations"') }}
{{ $assist->artisanCommand('migrate') }}
{{ $assist->artisanCommand('vendor:publish --tag="cashier-config"') }} # optional
```

Required `.env` keys — `CASHIER_CURRENCY` is commonly missed:

```
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
CASHIER_CURRENCY=usd
```

Add the `Billable` trait to the customer model:

@boostsnippet("Add Billable Trait", "php")
use Laravel\Cashier\Billable;

class User extends Authenticatable
{
    use Billable;
}
@endboostsnippet

For a non-User billable model, use `Cashier::useCustomerModel()` in a service provider (not a `CASHIER_MODEL` env var):

@boostsnippet("Custom Billable Model", "php")
use Laravel\Cashier\Cashier;

Cashier::useCustomerModel(Team::class);
@endboostsnippet

## Subscriptions

@boostsnippet("Create Subscription", "php")
$user->newSubscription('default', 'price_xxxx')->create($paymentMethodId);
@endboostsnippet

The first argument is the internal subscription type (`'default'` is conventional). The second is the Stripe Price ID.

Status reference:

| Method | Returns true when |
|---|---|
| `$user->subscribed('default')` | Active or on grace period |
| `->onTrial()` | Trial period active |
| `->onGracePeriod()` | Canceled, period not yet ended |
| `->canceled()` | `ends_at` set — may still have access |
| `->ended()` | Canceled AND grace period expired |
| `->incomplete()` | Awaiting SCA/3DS confirmation |
| `->pastDue()` | Payment overdue |

`subscribed()` returns `false` for `incomplete` and `past_due` by default.

### SCA / 3DS — Incomplete Payments

Always wrap subscription creation to catch 3DS challenges:

@boostsnippet("Handle Incomplete Payment", "php")
use Laravel\Cashier\Exceptions\IncompletePayment;

try {
    $user->newSubscription('default', 'price_xxxx')->create($paymentMethodId);
} catch (IncompletePayment $e) {
    return redirect()->route('cashier.payment', [$e->payment->id, 'redirect' => route('home')]);
}
@endboostsnippet

The `cashier.payment` route is auto-registered and renders a built-in 3DS confirmation page.

## Webhooks

Cashier auto-registers `POST /stripe/webhook` (named `cashier.webhook`). Exclude it from CSRF:

@boostsnippet("CSRF Exclusion (Laravel 11+)", "php")
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: ['stripe/*']);
})
@endboostsnippet

For local dev, forward events with the Stripe CLI:

```bash
stripe listen --forward-to localhost/stripe/webhook
```

**Critical:** The CLI prints its own `whsec_...` signing secret — it is different from the Dashboard endpoint secret. Use the CLI secret as `STRIPE_WEBHOOK_SECRET` locally; use the Dashboard secret in production.

### Custom Handlers

Extend `WebhookController` — method name is `handle` + StudlyCase of the event type:

@boostsnippet("Extend WebhookController", "php")
use Laravel\Cashier\Http\Controllers\WebhookController as CashierController;

class StripeWebhookController extends CashierController
{
    // invoice.payment_succeeded → handleInvoicePaymentSucceeded
    public function handleInvoicePaymentSucceeded(array $payload)
    {
        // your logic
    }
}
@endboostsnippet

Call `Cashier::ignoreRoutes()` in a service provider and register your controller manually, or listen to events without touching routes:

@boostsnippet("Listen to Webhook Events", "php")
use Laravel\Cashier\Events\WebhookReceived;

Event::listen(WebhookReceived::class, function (WebhookReceived $event) {
    if ($event->payload['type'] === 'invoice.payment_succeeded') {
        // handle renewal
    }
});
@endboostsnippet

## Common Pitfalls

- **Wrong publish tag**: use `cashier-migrations`, not `cashier`
- **Missing `CASHIER_CURRENCY`**: defaults to USD — non-US apps must set this explicitly
- **CLI secret ≠ Dashboard secret**: mixing them causes signature verification failures (419/403)
- **CSRF not excluded**: webhook POSTs are rejected with 419 without `stripe/*` exclusion
- **`canceled()` ≠ ended**: `canceled()` is true during the grace period; use `ended()` to confirm access is revoked
- **`Cashier::ignoreRoutes()` required**: when extending `WebhookController`, call this in a service provider to avoid duplicate route registration
- **Custom model registration**: use `Cashier::useCustomerModel()`, not a `CASHIER_MODEL` env var
- **Trial dates stored locally**: `trial_ends_at` syncs via webhooks — stale if webhooks are not configured

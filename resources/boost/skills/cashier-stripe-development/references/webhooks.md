# Webhooks Reference

Use `search-docs` for authoritative documentation on webhooks.

## Auto-Registered Routes

Cashier registers two routes automatically under the `cashier.path` prefix (default `stripe`):

- `POST /stripe/webhook` named `cashier.webhook`
- `GET /stripe/payment/{id}` named `cashier.payment`

## CSRF Exclusion

**Laravel 11+ (`bootstrap/app.php`):**

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: ['stripe/*']);
})
```

**Laravel 10 (`app/Http/Middleware/VerifyCsrfToken.php`):**

```php
protected $except = [
    'stripe/*',
];
```

## Local Development with Stripe CLI

```bash
stripe login
stripe listen --forward-to your-app.test/stripe/webhook
stripe trigger invoice.payment_succeeded
```

The CLI outputs a `whsec_...` signing secret specific to that session. Set it as `STRIPE_WEBHOOK_SECRET` locally. It is not the same as the Dashboard endpoint secret.

## Registering Events in the Stripe Dashboard

Use the Artisan command to create the endpoint automatically with all required events:

```bash
php artisan cashier:webhook
```

Key events Cashier handles internally:

- `customer.subscription.created`
- `customer.subscription.updated`
- `customer.subscription.deleted`
- `customer.updated` / `customer.deleted`
- `invoice.payment_action_required`
- `invoice.payment_succeeded`
- `payment_method.automatically_updated`

## Custom Handlers: Extending WebhookController

Method name pattern: `handle` + StudlyCase of event type with dots replaced by underscores.

`invoice.payment_succeeded` becomes `handleInvoicePaymentSucceeded`.

```php
use Laravel\Cashier\Http\Controllers\WebhookController as CashierController;

class StripeWebhookController extends CashierController
{
    public function handleInvoicePaymentSucceeded(array $payload)
    {
        // your logic, call parent:: to keep Cashier's default behavior
    }
}
```

In a service provider, disable auto-registration and point to your controller:

```php
Cashier::ignoreRoutes();
```

```php
// routes/web.php
Route::post('stripe/webhook', [StripeWebhookController::class, 'handleWebhook']);
```

## Custom Handlers: Listening to Events

The simpler option when you do not need to replace Cashier's internal logic:

```php
use Laravel\Cashier\Events\WebhookReceived;
use Laravel\Cashier\Events\WebhookHandled;

// WebhookReceived fires for every event before Cashier processes it
// WebhookHandled fires after Cashier processes it

Event::listen(WebhookReceived::class, function (WebhookReceived $event) {
    if ($event->payload['type'] === 'invoice.payment_succeeded') {
        // handle renewal
    }
});
```

## Signature Verification

`VerifyWebhookSignature` middleware is applied automatically when `cashier.webhook.secret` is set. No extra wiring is needed.

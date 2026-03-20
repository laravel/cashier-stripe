# Flexible Billing

- [Introduction](#introduction)
- [Installation](#installation)
- [Getting Started](#getting-started)
    - [Setting the Global Default](#setting-the-global-default)
    - [Per-Subscription Override](#per-subscription-override)
- [Creating Flexible Subscriptions](#creating-flexible-subscriptions)
    - [Basic Subscription](#basic-subscription)
    - [With a Trial Period](#with-a-trial-period)
    - [Via Checkout](#via-checkout)
    - [Checking Billing Mode](#checking-billing-mode)
- [Hybrid Billing](#hybrid-billing)
    - [Fixed + Metered](#fixed--metered)
    - [Removing a Metered Price](#removing-a-metered-price)
    - [Adding a Metered Price Later](#adding-a-metered-price-later)
- [Proration Discounts](#proration-discounts)
- [Swapping Plans](#swapping-plans)
- [Cancel & Resume](#cancel--resume)
- [Subscription Schedules](#subscription-schedules)
    - [Creating a Schedule](#creating-a-schedule)
    - [From an Existing Subscription](#from-an-existing-subscription)
    - [Schedule Operations](#schedule-operations)
    - [Querying Schedules](#querying-schedules)
    - [End Behavior](#end-behavior)
- [Quotes](#quotes)
    - [Creating a Quote](#creating-a-quote)
    - [Quote Lifecycle](#quote-lifecycle)
    - [Download PDF](#download-pdf)
    - [Querying Quotes](#querying-quotes)
- [Billing Credits](#billing-credits)
    - [Adding Credits](#adding-credits)
    - [Checking Balance](#checking-balance)
    - [Calculating Credit Application](#calculating-credit-application)
    - [Deducting Credits](#deducting-credits)
- [Metered Usage Reporting](#metered-usage-reporting)
- [Usage Thresholds](#usage-thresholds)
- [Rate Cards](#rate-cards)
    - [Tiered Pricing](#tiered-pricing)
    - [Package Pricing](#package-pricing)
    - [Flat Rate Pricing](#flat-rate-pricing)
- [Migrating from Classic to Flexible](#migrating-from-classic-to-flexible)
    - [Individual Subscription](#individual-subscription)
    - [Migration Strategy](#migration-strategy)
    - [Safety Guards](#safety-guards)
- [Webhook Events](#webhook-events)
- [Configuration Reference](#configuration-reference)
- [Chaining Examples](#chaining-examples)

## Introduction

Flexible billing is Stripe's modern billing mode that supports usage-based pricing, hybrid subscriptions, improved proration handling, and advanced subscription lifecycle management. Starting with Stripe API version `2025-09-30.clover`, flexible billing is the default for new subscriptions.

This guide covers everything added by the flexible billing feature set in Laravel Cashier.

## Installation

Publish and run the Cashier migrations:

```bash
php artisan vendor:publish --tag=cashier-migrations
php artisan migrate
```

This creates the standard Cashier tables plus:

| Table | Purpose |
|-------|---------|
| `subscription_schedules` | Multi-phase subscription lifecycle management |
| `cashier_quotes` | Quote tracking and lifecycle |
| `cashier_usage_thresholds` | Usage monitoring against configurable limits |
| `cashier_rate_cards` | Local pricing model definitions |

Ensure your User model uses the `Billable` trait:

```php
use Laravel\Cashier\Billable;

class User extends Authenticatable
{
    use Billable;
}
```

## Getting Started

### Setting the Global Default

Enable flexible billing for your entire application with one line in your `AppServiceProvider`:

```php
use Laravel\Cashier\Cashier;

public function boot(): void
{
    Cashier::defaultBillingMode('flexible');
}
```

Every new subscription will use flexible billing automatically. Existing subscriptions are unaffected.

### Per-Subscription Override

Opt in (or out) on individual subscriptions:

```php
// Flexible for this subscription
$user->newSubscription('default', $priceId)
    ->withBillingMode('flexible')
    ->create($paymentMethod);

// Classic for this subscription (overrides a flexible global default)
$user->newSubscription('legacy', $priceId)
    ->withBillingMode('classic')
    ->create($paymentMethod);
```

## Creating Flexible Subscriptions

### Basic Subscription

```php
$subscription = $user->newSubscription('default', 'price_monthly')
    ->withBillingMode('flexible')
    ->create('pm_card_visa');
```

### With a Trial Period

```php
$subscription = $user->newSubscription('default', 'price_monthly')
    ->withBillingMode('flexible')
    ->trialDays(14)
    ->create('pm_card_visa');
```

### Via Checkout

```php
$checkout = $user->newSubscription('default', 'price_monthly')
    ->withBillingMode('flexible')
    ->checkout([
        'success_url' => route('billing.success'),
        'cancel_url' => route('billing.cancel'),
    ]);

return redirect($checkout->url);
```

The `billing_mode` is automatically included in the Checkout session's `subscription_data`.

### Checking Billing Mode

```php
if ($subscription->usesFlexibleBilling()) {
    // This subscription uses flexible billing
}
```

## Hybrid Billing

### Fixed + Metered

Combine a fixed monthly fee with usage-based charges on a single subscription:

```php
$subscription = $user->newSubscription('default')
    ->price('price_base_plan')           // $29/mo fixed
    ->meteredPrice('price_api_calls')    // $0.01 per API call
    ->withBillingMode('flexible')
    ->create('pm_card_visa');
```

This creates one subscription with two items. The base plan charges monthly, while the metered price charges based on reported usage.

### Removing a Metered Price

In flexible billing mode, metered prices can be removed without `clear_usage` errors:

```php
$subscription->removePrice('price_api_calls');
```

In classic mode, Stripe requires `clear_usage: true` when removing metered prices, which can cause errors. Flexible billing handles this automatically.

### Adding a Metered Price Later

```php
$subscription->addMeteredPrice('price_storage_gb');
```

## Proration Discounts

Control how mid-cycle prorations and discounts appear on invoices.

**Itemized** — discount amounts shown as separate line items for full transparency:

```php
$subscription = $user->newSubscription('default', $priceId)
    ->withBillingMode('flexible')
    ->withProrationDiscounts('itemized')
    ->create('pm_card_visa');
```

**Included** — amounts are net of discounts (traditional behavior):

```php
$subscription = $user->newSubscription('default', $priceId)
    ->withBillingMode('flexible')
    ->withProrationDiscounts('included')
    ->create('pm_card_visa');
```

## Swapping Plans

Plan swaps preserve the billing mode automatically:

```php
$subscription->swap('price_premium');

$subscription->usesFlexibleBilling(); // still true
```

Multiple swaps in sequence work the same way:

```php
$subscription->swap('price_starter');    // downgrade
$subscription->swap('price_enterprise'); // upgrade
// billing mode is preserved through all swaps
```

## Cancel & Resume

Grace periods and resumption work identically to classic mode. The billing mode is preserved:

```php
$subscription->cancel();

$subscription->onGracePeriod();  // true
$subscription->valid();          // true — still usable until period ends

$subscription->resume();

$subscription->active();                // true
$subscription->usesFlexibleBilling();   // true
```

Cancel immediately:

```php
$subscription->cancelNow();
$subscription->cancelNowAndInvoice();
```

## Subscription Schedules

Pre-plan subscription transitions with multi-phase schedules.

### Creating a Schedule

```php
$schedule = $user->newSubscriptionSchedule('default')
    ->withBillingMode('flexible')
    ->addPhase([
        ['price' => 'price_starter', 'quantity' => 1],
    ], ['iterations' => 3])
    ->addPhase([
        ['price' => 'price_pro', 'quantity' => 1],
    ], ['iterations' => 12])
    ->startDate('now')
    ->create();
```

The `billing_mode` is set at the schedule level (not per-phase), consistent with the Stripe API.

### From an Existing Subscription

```php
$schedule = $user->newSubscriptionSchedule('default')
    ->createFromSubscription($subscription);
```

> **Important:** The schedule inherits the subscription's billing mode. Do not call `withBillingMode()` — Stripe will reject the request if you set it explicitly.

### Schedule Operations

```php
$schedule->active();      // currently running
$schedule->notStarted();  // hasn't begun
$schedule->completed();   // all phases finished
$schedule->released();    // detached from subscription
$schedule->canceled();    // was canceled

$schedule->phases();       // array of phase objects
$schedule->currentPhase(); // current phase object or null

$schedule->release();  // detach — subscription continues independently
$schedule->cancel();   // cancel the schedule and subscription
$schedule->updateSchedule(['end_behavior' => 'cancel']);
```

### Querying Schedules

```php
$user->subscriptionSchedules;                          // all schedules
$user->subscriptionSchedule('default');                // by type
$user->findSubscriptionSchedule('sub_sched_xxx');     // by Stripe ID
```

### End Behavior

```php
$schedule = $user->newSubscriptionSchedule('default')
    ->endBehavior('release')   // subscription continues (default)
    ->endBehavior('cancel')    // subscription is canceled
    ->endBehavior('none')      // no action
    ->addPhase([...])
    ->create();
```

## Quotes

Generate formal quotes for sales workflows.

### Creating a Quote

```php
$quote = $user->newQuote()
    ->addLineItem('price_enterprise', 1)
    ->addLineItem('price_support', 1)
    ->description('Annual enterprise commitment')
    ->header('Acme Corp')
    ->footer('Valid for 30 days')
    ->expiresAt(now()->addDays(30))
    ->withMetadata(['sales_rep' => 'jane'])
    ->withBillingMode('flexible')
    ->create();
```

### Quote Lifecycle

```php
$quote->finalize();  // Draft → Open
$quote->accept();    // Open → Accepted (creates subscription)
$quote->cancel();    // Open → Canceled
```

Check status:

```php
$quote->draft();
$quote->open();
$quote->accepted();
$quote->canceled();
```

### Download PDF

```php
return $quote->downloadPdf();
return $quote->downloadPdf('proposal.pdf');
```

### Querying Quotes

```php
$user->quotes;
$user->findQuote('qt_xxx');
```

## Billing Credits

Manage customer credit balances for promotional credits, refunds, or prepaid usage.

### Adding Credits

```php
$user->addBillingCredits(5000, 'Welcome bonus');  // $50.00
```

This is a convenience alias for the existing `creditBalance()` method.

### Checking Balance

```php
$user->availableCredits();           // 5000 (cents)
$user->hasSufficientCredits(3000);   // true
```

### Calculating Credit Application

Preview how credits would cover a charge without modifying the balance:

```php
$result = $user->calculateCreditApplication(8000);

// [
//     'applied_credits' => 5000,
//     'remaining_usage' => 3000,
//     'credits_after'   => 0,
// ]
```

### Deducting Credits

```php
$user->deductBillingCredits(2000, 'Usage charge');  // $20.00
```

## Metered Usage Reporting

> **Important: Meters are customer-level, not subscription-level.** In flexible billing mode, usage products require meters. Meters aggregate usage across the **entire customer**, not per subscription. If a customer has multiple subscriptions with the same metered price, reported usage aggregates across all of them and overages are charged on all subscriptions. Design your meters with this in mind — use distinct meter event names if you need per-subscription usage tracking.

```php
$user->reportMeterEvent('api_calls');         // 1 event
$user->reportMeterEvent('api_calls', 100);    // 100 events
```

Get usage summaries:

```php
$summaries = $user->meterEventSummaries(
    meterId: 'meter_xxx',
    startTime: now()->subMonth()->getTimestamp(),
);

$total = $summaries->sum('aggregated_value');
```

## Usage Thresholds

Monitor usage against limits. Stored in the database for reliability.

```php
$user->setUsageThreshold('meter_api_calls', 10000, 'billing_cycle', [
    'alert_email' => 'billing@company.com',
]);

$threshold = $user->getUsageThreshold('meter_api_calls');
$threshold->isExceeded(15000);      // true
$threshold->usagePercentage(7500);  // 75.0
$threshold->overage(12000);         // 2000

$user->removeUsageThreshold('meter_api_calls');
```

Valid periods: `billing_cycle`, `monthly`, `daily`, `weekly`.

## Rate Cards

Model pricing locally for display or estimation without Stripe API calls.

### Tiered Pricing

**Graduated** — each tier prices only usage within its range:

```php
$card = RateCard::create([
    'name' => 'API Calls',
    'product_id' => 'prod_xxx',
    'pricing_type' => 'tiered',
    'rates' => [
        'mode' => 'graduated',
        'tiers' => [
            ['up_to' => 1000,  'unit_amount' => 10, 'flat_amount' => 0],
            ['up_to' => 10000, 'unit_amount' => 5,  'flat_amount' => 0],
            ['up_to' => null,  'unit_amount' => 2,  'flat_amount' => 0],
        ],
    ],
    'currency' => 'usd',
]);

$card->calculatePricing(15000);
// $65.00 = (1000 × $0.10) + (9000 × $0.05) + (5000 × $0.02)
```

**Volume** — all units at the tier the total falls into:

```php
$card = RateCard::create([
    'pricing_type' => 'tiered',
    'rates' => ['mode' => 'volume', 'tiers' => [...]],
    // ...
]);

$card->calculatePricing(500);
// All 500 at the tier for 101–1000
```

### Package Pricing

```php
$card = RateCard::create([
    'pricing_type' => 'package',
    'rates' => ['package_size' => 1000, 'package_price' => 500],
    // ...
]);

$card->calculatePricing(2500);
// ceil(2500 / 1000) = 3 packages × $5.00 = $15.00
```

### Flat Rate Pricing

```php
$card = RateCard::create([
    'pricing_type' => 'flat',
    'rates' => ['unit_amount' => 1],
    // ...
]);

$card->calculatePricing(50000);
// 50,000 × $0.01 = $500.00
```

Query and manage:

```php
RateCard::active()->forProduct('prod_xxx')->get();
$card->deactivate();
```

## Migrating from Classic to Flexible

> **This is a one-way operation.** A subscription cannot be migrated back to classic mode.

### Individual Subscription

```php
$subscription->migrateToFlexibleBillingMode();
```

Uses Stripe's dedicated `POST /v1/subscriptions/{id}/migrate` endpoint. The subscription continues running without interruption.

### Migration Strategy

1. **New subscriptions:** Set `Cashier::defaultBillingMode('flexible')` in your service provider
2. **Existing subscriptions:** Migrate individually with `$subscription->migrateToFlexibleBillingMode()`
3. **Check mode:** Use `$subscription->usesFlexibleBilling()` to determine which mode a subscription uses

### Safety Guards

```php
// Already flexible — returns immediately without an API call
$subscription->migrateToFlexibleBillingMode();

// Incomplete — throws SubscriptionUpdateFailure
// Canceled — throws LogicException
```

## Webhook Events

Register these events in `config/cashier.php` to receive them:

```php
'webhook' => [
    'events' => [
        // Existing events...
        'subscription_schedule.created',
        'subscription_schedule.updated',
        'subscription_schedule.canceled',
        'subscription_schedule.completed',
        'subscription_schedule.released',
        'quote.finalized',
        'quote.accepted',
        'quote.canceled',
    ],
],
```

| Event | Handler |
|-------|---------|
| `subscription_schedule.created` | Creates local schedule record |
| `subscription_schedule.updated` | Syncs status, phases, subscription ID |
| `subscription_schedule.canceled` | Sets canceled status and timestamp |
| `subscription_schedule.completed` | Sets completed status and timestamp |
| `subscription_schedule.released` | Sets released status and timestamp |
| `quote.finalized` | Updates status, number, amounts |
| `quote.accepted` | Sets accepted status and timestamp |
| `quote.canceled` | Sets canceled status and timestamp |

## Configuration Reference

| Method | Scope | Description |
|--------|-------|-------------|
| `Cashier::defaultBillingMode('flexible')` | Global | All new subscriptions use flexible |
| `->withBillingMode('flexible')` | Per-builder | Override for one subscription/schedule/quote |
| `->withProrationDiscounts('itemized')` | Per-builder | Control proration display |
| `->migrateToFlexibleBillingMode()` | Per-subscription | One-way migration from classic |
| `->usesFlexibleBilling()` | Per-subscription | Check current billing mode |

**Incompatibilities:**

- `withBillingThresholds()` cannot be combined with flexible billing mode
- `billing_mode` cannot be set via subscription update — only at creation or via `/migrate`
- `createFromSubscription()` inherits billing mode — do not set it explicitly

**Stripe API Version:** Requires `2025-06-30.basil` or later. Included in `stripe-php` v17.4.0+.

## Chaining Examples

Every builder returns `$this`, so you can chain everything together. Here are some real-world examples.

### SaaS with Usage Overages

```php
$subscription = $user->newSubscription('default')
    ->price('price_pro_monthly')
    ->meteredPrice('price_api_calls')
    ->meteredPrice('price_storage_gb')
    ->withBillingMode('flexible')
    ->withProrationDiscounts('itemized')
    ->withMetadata(['plan' => 'pro', 'source' => 'upgrade-page'])
    ->trialDays(14)
    ->create($paymentMethod);
```

### Enterprise Onboarding Schedule

```php
$schedule = $user->newSubscriptionSchedule('enterprise')
    ->withBillingMode('flexible')
    ->withMetadata(['sales_rep' => 'jane', 'deal_id' => 'D-1234'])
    ->withDefaultSettings(['collection_method' => 'send_invoice'])
    ->addPhase([
        ['price' => 'price_onboarding', 'quantity' => 1],
    ], ['iterations' => 1])
    ->addPhase([
        ['price' => 'price_enterprise_annual', 'quantity' => 1],
    ], ['iterations' => 12])
    ->endBehavior('cancel')
    ->startDate(now()->addDays(7))
    ->create();
```

### B2B Quote with Discounts

```php
$quote = $user->newQuote()
    ->addLineItem('price_enterprise', 5)
    ->addLineItem('price_premium_support', 1)
    ->withBillingMode('flexible')
    ->withCoupon('ENTERPRISE20')
    ->description('Enterprise license — 5 seats + premium support')
    ->header('Proposal for Acme Corp')
    ->footer('Terms: Net 30. Valid for 14 days.')
    ->expiresAt(now()->addDays(14))
    ->withMetadata(['deal_size' => 'large', 'region' => 'emea'])
    ->create();
```

### Checkout with Flexible Billing

```php
return $user->newSubscription('default', 'price_pro')
    ->withBillingMode('flexible')
    ->withProrationDiscounts('itemized')
    ->allowPromotionCodes()
    ->trialDays(7)
    ->checkout([
        'success_url' => route('billing.success') . '?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => route('billing.cancel'),
    ]);
```

### Migrate, Then Schedule an Upgrade

```php
// Step 1: Migrate existing subscription to flexible
$subscription->migrateToFlexibleBillingMode();

// Step 2: Create a schedule to upgrade them next month
$schedule = $user->newSubscriptionSchedule('default')
    ->createFromSubscription($subscription);

$schedule->updateSchedule([
    'phases' => [
        [
            'items' => [['price' => 'price_current', 'quantity' => 1]],
            'end_date' => now()->addMonth()->getTimestamp(),
        ],
        [
            'items' => [['price' => 'price_premium', 'quantity' => 1]],
            'iterations' => 12,
        ],
    ],
]);
```

### Credits + Usage Threshold Workflow

```php
// Set up monitoring
$user->setUsageThreshold('meter_api_calls', 10000, 'billing_cycle', [
    'alert_email' => $user->email,
]);

// Issue welcome credits
$user->addBillingCredits(5000, 'Welcome bonus — first 5,000 API calls free');

// Later, check if they're approaching their limit
$threshold = $user->getUsageThreshold('meter_api_calls');
$percentage = $threshold->usagePercentage($currentUsage);

if ($percentage >= 80) {
    // Notify them they're at 80% of their threshold
}

if ($threshold->isExceeded($currentUsage)) {
    $overage = $threshold->overage($currentUsage);
    // Calculate overage charges or suggest an upgrade
}
```

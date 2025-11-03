# Fix subscription status validation for canceled subscriptions

**Target Branch**: `15.x` (bug fix for currently supported version)

## Description

This PR fixes a critical bug where subscription status validation methods (`valid()`, `active()`, `onTrial()`, `onGracePeriod()`, `canceled()`) did not properly check the `stripe_status` field when subscriptions are canceled via Stripe's API (e.g., when a test clock is canceled) or when subscriptions are immediately canceled during trial periods.

## Problems Fixed

### Issue 1: Test Clock Cancellation
When a Stripe test clock is canceled, Stripe automatically cancels all associated subscriptions and sets their status to `canceled`. The webhook correctly updates `stripe_status` to `STATUS_CANCELED`, but the validation methods did not respect this status:

- `canceled()` only checked `ends_at` and ignored `stripe_status`
- `active()`, `onTrial()`, and `onGracePeriod()` did not check for `STATUS_CANCELED`
- `valid()` would incorrectly return `true` for canceled subscriptions

**Reproduction Steps:**
1. Create a subscription
2. Advance a Stripe test clock
3. Cancel the test clock
4. Retrieve the subscription → `valid()` incorrectly returns `true`

### Issue 2: Immediate Cancellation During Trial
When a subscription with an active trial period is canceled immediately using `cancelNow()`, the subscription would still be considered valid because `onTrial()` only checked if `trial_ends_at` was in the future, without verifying if the subscription had actually ended.

**Reproduction Steps:**
1. Create a subscription with trial: `$user->newSubscription('default', $price)->trialUntil('tomorrow')->add()`
2. Cancel immediately: `$user->subscription('default')->cancelNow()`
3. Check subscription: `$user->subscribed('default')` incorrectly returns `true`

## Solution

Updated all subscription status validation methods to properly check `stripe_status === STATUS_CANCELED`:

1. **`canceled()`**: Now returns `true` if either `ends_at` is set OR `stripe_status === STATUS_CANCELED`
2. **`active()`**: Added explicit check for `stripe_status !== STATUS_CANCELED`
3. **`onTrial()`**: Now returns `false` if `stripe_status === STATUS_CANCELED` OR if `ends_at` is set and not in the future
4. **`onGracePeriod()`**: Now returns `false` if `stripe_status === STATUS_CANCELED` (grace period only applies when `ends_at` is in the future AND status is not canceled)

## Changes Made

### Code Changes (`src/Subscription.php`)

- **`canceled()` method** (line 304-307): Added check for `stripe_status === STATUS_CANCELED`
- **`active()` method** (line 229-237): Added explicit check for `stripe_status !== STATUS_CANCELED`
- **`onTrial()` method** (line 357-363): Added checks for `stripe_status !== STATUS_CANCELED` and `ends_at` not being in the past
- **`onGracePeriod()` method** (line 410-413): Added check for `stripe_status !== STATUS_CANCELED`

### Tests Added (`tests/Unit/SubscriptionTest.php`)

Added 17 comprehensive test cases covering:

- `canceled()` behavior with `STATUS_CANCELED` status (with and without `ends_at`)
- `active()` behavior with `STATUS_CANCELED` status
- `onTrial()` behavior with `STATUS_CANCELED` status and past `ends_at`
- `onGracePeriod()` behavior with `STATUS_CANCELED` status
- `valid()` behavior with `STATUS_CANCELED` status
- `ended()` behavior with `STATUS_CANCELED` status
- Grace period behavior with future `ends_at` and active status
- Trial period behavior when subscription is canceled immediately

## Benefits to End Users

1. **Correct Subscription Validation**: Subscriptions canceled via Stripe's API (including test clock cancellations) are now correctly identified as invalid
2. **Prevents Access Issues**: Users won't incorrectly retain access to features after their subscription is canceled
3. **Stripe Compliance**: The implementation now correctly aligns with Stripe's subscription status model
4. **Reliable Trial Handling**: Trial subscriptions canceled immediately are now properly invalidated

## Backward Compatibility

✅ **No breaking changes**: This fix maintains full backward compatibility:
- Subscriptions scheduled for cancellation (future `ends_at`) still work correctly
- Grace period behavior is preserved for subscriptions with future `ends_at` and active status
- All existing functionality continues to work as expected

## Testing

All new tests pass, and existing tests continue to pass, ensuring no regressions were introduced.

## Related Issues

Fixes subscription status validation issues related to:
- Stripe test clock cancellation scenarios
- Immediate cancellation of trial subscriptions
- Proper handling of `STATUS_CANCELED` status from Stripe webhooks


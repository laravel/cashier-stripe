# Laravel Cashier

- [Configuration](#configuration)
- [Subscribing To A Plan](#subscribing-to-a-plan)
- [No Card Up Front](#no-card-up-front)
- [Swapping Subscriptions](#swapping-subscriptions)
- [Cancelling A Subscription](#cancelling-a-subscription)
- [Resuming A Subscription](#resuming-a-subscription)
- [Checking Subscription Status](#checking-subscription-status)
- [Invoices](#invoices)

<a name="configuration"></a>
## Configuration

> **Note:** Because of its use of traits, Cashier requires PHP 5.4 or greater.

Laravel Cashier provides a fluent interface to [Stripe's](https://stripe.com) subscription billing services.

First, add the Cashier package to your `composer.json` file:

	"laravel/cashier": "~1.0"

To use Cashier, we'll need to add several columns to your database. Don't worry, you can use the `cashier:table` Artisan command to create a migration to add the necessary column. Once the migration has been created, simply run the `migrate` command.

Next, add the BillableTrait and appropriate date mutators to your model definition:

```php
use Laravel\Cashier\BillableTrait;

class User extends Eloquent {

	use BillableTrait;

	protected $dates = ['trial_ends_at', 'subscription_ends_at'];

}
```

Finally, set your Stripe key in one of your bootstrap files:

```php
User::setStripeKey('stripe-key');
```

<a name="subscribing-to-a-plan"></a>
## Subscribing To A Plan

Once you have a model instance, you can easily subscribe that user to a given Stripe plane:

```php
$user = User::find(1);

$user->subscription('monthly')->create($creditCardToken);
```

If you would like to apply a coupon when creating the subscription, you may use the `withCoupon` method:

```php
$user->subscription('monthly')
     ->withCoupon('code')
     ->create($creditCardToken);
```

The `subscription` method will automatically create the Stripe subscription, as well as update your database with Stripe customer ID and other relevant billing information. If your plan includes a trial, the trial end date will also automatically be set on the user record.

If your plan has a trial period, make sure to set the trial end date on your model after subscribing:

```php
$user->trial_ends_at = Carbon::now()->addDays(14);

$user->save();
```

<a name="no-card-up-front"></a>
## No Card Up Front

If your application offers a free-trial with no credit-card up front, set the `cardUpFront` property on your model to `false`:

```php
protected $cardUpFront = false;
```

On account creation, be sure to set the trial end date on the model:

```php
$user->trial_ends_at = Carbon::now()->addDays(14);

$user->save();
```

<a name="swapping-subscriptions"></a>
## Swapping Subscriptions

To swap a user to a new subscription, use the `swap` method:

```php
$user->subscription('premium')->swap();
```

If the user is on trial, the trial will be maintained as normal. Also, if a "quantity" exists for the subscription, that quantity will also be maintained.

<a name="cancelling-a-subscription"></a>
## Cancelling A Subscription

Cancelling a subscription is a walk in the park:

```php
$user->subscription()->cancel();
```

When a subscription is cancelled, Cashier will automatically set the `subscription_ends_at` column on your database. This column is used to know when the `subscribed` method should begin returning `false`. For example, if a customer cancels a subscription on March 1st, but the subscription was not scheduled to end until March 5th, the `subscribed` method will continue to return `true` until March 5th.

<a name="resuming-a-subscription"></a>
## Resuming A Subscription

If a user has cancelled their subscription and you wish to resume it, use the `resume` method:

```php
$user->subscription('monthly')->resume($creditCardToken);
```

If the user cancels a subscription and then resumes that subscription before the subscription has fully expired, they will not be billed immediately. Their subscription will simply be re-activated, and they will be billed on the original billing cycle.

<a name="checking-subscription-status"></a>
## Checking Subscription Status

To verify that a user is subscribed to your application, use the `subscribed` command:

```php
if ($user->subscribed())
{
	//
}
```

The `subscribed` method makes a great candidate for a route filter:

```php
Route::filter('subscribed', function()
{
	if (Auth::user() && ! Auth::user()->subscribed())
	{
		return Redirect::to('billing');
	}
});
```

You may also determine if the user is still within their trial period (if applicable) using the `onTrial` method:

```php
if ($user->onTrial())
{
	//
}
```

To determine if the user was once an active subscriber, but has cancelled their subscription, you may use the `cancelled` method:

```php
if ($user->cancelled())
{
	//
}
```

You may also determine if a user has cancelled their subscription, but are still on their "grace period" until the subscription fully expires. For example, if a user cancels a subscription on March 5th that was scheduled to end on March 10th, the user is on their "grace period" until March 10th. Note that the `subscribed` method still returns `true` during this time.

```php
if ($user->onGracePeriod())
{
	//
}
```

The `everSubscribed` method may be used to determine if the user has ever subscribed to a plan in your application:

```php
if ($user->everSubscribed())
{
	//
}
```

<a name="invoices"></a>
## Invoices

You can easily retrieve an array of a user's invoices using the `invoices` method:

```php
$invoices = $user->invoices();
```

When listing the invoices for the customer, you may use these helper methods to display the relevant invoice information:

```php
{{ $invoice->id }}

{{ $invoice->dateString() }}

{{ $invoice->dollars() }}
```

Use the `downloadInvoice` method to generate a PDF download of the invoice. Yes, it's really this easy:

```php
return $user->downloadInvoice($invoice->id, [
	'vendor'  => 'Your Company',
	'product' => 'Your Product',
]);
```
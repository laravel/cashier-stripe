<?php
// > vendor\bin\phpunit vendor\laravel\cashier\tests
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Laravel\Cashier\Http\Controllers\WebhookController;

class CashierTest extends PHPUnit_Framework_TestCase
{
    public static function setUpBeforeClass()
    {
        if (file_exists(__DIR__.'/../.env')) {
            $dotenv = new Dotenv\Dotenv(__DIR__.'/../');
            $dotenv->load();
        }
    }

    public function setUp()
    {
        Eloquent::unguard();

        $db = new DB;
        $db->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $db->bootEloquent();
        $db->setAsGlobal();

        $this->schema()->create('users', function ($table) {
            $table->increments('id');
            $table->string('email');
            $table->string('name');
            $table->string('stripe_id')->nullable();
            $table->string('card_brand')->nullable();
            $table->string('card_last_four')->nullable();
            $table->timestamps();
        });

        $this->schema()->create('subscriptions', function ($table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->string('name');
            $table->string('stripe_id');
            $table->string('stripe_plan');
            $table->integer('quantity');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        $this->schema()->create('subscription_items', function ($table) {
            $table->increments('id');
            $table->integer('subscription_id');
            $table->string('stripe_id');
            $table->string('stripe_plan');
            $table->integer('quantity');
            $table->timestamps();
        });

        \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));
    }

    public function tearDown()
    {
        $this->schema()->drop('users');
        $this->schema()->drop('subscriptions');
        $this->schema()->drop('subscription_items');
    }

    /**
     * Tests.
     */
    public function testSubscriptionsCanBeCreated()
    {
        $user = User::create([
            'email' => 'taylor@laravel.com',
            'name' => 'Taylor Otwell',
        ]);

        // Create Subscription
        $user->newSubscription('main', 'monthly-10-1')->create($this->getTestToken());

        $this->assertEquals(1, count($user->subscriptions));
        $this->assertNotNull($user->subscription('main')->stripe_id);

        $this->assertTrue($user->subscribed('main'));
        $this->assertTrue($user->subscribedToPlan('monthly-10-1', 'main'));
        $this->assertFalse($user->subscribedToPlan('monthly-10-1', 'something'));
        $this->assertFalse($user->subscribedToPlan('monthly-10-2', 'main'));
        $this->assertTrue($user->subscribed('main', 'monthly-10-1'));
        $this->assertFalse($user->subscribed('main', 'monthly-10-2'));
        $this->assertTrue($user->subscription('main')->active());
        $this->assertFalse($user->subscription('main')->cancelled());
        $this->assertFalse($user->subscription('main')->onGracePeriod());

        // Cancel Subscription
        $subscription = $user->subscription('main');
        $subscription->cancel();

        $this->assertTrue($subscription->active());
        $this->assertTrue($subscription->cancelled());
        $this->assertTrue($subscription->onGracePeriod());

        // Modify Ends Date To Past
        $oldGracePeriod = $subscription->ends_at;
        $subscription->fill(['ends_at' => Carbon::now()->subDays(5)])->save();

        $this->assertFalse($subscription->active());
        $this->assertTrue($subscription->cancelled());
        $this->assertFalse($subscription->onGracePeriod());

        $subscription->fill(['ends_at' => $oldGracePeriod])->save();

        // Resume Subscription
        $subscription->resume();

        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->cancelled());
        $this->assertFalse($subscription->onGracePeriod());

        // Increment & Decrement
        $subscription->incrementQuantity();

        $this->assertEquals(2, $subscription->quantity);

        $subscription->decrementQuantity();

        $this->assertEquals(1, $subscription->quantity);

        // Swap Plan
        $subscription->swap('monthly-10-2');

        $this->assertEquals('monthly-10-2', $subscription->stripe_plan);

        // Invoice Tests
        $invoice = $user->invoices()[1];

        $this->assertEquals('$10.00', $invoice->total());
        $this->assertFalse($invoice->hasDiscount());
        $this->assertFalse($invoice->hasStartingBalance());
        $this->assertNull($invoice->coupon());
        $this->assertInstanceOf(Carbon::class, $invoice->date());

        $cu = \Stripe\Customer::retrieve($user->stripe_id);
        $cu->delete();
    }

    public function testMultiSubscriptionsCanBeCreated()
    {
        $user = User::create([
            'email' => 'taylor@laravel.com',
            'name' => 'Taylor Otwell',
        ]);

        // Create MultiSubscription
        $multisubscription = $user
            ->newMultisubscription()
            ->addPlan('monthly-10-1')
            ->addPlan('monthly-10-2')
            ->create($this->getTestToken());

        $this->assertEquals(1, count($user->subscriptions));
        $this->assertNotNull($user->subscription('default')->stripe_id);

        $this->assertTrue($user->subscribed('default'));

        $this->assertTrue($user->onPlan('monthly-10-1'));
        $this->assertTrue($user->onPlan('monthly-10-2'));
        $this->assertFalse($user->onPlan('monthly-10-3'));

        $this->assertTrue($user->subscribedToPlan('monthly-10-1'));
        $this->assertFalse($user->subscribedToPlan('monthly-10-1', 'something'));
        $this->assertTrue($user->subscribedToPlan('monthly-10-2'));
        $this->assertFalse($user->subscribedToPlan('monthly-10-3'));

        $this->assertTrue($user->subscribed('default', 'monthly-10-1'));
        $this->assertTrue($user->subscribed('default', 'monthly-10-2'));
        $this->assertFalse($user->subscribed('default', 'monthly-10-3'));
        $this->assertTrue($user->subscription('default')->active());
        $this->assertFalse($user->subscription('default')->cancelled());

        $subscription = $user->subscription('default');

        // Invoice Tests
        $invoice = $user->invoices()[0];

        $this->assertEquals('$20.00', $invoice->total());
        $this->assertFalse($invoice->hasDiscount());
        $this->assertFalse($invoice->hasStartingBalance());
        $this->assertNull($invoice->coupon());
        $this->assertInstanceOf(Carbon::class, $invoice->date());

        $cu = \Stripe\Customer::retrieve($user->stripe_id);
        $cu->delete();
    }

    public function test_creating_subscription_with_coupons()
    {
        $user = User::create([
            'email' => 'taylor@laravel.com',
            'name' => 'Taylor Otwell',
        ]);

        // Create Subscription
        $user->newSubscription('main', 'monthly-10-1')
                ->withCoupon('coupon-1')->create($this->getTestToken());

        $subscription = $user->subscription('main');

        $this->assertTrue($user->subscribed('main'));
        $this->assertTrue($user->subscribed('main', 'monthly-10-1'));
        $this->assertFalse($user->subscribed('main', 'monthly-10-2'));
        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->cancelled());
        $this->assertFalse($subscription->onGracePeriod());

        // Invoice Tests
        $invoice = $user->invoices()[0];

        $this->assertTrue($invoice->hasDiscount());
        $this->assertEquals('$5.00', $invoice->total());
        $this->assertEquals('$5.00', $invoice->amountOff());
        $this->assertFalse($invoice->discountIsPercentage());

        $cu = \Stripe\Customer::retrieve($user->stripe_id);
        $cu->delete();
    }

    public function testMultiSubscriptionsWithCoupons()
    {
        $user = User::create([
            'email' => 'taylor@laravel.com',
            'name' => 'Taylor Otwell',
        ]);

        // Create MultiSubscription
        $multisubscription = $user
            ->newMultisubscription()
            ->addPlan('monthly-10-1')
            ->addPlan('monthly-10-2')
            ->withCoupon('coupon-1')
            ->create($this->getTestToken());

        $subscription = $user->subscription('default');
        $this->assertTrue($user->subscribed('default'));
        $this->assertTrue($user->onPlan('monthly-10-1'));
        $this->assertTrue($user->onPlan('monthly-10-2'));
        $this->assertFalse($user->onPlan('monthly-10-3'));
        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->cancelled());
        $this->assertFalse($subscription->onGracePeriod());

        // Invoice Tests
        $invoice = $user->invoices()[0];

        $this->assertTrue($invoice->hasDiscount());
        $this->assertEquals('$15.00', $invoice->total());
        $this->assertEquals('$5.00', $invoice->amountOff());
        $this->assertFalse($invoice->discountIsPercentage());

        $cu = \Stripe\Customer::retrieve($user->stripe_id);
        $cu->delete();
    }

    public function test_generic_trials()
    {
        $user = new User;
        $this->assertFalse($user->onGenericTrial());
        $user->trial_ends_at = Carbon::tomorrow();
        $this->assertTrue($user->onGenericTrial());
        $user->trial_ends_at = Carbon::today()->subDays(5);
        $this->assertFalse($user->onGenericTrial());
    }

    public function test_creating_subscription_with_trial()
    {
        $user = User::create([
            'email' => 'taylor@laravel.com',
            'name' => 'Taylor Otwell',
        ]);

        // Create Subscription
        $user->newSubscription('main', 'monthly-10-1')
                ->trialDays(7)->create($this->getTestToken());

        $subscription = $user->subscription('main');

        $this->assertTrue($subscription->active());
        $this->assertTrue($subscription->onTrial());
        $this->assertEquals(Carbon::today()->addDays(7)->day, $subscription->trial_ends_at->day);

        // Cancel Subscription
        $subscription->cancel();

        $this->assertTrue($subscription->active());
        $this->assertTrue($subscription->onGracePeriod());

        // Resume Subscription
        $subscription->resume();

        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->onGracePeriod());
        $this->assertTrue($subscription->onTrial());
        $this->assertEquals(Carbon::today()->addDays(7)->day, $subscription->trial_ends_at->day);

        $cu = \Stripe\Customer::retrieve($user->stripe_id);
        $cu->delete();
    }

    public function test_creating_subscription_with_explicit_trial()
    {
        $user = User::create([
             'email' => 'taylor@laravel.com',
             'name' => 'Taylor Otwell',
        ]);

        // Create Subscription
        $user->newSubscription('main', 'monthly-10-1')
             ->trialUntil(Carbon::tomorrow()->hour(3)->minute(15))->create($this->getTestToken());

        $subscription = $user->subscription('main');

        $this->assertTrue($subscription->active());
        $this->assertTrue($subscription->onTrial());
        $this->assertEquals(Carbon::tomorrow()->hour(3)->minute(15), $subscription->trial_ends_at);

        // Cancel Subscription
        $subscription->cancel();

        $this->assertTrue($subscription->active());
        $this->assertTrue($subscription->onGracePeriod());

        // Resume Subscription
        $subscription->resume();

        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->onGracePeriod());
        $this->assertTrue($subscription->onTrial());
        $this->assertEquals(Carbon::tomorrow()->hour(3)->minute(15), $subscription->trial_ends_at);

        $cu = \Stripe\Customer::retrieve($user->stripe_id);
        $cu->delete();
    }

    public function test_applying_coupons_to_existing_customers()
    {
        $user = User::create([
            'email' => 'taylor@laravel.com',
            'name' => 'Taylor Otwell',
        ]);

        // Create Subscription
        $user->newSubscription('main', 'monthly-10-1')
                ->create($this->getTestToken());

        $user->applyCoupon('coupon-1');

        $customer = $user->asStripeCustomer();

        $this->assertEquals('coupon-1', $customer->discount->coupon->id);

        $cu = \Stripe\Customer::retrieve($user->stripe_id);
        $cu->delete();
    }

    public function testApplyingCouponsToExistingCustomers()
    {
        $user = User::create([
            'email' => 'taylor@laravel.com',
            'name' => 'Taylor Otwell',
        ]);

        // Create MultiSubscription
        $multisubscription = $user
            ->newMultisubscription()
            ->addPlan('monthly-10-1')
            ->addPlan('monthly-10-2')
            ->create($this->getTestToken());

        $user->applyCoupon('coupon-1');

        $customer = $user->asStripeCustomer();

        $this->assertEquals('coupon-1', $customer->discount->coupon->id);

        $cu = \Stripe\Customer::retrieve($user->stripe_id);
        $cu->delete();
    }

    /**
     * @group foo
     */
    public function test_marking_as_cancelled_from_webhook()
    {
        $user = User::create([
            'email' => 'taylor@laravel.com',
            'name' => 'Taylor Otwell',
        ]);

        $user->newSubscription('main', 'monthly-10-1')
                ->create($this->getTestToken());

        $subscription = $user->subscription('main');

        $request = Request::create('/', 'POST', [], [], [], [], json_encode(['id' => 'foo', 'type' => 'customer.subscription.deleted',
            'data' => [
                'object' => [
                    'id' => $subscription->stripe_id,
                    'customer' => $user->stripe_id,
                ],
            ],
        ]));

        $controller = new CashierTestControllerStub;
        $response = $controller->handleWebhook($request);
        $this->assertEquals(200, $response->getStatusCode());

        $user = $user->fresh();
        $subscription = $user->subscription('main');

        $this->assertTrue($subscription->cancelled());

        $cu = \Stripe\Customer::retrieve($user->stripe_id);
        $cu->delete();
    }

    public function testCreatingOneOffInvoices()
    {
        $user = User::create([
            'email' => 'taylor@laravel.com',
            'name' => 'Taylor Otwell',
        ]);

        // Create Invoice
        $user->createAsStripeCustomer($this->getTestToken());
        $user->invoiceFor('Laravel Cashier', 1000);

        // Invoice Tests
        $invoice = $user->invoices()[0];
        $this->assertEquals('$10.00', $invoice->total());
        $this->assertEquals('Laravel Cashier', $invoice->invoiceItems()[0]->asStripeInvoiceItem()->description);

        $cu = \Stripe\Customer::retrieve($user->stripe_id);
        $cu->delete();
    }

    public function testRefunds()
    {
        $user = User::create([
            'email' => 'taylor@laravel.com',
            'name' => 'Taylor Otwell',
        ]);

        // Create Invoice
        $user->createAsStripeCustomer($this->getTestToken());
        $invoice = $user->invoiceFor('Laravel Cashier', 1000);

        // Create the refund
        $refund = $user->refund($invoice->charge);

        // Refund Tests
        $this->assertEquals(1000, $refund->amount);

        $cu = \Stripe\Customer::retrieve($user->stripe_id);
        $cu->delete();
    }

    protected function getTestToken()
    {
        return Stripe\Token::create([
            'card' => [
                'number' => '4242424242424242',
                'exp_month' => 5,
                'exp_year' => 2020,
                'cvc' => '123',
            ],
        ], ['api_key' => getenv('STRIPE_SECRET')])->id;
    }

    /**
     * Schema Helpers.
     */
    protected function schema()
    {
        return $this->connection()->getSchemaBuilder();
    }

    protected function connection()
    {
        return Eloquent::getConnectionResolver()->connection();
    }
}

class User extends Eloquent
{
    use Laravel\Cashier\Billable;
}

class CashierTestControllerStub extends WebhookController
{
    protected function eventExistsOnStripe($id)
    {
        return true;
    }
}

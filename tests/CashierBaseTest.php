<?php

namespace Laravel\Cashier\Tests;

use PHPUnit_Framework_TestCase;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Model as Eloquent;

abstract class CashierBaseTest extends PHPUnit_Framework_TestCase
{
    public static function setUpBeforeClass()
    {
        if (file_exists(__DIR__.'/../.env')) {
            $dotenv = new \Dotenv\Dotenv(__DIR__.'/../');
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
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        $this->schema()->create('subscription_items', function ($table) {
            $table->increments('id');
            $table->integer('subscription_id');
            $table->string('stripe_id');
            $table->string('stripe_plan');
            $table->integer('quantity')->nullable();
            $table->timestamps();
        });
    }

    public function tearDown()
    {
        $this->schema()->drop('users');
        $this->schema()->drop('subscriptions');
        $this->schema()->drop('subscription_items');
    }

    protected function getTestToken()
    {
        return \Stripe\Token::create([
            'card' => [
                'number' => '4242424242424242',
                'exp_month' => 5,
                'exp_year' => date('Y') + 1,
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

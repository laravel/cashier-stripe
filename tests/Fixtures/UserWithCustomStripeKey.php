<?php

namespace Laravel\Cashier\Tests\Fixtures;

class UserWithCustomStripeKey extends User
{
    protected $stripeKey = 'stripe_customer_id';
}

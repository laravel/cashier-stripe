<?php

namespace Laravel\Cashier\Tests\Fixtures;

use Laravel\Cashier\Billable;
use Illuminate\Database\Eloquent\Model as Eloquent;

class User extends Eloquent
{
    use Billable;
}

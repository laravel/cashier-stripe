<?php

namespace Laravel\Cashier\Tests\Fixtures;

use Laravel\Cashier\Billable;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use Billable;
}

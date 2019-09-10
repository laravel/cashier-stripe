<?php

namespace Laravel\Cashier\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;

class User extends Model
{
    use Billable, Notifiable;
}

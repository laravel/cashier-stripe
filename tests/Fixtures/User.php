<?php

namespace Laravel\Cashier\Tests\Fixtures;

use Laravel\Cashier\Billable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Model;

class User extends Model
{
    use Billable, Notifiable;
}

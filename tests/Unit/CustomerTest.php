<?php

namespace Laravel\Cashier\Tests\Unit;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use Laravel\Cashier\Tests\Fixtures\User;

class CustomerTest extends TestCase
{
    public function test_customer_can_be_put_on_a_generic_trial()
    {
        $user = new User;

        $this->assertFalse($user->onGenericTrial());

        $user->trial_ends_at = Carbon::tomorrow();

        $this->assertTrue($user->onGenericTrial());

        $user->trial_ends_at = Carbon::today()->subDays(5);

        $this->assertFalse($user->onGenericTrial());
    }
}

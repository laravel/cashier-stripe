<?php

namespace Laravel\Cashier\Tests\Feature;

use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\SubscriptionItem;
use Laravel\Cashier\Tests\TestCase;
use Orchestra\Testbench\Attributes\WithMigration;
use Orchestra\Testbench\Concerns\WithLaravelMigrations;

#[WithMigration]
class SubscriptionOwnerTest extends TestCase
{
    use RefreshDatabase;
    use WithLaravelMigrations;

    protected function tearDown(): void
    {
        Cashier::useCustomerModel(User::class);

        parent::tearDown();
    }

    public function test_subscription_owner_can_resolve_soft_deleted_billables()
    {
        Schema::table('users', function ($table) {
            $table->softDeletes();
        });

        Cashier::useCustomerModel(SoftDeletingUser::class);

        $user = SoftDeletingUser::forceCreate([
            'email' => 'soft-deleted-owner@cashier-test.com',
            'name' => 'Taylor Otwell',
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'stripe_id' => 'cus_foo',
        ]);

        $subscription = $user->subscriptions()->create([
            'type' => 'main',
            'stripe_id' => 'sub_foo',
            'stripe_price' => 'price_foo',
            'stripe_status' => 'active',
        ]);

        $item = $subscription->items()->create([
            'stripe_id' => 'si_foo',
            'stripe_product' => 'prod_foo',
            'stripe_price' => 'price_foo',
            'quantity' => 1,
        ]);

        $user->delete();

        $item = SubscriptionItem::query()->find($item->id);

        $this->assertTrue($item->subscription->owner->is($user));
    }
}

class SoftDeletingUser extends User
{
    use SoftDeletes;

    protected $table = 'users';

    public function getForeignKey()
    {
        return 'user_id';
    }
}

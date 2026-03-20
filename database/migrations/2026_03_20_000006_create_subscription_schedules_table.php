<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Note: The table name 'subscription_schedules' does not use the 'cashier_' prefix
     * to stay consistent with the existing 'subscriptions' and 'subscription_items' tables
     * which also omit the prefix. Changing this would be a breaking change for existing
     * installations that have already run this migration.
     */
    public function up(): void
    {
        Schema::create('subscription_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('type');
            $table->string('stripe_id')->unique();
            $table->string('stripe_status');
            $table->string('subscription_id')->nullable();
            $table->timestamp('current_phase_started_at')->nullable();
            $table->timestamp('current_phase_ends_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'stripe_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_schedules');
    }
};

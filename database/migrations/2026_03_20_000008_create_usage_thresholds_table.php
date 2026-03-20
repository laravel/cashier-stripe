<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cashier_usage_thresholds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('meter_id');
            $table->unsignedBigInteger('threshold');
            $table->string('period')->default('billing_cycle');
            $table->json('alert_options')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'meter_id']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cashier_usage_thresholds');
    }
};

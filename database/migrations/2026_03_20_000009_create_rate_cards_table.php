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
        Schema::create('cashier_rate_cards', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('product_id');
            $table->string('pricing_type'); // tiered, package, flat
            $table->json('rates');
            $table->string('currency')->default('usd');
            $table->string('interval')->default('month');
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cashier_rate_cards');
    }
};

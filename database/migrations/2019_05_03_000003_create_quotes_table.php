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
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('stripe_id')->unique();
            $table->string('status');
            $table->string('number')->nullable();
            $table->integer('amount_subtotal')->nullable();
            $table->integer('amount_total')->nullable();
            $table->string('currency');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('status_transitions_finalized_at')->nullable();
            $table->timestamp('status_transitions_accepted_at')->nullable();
            $table->timestamp('status_transitions_canceled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};

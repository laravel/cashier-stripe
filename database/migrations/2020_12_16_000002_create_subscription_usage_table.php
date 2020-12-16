<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSubscriptionUsageTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('subscription_usage', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscription_item_id');
            $table->integer('quantity');
            $table->timestamps();

            $table->unique(['subscription_item_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('subscription_usage');
    }
}

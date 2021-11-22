<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPauseCollectionToSubscriptionsTable extends Migration
{

    /**
     * Get used table name.
     *
     * @return mixed
     */
    protected function getTableName() {
        $modelClass = \Laravel\Cashier\Cashier::$subscriptionModel;

        return (new $modelClass)->getTable();
    }

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::table( $this->getTableName(), function ( Blueprint $table ) {
            $table->json( 'pause_collection' )->nullable()
                  ->after( 'quantity' );
        } );
    }

    /**
     * Reverse the migrations.
     */
    public function down() {
        Schema::table( $this->getTableName(), function ( Blueprint $table ) {
            $table->dropColumn( 'pause_collection' );
        } );
    }
}

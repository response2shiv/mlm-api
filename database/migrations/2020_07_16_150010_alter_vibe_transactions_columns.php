<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterVibeTransactionsColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('vibe_transactions', function (Blueprint $table) {
            # Modify type columns
            $table->string('ride_id')->change();
            $table->string('rider_id')->change();
            $table->string('driver_id')->change();
            $table->integer('distance')->change();

            # Drop Column
            $table->dropColumn('commissions');
            $table->dropColumn('duration');

            # Add new columns
            $table->integer('driver_sponsor')->nullable();
            $table->integer('customer_sponsor')->nullable();
            $table->string('ride_status');
            $table->string('payment_status');
            $table->integer('amount')->nullable();
            $table->dateTime('ride_date');
        });

        Schema::table('vibe_transactions', function (Blueprint $table) {

            # Add new columns
            $table->integer('duration')->nullable();
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('vibe_transactions', function (Blueprint $table) {
            //
        });
    }
}

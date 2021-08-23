<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGiftBoxField extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //Creating the giftbox field on the orders table
        Schema::table('orders', function (Blueprint $table) {
            $table->smallInteger('gift_box')->default(0)->nullable();
        });
        Schema::table('pre_orders', function (Blueprint $table) {
            $table->smallInteger('gift_box')->default(0)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('gift_box');
        });
        Schema::table('pre_orders', function (Blueprint $table) {
            $table->dropColumn('gift_box');
        });
    }
}

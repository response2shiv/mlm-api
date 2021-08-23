<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterPreOrderAmount extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::table('pre_orders', function (Blueprint $table) {
            $table->decimal('ordersubtotal', 10, 2)->nullable()->change();
            $table->decimal('ordertax', 10, 2)->nullable()->change();
            $table->decimal('ordertotal', 10, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
        Schema::table('pre_orders', function (Blueprint $table) {
            $table->decimal('ordersubtotal', 10, 0)->nullable()->change();
            $table->decimal('ordertax', 10, 0)->nullable()->change();
            $table->decimal('ordertotal', 10, 0)->nullable()->change();
        });
    }
}

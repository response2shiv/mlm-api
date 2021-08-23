<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangePreOrderItemAmountSize extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::table('pre_order_items', function (Blueprint $table) {
            $table->decimal('itemprice', 10, 2)->nullable()->change();
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
        Schema::table('pre_order_items', function (Blueprint $table) {
            $table->decimal('itemprice', 10, 0)->nullable()->change();            
        });
    }
}

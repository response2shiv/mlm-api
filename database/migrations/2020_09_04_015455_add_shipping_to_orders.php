<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddShippingToOrders extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('ordershipping', 10, 2)->nullable();
            $table->decimal('ordertaxrate', 10, 2)->nullable();
        });

        Schema::table('pre_orders', function (Blueprint $table) {
            $table->decimal('ordershipping', 10, 2)->nullable();
            $table->decimal('ordertaxrate', 10, 2)->nullable();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->smallInteger('allow_quantity_change')->default(0)->nullable();
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
            $table->dropColumn('ordershipping');
            $table->dropColumn('ordertaxrate');
        });
        Schema::table('pre_orders', function (Blueprint $table) {
            $table->dropColumn('ordershipping');
            $table->dropColumn('ordertaxrate');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('allow_quantity_change');
        });
    }
}

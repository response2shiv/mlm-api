<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUserPaymentMethodsIdInPreOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pre_orders', function (Blueprint $table) {
            $table->bigInteger('user_payment_methods_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pre_orders', function (Blueprint $table) {
            $table->dropColumn('user_payment_methods_id');
        });
    }
}

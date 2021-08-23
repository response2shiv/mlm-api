<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserPaymentMethodMerchantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_payment_method_merchants', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_payment_method_id');
            $table->bigInteger('merchant_id');
            $table->timestamps();

            # FK's
            $table->foreign('user_payment_method_id')->references('id')->on('user_payment_methods');
            $table->foreign('merchant_id')->references('id')->on('merchants');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_payment_method_merchants');
    }
}

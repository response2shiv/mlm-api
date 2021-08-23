<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterUserPaymentMethodsNullable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::table('user_payment_methods', function (Blueprint $table) {
            $table->unsignedBigInteger('user_payment_address_id')->nullable()->change();
            $table->string('card_token')->nullable()->change();
            $table->string('first_name')->nullable()->change();
            $table->string('last_name')->nullable()->change();
            $table->string('cvv')->nullable()->change();
            $table->string('expiration_month')->nullable()->change();
            $table->string('expiration_year')->nullable()->change();
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
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserPaymentMethodsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_payment_methods', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('user_payment_address_id');
            $table->string("first_name");
            $table->string("last_name");
            $table->string("card_number");
            $table->string("expiry_date");
            $table->boolean("is_primary")->default(false);
            $table->boolean("is_active")->default(true);
            $table->boolean("known_t1")->default(false);
            $table->boolean("known_metro")->default(false);
            $table->boolean("known_payarc")->default(false);
            $table->unsignedBigInteger("payment_method_type");
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_payment_methods');
    }
}

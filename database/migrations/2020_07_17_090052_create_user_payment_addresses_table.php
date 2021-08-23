<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserPaymentAddressesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_payment_addresses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger("user_id");
            $table->unsignedBigInteger('addrtype');
            $table->boolean('is_primary')->default(false);
            $table->string("address1");
            $table->string("address2")->nullable();
            $table->string('city');
            $table->string('stateprov');
            $table->string('stateprov_abbrev');
            $table->string('postal_code')->nullable();
            $table->string('apt')->nullable();
            $table->string('country_code');
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
        Schema::dropIfExists('user_payment_addresses');
    }
}

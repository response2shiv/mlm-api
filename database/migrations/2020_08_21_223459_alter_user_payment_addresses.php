<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterUserPaymentAddresses extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::table('user_payment_addresses', function (Blueprint $table) {
            $table->renameColumn('stateprov', 'state');
            $table->renameColumn('postal_code', 'zipcode');
            $table->dropColumn('addrtype');
            $table->dropColumn('user_id');
            $table->dropColumn('is_primary');
            $table->dropColumn('apt');
            $table->dropColumn('stateprov_abbrev');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_payment_addresses', function (Blueprint $table) {
            $table->renameColumn('state', 'stateprov');
            $table->renameColumn('zipcode', 'postal_code');
            $table->unsignedBigInteger("user_id");
            $table->unsignedBigInteger('addrtype');
            $table->boolean('is_primary')->default(false);
            $table->string('apt')->nullable();
            $table->string('stateprov_abbrev');
        });
    }
}

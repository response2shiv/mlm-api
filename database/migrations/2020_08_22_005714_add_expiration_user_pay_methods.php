<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddExpirationUserPayMethods extends Migration
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
            $table->dropColumn('expiration_date');
            $table->dropColumn('payment_method_type');
            $table->string('expiration_month', 10)->after('primary');
            $table->string('expiration_year', 10)->after('primary');
            $table->renameColumn('card_number', 'card_token');
            $table->renameColumn('primary', 'is_primary');
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
        Schema::table('user_payment_methods', function (Blueprint $table) {
            $table->string('expiration_date');
            $table->unsignedBigInteger("payment_method_type");
            $table->dropColumn('expiration_month');
            $table->dropColumn('expiration_year');
            $table->renameColumn('card_token', 'card_number');
            $table->renameColumn('is_primary', 'primary');
        });
    }
}

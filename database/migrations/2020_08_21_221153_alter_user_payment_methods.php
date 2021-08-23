<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterUserPaymentMethods extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_payment_methods', function (Blueprint $table) {
            $table->renameColumn('expiry_date', 'expiration_date');
            $table->renameColumn('is_primary', 'primary');
            $table->renameColumn('is_active', 'active');
            $table->dropColumn('known_t1');
            $table->dropColumn('known_metro');
            $table->dropColumn('known_payarc');
            $table->string('cvv')->after('expiration_date')->nullable();
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
            $table->renameColumn('expiration_date', 'expiry_date');
            $table->renameColumn('primary', 'is_primary');
            $table->renameColumn('active', 'is_active');
            $table->boolean("known_t1")->default(false);
            $table->boolean("known_metro")->default(false);
            $table->boolean("known_payarc")->default(false);
            $table->dropColumn('cvv');
        });        
    }
}

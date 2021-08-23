<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\UserPaymentMethod;
use App\Models\PaymentMethod;


class AddIsRestrictedToUserPayMethods extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_payment_methods', function (Blueprint $table) {
            $table->boolean('is_restricted')->nullable()->default(false);
        });
        
        DB::table('statuscode')->insert([
            ['id' => 15, 'status_desc' => 'VOIDED']
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_payment_methods', function (Blueprint $table) {
            $table->dropColumn('is_restricted');
        });
        
        DB::table('statuscode')->where('id', 15)->delete();
    }
}

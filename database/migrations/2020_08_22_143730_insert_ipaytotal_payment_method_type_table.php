<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class InsertIpaytotalPaymentMethodTypeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('payment_method_type')->insert([
            ['id' => 14, 'pay_method_name' => 'iPayTotal', 'statuscode' => 1, 'type' => 'CC']
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('payment_method_type')->where('id', 14)->delete();
    }
}

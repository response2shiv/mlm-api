<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdatePaymentTypeIdOnUserPaymentMethodsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        # Update flag to CC where card_token have value
        DB::table('user_payment_methods')
            ->whereNotNull('card_token')
            ->update(['pay_method_type' => 1]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(){}
}

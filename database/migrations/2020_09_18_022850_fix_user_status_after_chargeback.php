<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FixUserStatusAfterChargeback extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //get chargbacks tables
        $chargebacks_order_id = DB::table('chargebacks')->pluck('order_id');

        //get orders
        $orders_user_ud = DB::table('orders')->whereIn('id', $chargebacks_order_id)->pluck('userid');

        //update user status
        DB::table('users')->whereIn('id', $orders_user_ud)
                        //   ->where('account_status', \App\Models\User::ACC_STATUS_TERMINATED)
                          ->update([
                              'account_status' => \App\Models\User::ACC_STATUS_CHARGEBACK_REVIEW
                            ]);

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

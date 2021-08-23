<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMerchantTransactionTrackersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('merchant_transaction_tracker', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('merchant_id')->nullable();
            $table->unsignedInteger('pre_order_id')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('status')->nullable();
            $table->smallInteger('cron_processed')->default(0);
            $table->timestamps();

            # FK's
            $table->foreign('merchant_id')->references('id')->on('merchants');
            // $table->foreign('pre_order_id')->references('id')->on('pre_orders');
        });

        $orders = DB::table('unicrypt_invoice_tracker')->where('id', '>', 0)->get();
        foreach($orders as $order){

            $pre_order = DB::table('pre_orders')->where('orderhash', $order->orderhash)->first();
            if($pre_order){
                $poid = $pre_order->id;
            }else{
                $poid = 0;
            }
            $data['merchant_id']    = 5;
            $data['transaction_id'] = $order->orderhash;
            $data['pre_order_id']   = $poid;
            $data['status']         = $order->status;
            $data['cron_processed'] = $order->cron_processed;
            $data['created_at']     = $order->created_at;
            $data['updated_at']     = $order->created_at;            
            DB::table('merchant_transaction_tracker')->insert($data);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('merchant_transaction_tracker');
    }
}

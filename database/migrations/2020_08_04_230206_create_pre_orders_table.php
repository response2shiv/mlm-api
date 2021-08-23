<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePreOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pre_orders', function (Blueprint $table) {
            $table->integer('userid')->nullable()->index('pre_orders_userid_idx');
            $table->string('orderhash')->nullable();
            $table->integer('statuscode')->nullable();
            $table->decimal('ordersubtotal', 10, 0)->nullable();
            $table->decimal('ordertax', 10, 0)->nullable();
            $table->decimal('ordertotal', 10, 0)->nullable();
            $table->integer('orderbv')->nullable();
            $table->integer('orderqv')->nullable();
            $table->integer('ordercv')->nullable();
            $table->string('trasnactionid', 50)->nullable();
            $table->timestamps();
            $table->integer('payment_methods_id')->nullable()->index('pre_orders_payment_methods_id_idx');
            $table->integer('shipping_address_id')->nullable();
            $table->bigInteger('id', true);
            $table->integer('inv_id')->nullable();
            $table->date('created_date')->nullable();
            $table->time('created_time')->nullable();
            $table->boolean('processed')->nullable()->default(0)->index('pre_idx_status');
            $table->string('coupon_code', 30)->nullable();
            $table->bigInteger('order_refund_ref')->nullable();
            $table->dateTime('created_dt')->nullable();
            $table->integer('orderqc')->nullable();
            $table->integer('orderac')->nullable();
            $table->integer('payment_type_id')->nullable();
            $table->index(['created_dt','statuscode'], 'pre_orders_created_dt_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pre_orders');
    }
}

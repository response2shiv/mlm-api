<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePreOrderItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pre_order_items', function (Blueprint $table) {
            $table->integer('orderid')->nullable()->index('pre_orderitem_orderid_idx');
            $table->integer('productid')->nullable()->index('pre_orderitem_productid_idx');
            $table->integer('quantity')->nullable();
            $table->decimal('itemprice', 10, 0)->nullable();
            $table->integer('bv')->nullable();
            $table->integer('qv')->nullable();
            $table->integer('cv')->nullable();
            $table->bigInteger('id', true);
            $table->timestamps();
            $table->date('created_date')->nullable();
            $table->time('created_time')->nullable();
            $table->bigInteger('discount_coupon')->nullable();
            $table->bigInteger('discount_voucher_id')->nullable();
            $table->dateTime('created_dt')->nullable()->index('pre_orderitem_created_dt_idx');
            $table->integer('qc')->nullable();
            $table->integer('ac')->nullable();
            $table->integer('is_refunded')->nullable()->default(0);
            $table->integer('will_be_attend')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pre_order_items');
    }
}

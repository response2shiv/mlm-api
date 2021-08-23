<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorldSeriesErrorsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('world_series_errors', function (Blueprint $table) {
            $table->bigIncrements('id');
            
            $table->tinyInteger('is_enrollment')->default(0);
            $table->tinyInteger('is_upgrade')->default(0);

            $table->unsignedInteger('order_id');
            $table->unsignedInteger('order_refund_id')->nullable();

            # FK's
            $table->foreign('order_id')->references('id')->on('orders');
            $table->foreign('order_refund_id')->references('id')->on('orders');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('world_series_errors');
    }
}

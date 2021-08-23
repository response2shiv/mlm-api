<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomerTypePivotTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customer_type_pivot', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedInteger('customer_id')->nullable();
            $table->unsignedInteger('customer_type_id')->nullable();

            # FK's
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('customer_type_id')->references('id')->on('customer_types');
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
        Schema::dropIfExists('customer_type_pivot');
    }
}

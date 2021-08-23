<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVibeTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vibe_transactions', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedInteger('customer_id')->nullable();
            $table->unsignedInteger('rider_id')->nullable();
            $table->unsignedInteger('driver_id')->nullable();
            $table->unsignedInteger('ride_id')->nullable();
            $table->char('status', 30);
            $table->decimal('total', 10, 2);
            $table->decimal('commissions');
            $table->decimal('distance');
            $table->time('duration');
            $table->timestamps();

            # FK's
            $table->foreign('customer_id')->references('id')->on('customers');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('vibe_transactions');
    }
}

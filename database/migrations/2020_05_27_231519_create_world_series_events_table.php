<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorldSeriesEventsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('world_series_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            
            # (0 = no hit and not on base, 1 = 1st base, 2 = 2nd base, 3 = 3rd base, 4 = run or point or score) : Depending on the rules pertaining to 
            $table->tinyInteger('position')->default(0);
            $table->string('event_type')->nullable();
            $table->string('description')->nullable();

            # To know when the event is historical or active.
            $table->boolean('active')->default(1);

            $table->unsignedInteger('sponsor_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('order_id');
            $table->unsignedInteger('moved_by_user_id')->nullable();

            # FK's
            $table->foreign('sponsor_id')->references('id')->on('users');
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('order_id')->references('id')->on('orders');
            $table->foreign('moved_by_user_id')->references('id')->on('users');

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
        Schema::dropIfExists('world_series_events');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorldSeriesOverviewsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('world_series_overviews', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedInteger('sponsor_id');
            $table->unsignedInteger('first_base_user_id')->nullable();
            $table->unsignedInteger('second_base_user_id')->nullable();
            $table->unsignedInteger('third_base_user_id')->nullable();

            $table->integer('runs')->default(0);
            $table->integer('hits')->default(0);
            $table->integer('errors')->default(0);
            $table->integer('total')->default(0);
            $table->string('season_name', 50);

            # FK's
            $table->foreign('sponsor_id')->references('id')->on('users');
            $table->foreign('first_base_user_id')->references('id')->on('users');
            $table->foreign('second_base_user_id')->references('id')->on('users');
            $table->foreign('third_base_user_id')->references('id')->on('users');

            $table->timestamp('season_period');
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
        Schema::dropIfExists('world_series_overviews');
    }
}

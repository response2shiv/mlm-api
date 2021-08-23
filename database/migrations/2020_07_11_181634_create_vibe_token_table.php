<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVibeTokenTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vibe_token', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedInteger('customer_id')->nullable();
            $table->unsignedInteger('vibe_id')->nullable();
            $table->string('status');

            # FK's
            $table->foreign('customer_id')->references('id')->on('customers');
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
        Schema::dropIfExists('vibe_token');
    }
}

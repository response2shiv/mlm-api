<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVolumeLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('volume_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('order_id');
            $table->dateTime('date_distributed');
            $table->integer('bv')->nullable();
            $table->integer('qv')->nullable();
            $table->integer('cv')->nullable();
            $table->integer('pev')->nullable();
            $table->unsignedBigInteger('bucket_id');
            $table->string('status')->nullable();
            $table->integer('week_no')->nullable();
            $table->boolean('adjustment')->nullable()->default(false);
            $table->dateTime('adjustment_dt')->nullable();            
            $table->unsignedBigInteger('adjustment_userid')->nullable();
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
        Schema::dropIfExists('volume_logs');
    }
}

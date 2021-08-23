<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserBucketVolumesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_bucket_volumes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->integer("week_no");
            $table->integer('bv_a')->nullable();
            $table->integer('bv_b')->nullable();
            $table->integer('bv_c')->nullable();
            $table->integer('total_bv')->nullable();
            $table->integer('cv')->nullable();
            $table->integer('qv')->nullable();
            $table->integer('pv')->nullable();
            $table->integer('pev')->nullable();
            $table->date('start_of_week')->nullable();
            $table->date('end_of_week')->nullable();
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
        Schema::dropIfExists('user_bucket_volumes');
    }
}

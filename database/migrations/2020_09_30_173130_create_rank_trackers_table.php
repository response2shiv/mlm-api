<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class CreateRankTrackersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rank_tracker', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->boolean('is_running')->nullable()->default(false);
            $table->timestamp('last_run_start');
            $table->timestamp('last_run_finish');
            $table->timestamps();
        });

        DB::table('rank_tracker')->insert([
            ['id' => 1, 
            'is_running' => false, 
            'last_run_start' => Carbon::now(), 
            'last_run_finish' => Carbon::now(),
            'created_at' => Carbon::now()
            ]
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('rank_tracker');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBonusRunsToWorldSeriesOverviews extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('world_series_overviews', function (Blueprint $table) {
            $table->integer('bonus_runs')->default(0)->after('total');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('world_series_overviews', function (Blueprint $table) {
			$table->dropColumn('bonus_runs');
        });
    }
}
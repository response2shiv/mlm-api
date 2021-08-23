<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEnrollmentBlacklistColumnCountryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('country', function (Blueprint $table) {
            $table->smallInteger('enrollment_blacklist')->default(0);
        });

        DB::table('country')
            ->where('country', 'Ukraine')
            ->update(['enrollment_blacklist' => 1]);
        DB::table('country')
            ->where('country', 'Afghanistan')
            ->update(['enrollment_blacklist' => 1]);
        DB::table('country')
            ->where('country', 'Albania')
            ->update(['enrollment_blacklist' => 1]);
        DB::table('country')
            ->where('country', 'Armenia')
            ->update(['enrollment_blacklist' => 1]);
        DB::table('country')
            ->where('country', 'Czech Republic')
            ->update(['enrollment_blacklist' => 1]);
        DB::table('country')
            ->where('country', 'Kazakhstan')
            ->update(['enrollment_blacklist' => 1]);
        DB::table('country')
            ->where('country', 'Iran (Islamic Republic of)')
            ->update(['enrollment_blacklist' => 1]);
        DB::table('country')
            ->where('country', 'Malaysia')
            ->update(['enrollment_blacklist' => 1]);
        DB::table('country')
            ->where('country', 'Macedonia')
            ->update(['enrollment_blacklist' => 1]);
        DB::table('country')
            ->where('country', 'Indonesia')
            ->update(['enrollment_blacklist' => 1]);
        DB::table('country')
            ->where('country', 'Yugoslavia')
            ->update(['enrollment_blacklist' => 1]);
        DB::table('country')
            ->where('country', 'Lithuania')
            ->update(['enrollment_blacklist' => 1]);
        DB::table('country')
            ->where('country', 'Romania')
            ->update(['enrollment_blacklist' => 1]);
        DB::table('country')
            ->where('country', 'Bulgaria')
            ->update(['enrollment_blacklist' => 1]);
        DB::table('country')
            ->where('country', 'Russian Federation')
            ->update(['enrollment_blacklist' => 1]);
        DB::table('country')
            ->where('country', 'Pakistan')
            ->update(['enrollment_blacklist' => 1]);
        DB::table('country')
            ->where('country', 'India')
            ->update(['enrollment_blacklist' => 1]);
        DB::table('country')
            ->where('country', 'Kuwait')
            ->update(['enrollment_blacklist' => 1]);
        DB::table('country')
            ->where('country', 'Azerbaijan')
            ->update(['enrollment_blacklist' => 1]);
        DB::table('country')
            ->where('country', 'Vietnam')
            ->update(['enrollment_blacklist' => 1]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('country', function (Blueprint $table) {
            $table->dropColumn('enrollment_blacklist');
        });
    }
}

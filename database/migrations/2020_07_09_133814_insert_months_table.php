<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InsertMonthsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('months')->insert([
            ['name' => 'January',   'short_name' => 'Jan' ],
            ['name' => 'February',  'short_name' => 'Feb' ],
            ['name' => 'March',     'short_name' => 'Mar' ],
            ['name' => 'April',     'short_name' => 'Apr' ],
            ['name' => 'May',       'short_name' => 'May' ],
            ['name' => 'June',      'short_name' => 'Jun' ],
            ['name' => 'July',      'short_name' => 'Jul' ],
            ['name' => 'August',    'short_name' => 'Aug' ],
            ['name' => 'September', 'short_name' => 'Sep' ],
            ['name' => 'October',   'short_name' => 'Oct' ],
            ['name' => 'November',  'short_name' => 'Nov' ],
            ['name' => 'December',  'short_name' => 'Dec' ],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('months')->delete();
    }
}

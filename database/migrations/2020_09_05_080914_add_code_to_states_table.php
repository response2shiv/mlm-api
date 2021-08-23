<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddCodeToStatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('states', function (Blueprint $table) {
            $table->string('code', 3)->nullable();
        });

        DB::table('states')->where('name', 'ilike', 'ALABAMA')->update(['code' => 'AL']);
        DB::table('states')->where('name', 'ilike', 'ALASKA')->update(['code' => 'AK']);
        DB::table('states')->where('name', 'ilike', 'APO')->update(['code' => 'APO']);
        DB::table('states')->where('name', 'ilike', 'ARIZONA')->update(['code' => 'AZ']);
        DB::table('states')->where('name', 'ilike', 'ARKANSAS')->update(['code' => 'AR']);
        DB::table('states')->where('name', 'ilike', 'CALIFORNIA')->update(['code' => 'CA']);
        DB::table('states')->where('name', 'ilike', 'COLORADO')->update(['code' => 'CO']);
        DB::table('states')->where('name', 'ilike', 'CONNECTICUT')->update(['code' => 'CT']);
        DB::table('states')->where('name', 'ilike', 'DELAWARE')->update(['code' => 'DE']);
        DB::table('states')->where('name', 'ilike', 'FLORIDA')->update(['code' => 'FL']);
        DB::table('states')->where('name', 'ilike', 'FPO')->update(['code' => 'FPO']);
        DB::table('states')->where('name', 'ilike', 'GEORGIA')->update(['code' => 'GA']);
        DB::table('states')->where('name', 'ilike', 'HAWAII')->update(['code' => 'HI']);
        DB::table('states')->where('name', 'ilike', 'IDAHO')->update(['code' => 'ID']);
        DB::table('states')->where('name', 'ilike', 'ILLINOIS')->update(['code' => 'IL']);
        DB::table('states')->where('name', 'ilike', 'INDIANA')->update(['code' => 'IN']);
        DB::table('states')->where('name', 'ilike', 'IOWA')->update(['code' => 'IA']);
        DB::table('states')->where('name', 'ilike', 'KANSAS')->update(['code' => 'KS']);
        DB::table('states')->where('name', 'ilike', 'KENTUCKY')->update(['code' => 'KY']);
        DB::table('states')->where('name', 'ilike', 'LOUISIANA')->update(['code' => 'LA']);
        DB::table('states')->where('name', 'ilike', 'MAINE')->update(['code' => 'ME']);
        DB::table('states')->where('name', 'ilike', 'MARYLAND')->update(['code' => 'MD']);
        DB::table('states')->where('name', 'ilike', 'MASSACHUSETTS')->update(['code' => 'MA']);
        DB::table('states')->where('name', 'ilike', 'MICHIGAN')->update(['code' => 'MI']);
        DB::table('states')->where('name', 'ilike', 'MINNESOTA')->update(['code' => 'MN']);
        DB::table('states')->where('name', 'ilike', 'MISSISSIPPI')->update(['code' => 'MS']);
        DB::table('states')->where('name', 'ilike', 'MISSOURI')->update(['code' => 'MO']);
        DB::table('states')->where('name', 'ilike', 'MONTANA')->update(['code' => 'MT']);
        DB::table('states')->where('name', 'ilike', 'NEBRASKA')->update(['code' => 'NE']);
        DB::table('states')->where('name', 'ilike', 'NEVADA')->update(['code' => 'NV']);
        DB::table('states')->where('name', 'ilike', 'NEW HAMPSHIRE')->update(['code' => 'NH']);
        DB::table('states')->where('name', 'ilike', 'NEW JERSEY')->update(['code' => 'NJ']);
        DB::table('states')->where('name', 'ilike', 'NEW MEXICO')->update(['code' => 'NM']);
        DB::table('states')->where('name', 'ilike', 'NEW YORK')->update(['code' => 'NY']);
        DB::table('states')->where('name', 'ilike', 'NORTH CAROLINA')->update(['code' => 'NC']);
        DB::table('states')->where('name', 'ilike', 'NORTH DAKOTA')->update(['code' => 'ND']);
        DB::table('states')->where('name', 'ilike', 'OHIO')->update(['code' => 'OH']);
        DB::table('states')->where('name', 'ilike', 'OKLAHOMA')->update(['code' => 'OK']);
        DB::table('states')->where('name', 'ilike', 'OREGON')->update(['code' => 'OR']);
        DB::table('states')->where('name', 'ilike', 'PENNSYLVANIA')->update(['code' => 'PA']);
        DB::table('states')->where('name', 'ilike', 'RHODE ISLAND')->update(['code' => 'RI']);
        DB::table('states')->where('name', 'ilike', 'SOUTH CAROLINA')->update(['code' => 'SC']);
        DB::table('states')->where('name', 'ilike', 'SOUTH DAKOTA')->update(['code' => 'SD']);
        DB::table('states')->where('name', 'ilike', 'TENNESSEE')->update(['code' => 'TN']);
        DB::table('states')->where('name', 'ilike', 'TEXAS')->update(['code' => 'TX']);
        DB::table('states')->where('name', 'ilike', 'UTAH')->update(['code' => 'UT']);
        DB::table('states')->where('name', 'ilike', 'VERMONT')->update(['code' => 'VT']);
        DB::table('states')->where('name', 'ilike', 'VIRGINIA')->update(['code' => 'VA']);
        DB::table('states')->where('name', 'ilike', 'WASHINGTON')->update(['code' => 'WA']);
        DB::table('states')->where('name', 'ilike', 'WASHINGTON, D.C.')->update(['code' => 'DC']);
        DB::table('states')->where('name', 'ilike', 'WEST VIRGINIA')->update(['code' => 'WV']);
        DB::table('states')->where('name', 'ilike', 'WISCONSIN')->update(['code' => 'WI']);
        DB::table('states')->where('name', 'ilike', 'WYOMING')->update(['code' => 'WY']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('states', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class InsertCustomerTypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('customer_types')->insert([
            ['description' => 'Vibe Driver', 'type' => 'driver'],
            ['description' => 'Vibe Rider' , 'type' => 'rider'],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('customer_types')->delete();
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveColumnsCustomers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasColumn('customers', 'has_vibe')) { 
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('has_vibe');
            });
        }

        if (Schema::hasColumn('customers', 'has_boom')) { 
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('has_boom');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customers', function (Blueprint $table) {
            //
        });
    }
}

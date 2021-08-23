<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQcOnQvTransaction extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('qv_transaction', function (Blueprint $table) {
            $table->float('qc', 2, 2)->default(0)->after('qv')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('qv_transaction', function (Blueprint $table) {
			$table->dropColumn('qc');
        });
    }
}

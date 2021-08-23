<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterUnicriptInvoiceTracker extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::table('unicrypt_invoice_tracker', function (Blueprint $table) {
            $table->smallInteger('cron_processed')->default(0);
        });

        DB::table('usertype')->insert([
            ['id' => 5, 'typedesc' => 'DELETED', 'statuscode' => 5]
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
        Schema::table('unicrypt_invoice_tracker', function (Blueprint $table) {
            $table->dropColumn('cron_processed');
        });

        DB::table('usertype')->whereId(5)->delete();
    }
}

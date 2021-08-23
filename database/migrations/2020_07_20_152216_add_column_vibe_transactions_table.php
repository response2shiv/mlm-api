<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnVibeTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('vibe_transactions', function (Blueprint $table) {
            $table->decimal('commission_amount', 8, 2)->default(0);

            if (Schema::hasColumn('vibe_transactions', 'amount')) { 
                $table->dropColumn('amount');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('vibe_transactions', function (Blueprint $table) {
            $table->dropColumn('commission_amount');

            if (Schema::hasColumn('vibe_transactions', 'amount')) { 
                $table->dropColumn('amount');
            }
        });
    }
}

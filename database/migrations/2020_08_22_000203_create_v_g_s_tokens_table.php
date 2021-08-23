<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVGSTokensTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vgs_tokens', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('payment_methods_id');
            $table->string('tokenex_token', 150);
            $table->string('vgs_token', 150);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('vgs_tokens');
    }
}

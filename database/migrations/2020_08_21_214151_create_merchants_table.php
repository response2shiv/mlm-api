<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMerchantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('merchants', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->boolean('test_mode');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        DB::table('merchants')->insert([
            [
                'name' => 'T1',
                'test_mode' => true,
                'is_enabled' => false
            ],
            [
                'name' => 'PayArc',
                'test_mode' => true,
                'is_enabled' => false
            ],
            [
                'name' => 'BitPay',
                'test_mode' => true,
                'is_enabled' => false
            ],
            [
                'name' => 'Metropolitan',
                'test_mode' => true,
                'is_enabled' => false
            ],
            [
                'name' => 'Unicrypt',
                'test_mode' => false,
                'is_enabled' => true
            ],
            [
                'name' => 'Payworx',
                'test_mode' => true,
                'is_enabled' => false
            ],
            [
                'name' => 'iPaytotal',
                'test_mode' => false,
                'is_enabled' => true
            ]
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('merchants');
    }
}

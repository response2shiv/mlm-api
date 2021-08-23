<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateShoppingCartSettings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shopping_cart_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('shopping_cart_unicrypt_minimum')->nullable()->default(30);
            $table->integer('max_total_cart')->nullable()->default(130);
            $table->boolean('shopping_cart_maintenance')->nullable()->default(false);
            $table->text('shopping_cart_maintenance_message')->nullable();
            $table->timestamps();
        });

        DB::table('shopping_cart_settings')->insert([
            ['id' => 1, 
            'shopping_cart_unicrypt_minimum' => 30,
            'max_total_cart' => 130,
            'shopping_cart_maintenance' => false,
            'shopping_cart_maintenance_message' => "The shopping cart is currently unavailable, please check back later"
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
        Schema::dropIfExists('shopping_cart_settings');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFieldsOnShoppingCartTableAndShoppingCartProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('shopping_carts', function (Blueprint $table) {
            $table->decimal('total',10,2)->default(0);
            $table->decimal('discount',10,2)->default(0);
            $table->integer('voucher_id')->nullable();
        });

        Schema::table('shopping_cart_products', function (Blueprint $table) {
            $table->decimal('product_price',10,2)->default(0);
            $table->decimal('sub_total',10,2)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('shopping_carts',function(Blueprint $table){
           $table->dropColumn('total');
           $table->dropColumn('discount');
           $table->dropColumn('voucher_id');
        });

        Schema::table('shopping_cart_products',function(Blueprint $table){
            $table->dropColumn('product_price');
            $table->dropColumn('sub_total');
        });
    }
}

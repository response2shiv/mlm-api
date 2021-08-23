<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddShippingToProducts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->smallInteger('is_taxable')->default(0)->nullable();
            $table->smallInteger('shipping_enabled')->default(0)->nullable();
            $table->smallInteger('allow_multiple_on_cart')->default(0)->nullable();
            $table->decimal('shipping_price', 8, 2)->default(0);
        });

        DB::table('producttype')->insert([
            ['id' => 8, 'typedesc' => 'Retail', 'statuscode' => 1]
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_taxable');
            $table->dropColumn('shipping_enabled');
            $table->dropColumn('shipping_price');
        });

        DB::table('producttype')->where('id', 8)->delete();
    }
}

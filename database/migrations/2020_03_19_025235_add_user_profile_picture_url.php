<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUserProfilePictureUrl extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //Add new column on Users table
        Schema::table('users', function ($table) {
            $table->text('profile_image_url')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //Drop column from Users table
        Schema::table('users', function ($table) {
            $table->dropColumn('profile_image_url');
        });
    }
}

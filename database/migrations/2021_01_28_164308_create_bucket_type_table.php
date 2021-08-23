<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBucketTypeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        DB::unprepared("
            CREATE TYPE bucket_type AS ( 
                placement_root bigint, 
                placement_position bigint,
                parent_id bigint,
                parent_tree_id bigint,
                bucket_id bigint,
                bucket_tag varchar(1),
                next_bucket smallint,
                level bigint 
                ); 
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::unprepared("DROP TYPE bucket_type CASCADE;");
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBucketTreePlanTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::unprepared("
            DROP TABLE  IF EXISTS bucket_tree_plan;
            DROP SEQUENCE IF EXISTS bucket_tree_plan_tid_seq;
            DROP TYPE IF EXISTS bucket_type CASCADE;
            -- drop and create the sequence used by the tree table
            CREATE SEQUENCE bucket_tree_plan_tid_seq START 1;
            
            --Create tree the table
            CREATE TABLE public.bucket_tree_plan
            (
                tid bigint NOT NULL DEFAULT nextval('bucket_tree_plan_tid_seq'::regclass),
                isassigned boolean DEFAULT false,
                uid bigint, -- user_id, id of the user being placed in the tree
                sid bigint, -- sponsor_id, id of the sponsor
                pid bigint, -- placement_root, id of the person the user is directly placed under
                rxtime timestamp, -- time of placement
                lvl bigint, -- level in tree from the placement_root
                stid bigint, -- tree id of the related sponsor
                ptid bigint, -- tree id of the related placement_root
                sbid bigint, -- id of the placement bucket in relation to the sponsor
                pbid bigint, -- id of the placement bucket in relation to the placement_root
                abid bigint, -- tree id of this user's A bucket
                bbid bigint, -- tree id of this user's B bucket
                cbid bigint, -- tree id of this user's C bucket
                auid bigint, -- user id of the user put inside of this user's bucket A
                buid bigint, -- user id of the user put inside of this user's bucket B
                cuid bigint, -- user id of the user put inside of this user's bucket C
                next_bucket bigint, -- next bucket to start placement 1-A 2-B 3-C
                CONSTRAINT bucket_tree_plan_pkey PRIMARY KEY (tid)
            )
            WITH (
                OIDS = FALSE
            );        

            -- =============================================
            -- Description: Resets the tree table and add base records
            -- Parameters: NOOP
            -- Returns: NOOP
            -- =============================================
            DO $$
            DECLARE counter integer := 1;
            BEGIN 
                TRUNCATE TABLE bucket_tree_plan;
                ALTER SEQUENCE bucket_tree_plan_tid_seq RESTART WITH 1;
                ALTER SEQUENCE bucket_tree_plan_tid_seq MINVALUE 1 RESTART 1;
                LOOP
                    EXIT WHEN counter = 100000;
                    INSERT INTO
                        public.bucket_tree_plan(
                            isassigned, 
                            uid,
                            sid,
                            pid,
                            rxtime,
                            lvl,
                            sbid,
                            pbid,
                            stid,
                            ptid,
                            abid,
                            bbid,
                            cbid,
                            auid,
                            buid,
                            cuid,                
                            next_bucket
                        )
                    VALUES
                        (
                            FALSE,
                            NULL,
                            NULL,
                            NULL,
                            NULL,
                            NULL,
                            NULL,
                            NULL,
                            NULL,
                            NULL,
                            (counter * 3)-1,
                            (counter * 3),
                            (counter * 3)+1,
                            NULL,
                            NULL,
                            NULL,
                            1
                    );
                    counter := counter + 1;
                END LOOP;
                DELETE FROM bucket_tree_plan WHERE tid=0;
                UPDATE bucket_tree_plan SET isassigned=TRUE, uid=2, sid=2, pid=1, rxtime=NOW() WHERE tid=1;
            END;
            $$
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bucket_tree_plan');
        DB::unprepared("DROP SEQUENCE bucket_tree_plan_tid_seq;");
    }
}

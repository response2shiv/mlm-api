<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFindAvailableBucketPosFunction extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::unprepared("        
        CREATE OR REPLACE FUNCTION public.find_available_bucket_pos(placement_root bigint, placement_bucket int) 
        RETURNS bucket_type
        LANGUAGE 'plpgsql' cost 100 volatile parallel UNSAFE as \$BODY\$ 
    -- =============================================
    -- Description: Locates the next available position for bucket/tree placement
    -- Parameters:
    --   @placement_root - TID of the requested user placement root
    --   @placement_bucket - numeric id of the bucket/leg used for placement [0=auto, 1=A, 2=B, 3=C]
    -- Returns:    trus if user has been placed or false if unable to place
    -- =============================================
    DECLARE 
        bucket_result bucket_type;
        bucket_id smallint = 0;
        a_bucket_id bigint = 0;
        b_bucket_id bigint = 0;
        c_bucket_id bigint = 0;
        rws bigint = 1;
        next_position bigint = 0;
        pos_limits bigint = 100;
        lvl bigint = 1;
        max_level_pos bigint = lvl * 3;
        min_pos bigint = 0;
        max_pos bigint = 0;
        root_a bigint = 0;
        root_c bigint = 0;
        sib_a bigint = 0;
        sib_b bigint = 0;
        sib_c bigint = 0;
        parent bigint = 0;
    BEGIN
        --get the search limit from the total table records;
        SELECT COUNT(bucket_tree_plan.tid) 
        FROM public.bucket_tree_plan 
        INTO pos_limits;
        root_a := placement_root;
        root_c := placement_root;
        LOOP
            EXIT WHEN rws = pos_limits;
            --look into the any or specific position open in the immediate LEVEL
            a_bucket_id := (root_a * 3)-1;
            b_bucket_id := (root_a * 3);
            c_bucket_id := (root_a * 3)+1;
            min_pos := (root_a * 3)-1;
            max_pos := (root_c * 3)+1;
            root_a := a_bucket_id;
            root_c := c_bucket_id;
            IF (lvl = 1) THEN
            --check if specific root_position is open
                CASE
                    WHEN placement_bucket = 1 THEN 
                        -- bucket A
                        min_pos := a_bucket_id;
                        max_pos := a_bucket_id;
                        root_a := a_bucket_id;
                        root_c := a_bucket_id;
                    WHEN placement_bucket = 2 THEN 
                        -- bucket B
                        min_pos := b_bucket_id;
                        max_pos := b_bucket_id;
                        root_a := b_bucket_id;
                        root_c := b_bucket_id;
                    WHEN placement_bucket = 3 THEN 
                        --bucket C
                        min_pos := c_bucket_id;
                        max_pos := c_bucket_id;
                        root_a := c_bucket_id;
                        root_c := c_bucket_id;
                    ELSE
                        --no bucket
                        min_pos := (root_a * 3)-1;
                        max_pos := (root_c * 3)+1;
                END CASE;
            END IF;
            IF (lvl = 2) THEN
                CASE
                    WHEN placement_bucket = 1 THEN 
                        -- bucket A
                        min_pos := a_bucket_id;
                        max_pos := c_bucket_id;
                        root_a := a_bucket_id;
                        root_c := a_bucket_id;
                    WHEN placement_bucket = 2 THEN 
                        -- bucket B
                        min_pos := b_bucket_id;
                        max_pos := c_bucket_id;
                        root_a := b_bucket_id;
                        root_c := b_bucket_id;
                    WHEN placement_bucket = 3 THEN 
                        --bucket C
                        min_pos := c_bucket_id;
                        max_pos := c_bucket_id;
                        root_a := c_bucket_id;
                        root_c := c_bucket_id;
                    ELSE
                        --no bucket
                        min_pos := (root_a * 3)-1;
                        max_pos := (root_a * 3)+1;
                    END CASE;
            END IF;
            --check if any of the three positions is open
            SELECT MIN(bucket_tree_plan.tid) 
            FROM bucket_tree_plan 
            WHERE bucket_tree_plan.isassigned = false 
            AND bucket_tree_plan.tid 
            BETWEEN min_pos AND max_pos INTO next_position;               
            IF(next_position IS NOT NULL) THEN
                -- get the bucket tag for the available position
                parent := ROUND(next_position/3::float);
                IF parent < 1 THEN
                    parent := 1;
                END IF;
                RAISE NOTICE 'BLAH: %', parent;
                sib_a := (parent *3) -1;
                sib_b := (parent *3);
                sib_c := (parent *3) +1;
                RAISE NOTICE 'parent: %, sib_a: % sib_b: % sib_c: %', parent, sib_a, sib_b, sib_c;
                SELECT bucket_tree_plan.uid
                FROM bucket_tree_plan 
                WHERE bucket_tree_plan.tid = parent INTO bucket_result.parent_id;  
                bucket_result.placement_root := parent;
                bucket_result.placement_position := next_position;
                bucket_result.bucket_id = placement_bucket;
                bucket_result.level = lvl;
                CASE
                    WHEN placement_bucket = 1 THEN 
                        -- bucket A
                        bucket_result.bucket_tag := 'A';
                        bucket_result.next_bucket := 2;
                    WHEN placement_bucket = 2 THEN 
                        -- bucket B
                        bucket_result.bucket_tag := 'B';
                        bucket_result.next_bucket := 3;
                    WHEN placement_bucket = 3 THEN 
                        --bucket C
                        bucket_result.bucket_tag := 'C';
                        bucket_result.next_bucket := 1;
                END CASE;                
                RETURN bucket_result;
            END IF;
            lvl := lvl + 1;
            rws := rws + 1;
        END LOOP;
        RETURN NULL;
    END;
    -- VERSION 1.0
    \$BODY\$;        
        ");        
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::unprepared("DROP FUNCTION public.find_available_bucket_pos");
    }
}

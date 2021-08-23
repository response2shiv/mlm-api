<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIsboPlaceInBpFunction extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::unprepared("    
        CREATE OR REPLACE FUNCTION public.isbo_place_in_bp(user_id bigint, sponsor_id bigint, placement_root bigint, placement_bucket int) 
            RETURNS boolean 
            LANGUAGE 'plpgsql' cost 100 volatile parallel UNSAFE as \$BODY\$ 
        -- =============================================
        -- Description: Executes placement of new user in trinary tree
        -- Parameters:
        --   @user_id - id of the user being placed into the tree
        --   @sponsor_id - id of the user's sponsor
        --   @placement_root - id of the requested user placement root
        --   @placement_bucket - numeric id of the bucket/leg used for placement [0=AUTO, 1=A, 2=B, 3=C]
        -- Returns:    trus if user has been placed or false if unable to place
        -- Manual Placement Example: public.isbo_place_in_bp(user_id,sponsor_id,placement_root,bucket_id)
        -- Auto Placement Example: public.isbo_place_in_bp(user_id,sponsor_id,sponsor_id,0)
        -- =============================================
        DECLARE 
            placement_root_tree_id bigint := 0;
            sponsor_tree_id bigint := 0;
            existing_user_id bigint := NULL;
            bucket bucket_type;
            a_bucket_id bigint := 0;
            b_bucket_id bigint := 0;
            c_bucket_id bigint := 0;
            placement_bucket_id bigint :=0;
        BEGIN
            --check if user Id alread in the tree. If so abort and return false
            SELECT bucket_tree_plan.tid
            FROM bucket_tree_plan
            WHERE bucket_tree_plan.uid = user_id INTO existing_user_id;
            IF (existing_user_id IS NOT NULL) THEN
                RETURN false;
            END IF;
            --Check if the placement_root is in the tree, if not return false;
            SELECT bucket_tree_plan.tid
            FROM bucket_tree_plan
            WHERE bucket_tree_plan.uid = placement_root INTO placement_root_tree_id;
            IF(placement_root_tree_id IS NULL) THEN
                RETURN false;
            END IF;
            --Check if the sponsor is in the tree, if not return false;
            SELECT bucket_tree_plan.tid
            FROM bucket_tree_plan
            WHERE bucket_tree_plan.uid = sponsor_id INTO sponsor_tree_id;
            IF(sponsor_tree_id IS NULL) THEN
                RETURN false;
            END IF;
            IF(placement_bucket = 0) THEN
                SELECT bucket_tree_plan.next_bucket
                FROM bucket_tree_plan
                WHERE bucket_tree_plan.uid = sponsor_id INTO placement_bucket;
                --Sets the placement root to the sponsor id for auto placement.
                placement_root_tree_id := sponsor_tree_id;
            END IF;
            SELECT * FROM public.find_available_bucket_pos(placement_root_tree_id, placement_bucket) INTO bucket;
            RAISE INFO 'Pacement Root: %, Placement Position: %, Placement Parent Id: % Requested Bucket Id: %, Requested Bucket Tag: %, Next bucket: %, Level: %', 
                    bucket.placement_root,
                    bucket.placement_position,
                    bucket.parent_id,
                    bucket.bucket_id,
                    bucket.bucket_tag,
                    bucket.next_bucket,
                    bucket.level;
            IF(bucket.placement_position IS NOT NULL AND bucket.placement_position > 0) THEN
                a_bucket_id := (bucket.placement_root *3) -1;
                b_bucket_id := (bucket.placement_root *3);
                c_bucket_id := (bucket.placement_root *3) +1;
                CASE    
                    WHEN bucket.placement_position = a_bucket_id THEN
                        placement_bucket_id := 1;
                    WHEN bucket.placement_position = b_bucket_id THEN
                        placement_bucket_id := 2;
                    WHEN bucket.placement_position = c_bucket_id THEN
                        placement_bucket_id := 3;
                    ELSE
                        placement_bucket_id :=0;
                END CASE;
                -- update record placing the new user
                UPDATE public.bucket_tree_plan SET  
                    isassigned = TRUE,
                    uid = user_id, 
                    sid = sponsor_id,
                    pid = bucket.parent_id,
                    rxtime = NOW(),
                    lvl = bucket.level,
                    stid = sponsor_tree_id,
                    ptid = bucket.placement_root,
                    sbid = placement_bucket,
                    pbid = placement_bucket_id,            
                    next_bucket = 1
                WHERE tid = bucket.placement_position;
                --update sponsor record to track the next bucket    
                UPDATE public.bucket_tree_plan SET  
                    next_bucket = bucket.next_bucket
                WHERE tid = sponsor_tree_id; 
                --update placement parent for a-b-c uid
                CASE    
                    WHEN bucket.placement_position = a_bucket_id THEN
                        UPDATE public.bucket_tree_plan SET auid = user_id WHERE tid = bucket.placement_root;
                    WHEN bucket.placement_position = b_bucket_id THEN
                        UPDATE public.bucket_tree_plan SET buid = user_id WHERE tid = bucket.placement_root;
                    WHEN bucket.placement_position = c_bucket_id THEN
                        UPDATE public.bucket_tree_plan SET cuid = user_id WHERE tid = bucket.placement_root;
                    ELSE
                        RAISE NOTICE 'a: % b: % c:% parent: %', a_bucket_id, b_bucket_id, c_bucket_id, bucket.parent_id;
                END CASE;
                RETURN true;
            ELSE
                RETURN false;
            END IF;
        END;
        -- VERSION 1.0
        \$BODY\$; ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::unprepared("DROP FUNCTION public.isbo_place_in_bp");
    }
}

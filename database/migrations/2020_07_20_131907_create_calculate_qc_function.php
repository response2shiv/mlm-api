<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCalculateQcFunction extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("DROP FUNCTION IF EXISTS calculate_pqc;");
        
        // Create the function
        DB::statement('create function calculate_pqc(dist_id character varying, from_date timestamp without time zone, to_date timestamp without time zone) returns bigint
            language plpgsql
        as
        $$
        DECLARE
         	pqc float8;
         BEGIN
        
        
            SELECT COALESCE(SUM(p.qc), 0) into pqc
                FROM users u
                JOIN orders o ON u.id = o.userid
                JOIN "orderItem" oi ON o.id = oi.orderid
                JOIN products p ON p.id=oi.productid
            WHERE o.created_dt >= from_date
                AND o.created_dt <= to_date
                AND o.statuscode IN (1, 6, 9, 10, 11)
                AND u.distid = dist_id;
        
            if pqc > 1 then
                pqc := 1;
            end if;
            
        	RETURN pqc;
        
        END;
        $$;');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP FUNCTION IF EXISTS calculate_pqc;");
    }
}

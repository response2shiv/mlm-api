<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Class WorldSeriesBonusRunsService
 * @package App\Services
 */
class WorldSeriesBonusRunsService
{

    public static function calculate($from, $to) 
    {
    	$sql = "
	    	SELECT COUNT(cus.id) / 50 as bonus_run
	             , wso.runs 
	             , sponsor.id as sponsor_id
	             , master_sponsor.id as master_sponsor_id

	        FROM customers cus 

	  INNER JOIN users sponsor 
	          ON sponsor.id = cus.userid 

	  INNER JOIN users master_sponsor 
	          ON sponsor.sponsorid = master_sponsor.distid 

	  INNER JOIN world_series_overviews wso 
	          ON wso.sponsor_id = sponsor.id 

	       WHERE cus.created_date >= '$from'
	         AND cus.created_date <= '$to' 

	    GROUP BY wso.runs, sponsor.id, master_sponsor_id

	      HAVING COUNT(cus.id) >= 50 
	         AND wso.runs >= 4

	    ORDER BY bonus_run DESC, runs ASC ";

    	return DB::select($sql);
	}
}

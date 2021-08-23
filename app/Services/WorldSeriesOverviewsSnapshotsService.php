<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Class WorldSeriesOverviewsSnapshotsService
 * @package App\Services
 */
class WorldSeriesOverviewsSnapshotsService
{

    public static function updateEverySunday($snapshot_date)
    {

    	# Helper to populate snapshots from oldest month
    	if (!is_null($snapshot_date)) {
    		$date  = ", 	'".$snapshot_date."'";
    		$month = " extract (month from '$snapshot_date'::DATE) "; 

    	} else {
	     	$date  = ", ((now() at time zone 'America/Chicago')::date - interval '1 day')::date AS snapshot_date ";
    		$month = " extract (month from CURRENT_DATE) "; 
    	}

  		DB::beginTransaction();

		$sql = " INSERT INTO world_series_overviews_snapshots (
				  sponsor_id
				, first_base_user_id
				, second_base_user_id
				, third_base_user_id
				, runs
				, hits
				, errors
				, total
				, season_name
				, season_period
				, snapshot_date
				, created_at
				, updated_at
				, bonus_runs
			)
			(
				SELECT sponsor_id
				     , first_base_user_id
				     , second_base_user_id
				     , third_base_user_id
				     , runs
				     , hits
				     , errors
				     , total
				     , season_name
				     , season_period
				     $date
				     , created_at
				     , updated_at
				     , bonus_runs
				 FROM world_series_overviews

			    WHERE EXTRACT (MONTH FROM season_period) = $month
			)
		";

		DB::insert($sql);
		DB::commit();
    }

}

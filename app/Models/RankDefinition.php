<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DB;

class RankDefinition extends Model {

    const RANK_AMBASSADOR = 'Ambassador';

    protected $table = "rank_definition";
    public $timestamps = false;

    public static function getUpperRankInfo($currentRankVal) {
        return DB::table('rank_definition')
                        ->select('rankval', 'rankdesc')
                        ->where('rankval', '>', $currentRankVal)
                        ->orderBy('rankval', 'asc')
                        ->get();
    }

}

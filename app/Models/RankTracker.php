<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RankTracker extends Model
{
    //Set Table
    protected $table = 'rank_tracker';

    public static function isRankRunning()
    {
        $tracker = self::find(1);
        if($tracker){
            return $tracker->is_running;
        }else{
            return $tracker->false;
        }        
    }
}

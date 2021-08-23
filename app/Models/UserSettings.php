<?php

namespace App\Models;

use Auth;
use Illuminate\Database\Eloquent\Model;
use GuzzleHttp\Client;
use Log;
use DB;

class UserSettings extends Model {

    public $timestamps = true;
    
    
    public static function getByUserId($UserId) {        
        return DB::table('user_settings')
                        ->where('user_id', $UserId)
                        ->first();
    }

}

<?php

namespace App\Models;

use DB;
use Illuminate\Database\Eloquent\Model;

class UserActivityLog extends Model {

    protected $table = "user_activity_log";
    
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'ip_address',
        'ip_details',
        'action',
        'old_data',
        'new_data'
    ];
}

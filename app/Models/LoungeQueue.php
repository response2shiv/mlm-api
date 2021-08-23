<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Helper;
use Log;

class LoungeQueue extends Model
{
    protected $table = 'lounge_queue';
    protected $fillable = ['user_id', 'sponsor_id', 'is_assigned'];


    public function user()
    {
        return $this->belongsTo('App\Models\User', 'user_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VibeToken extends Model
{

    protected $table = "vibe_token";

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
    	'customer_id', 
    	'vibe_id',
    	'status',
        'type',
        'origin'
    ];

    const status = [
        'invited', 
        'accepted', 
        'in-process', 
        'completed'
    ];


    public static function getStatus()
    {
        return self::status;
    }

}

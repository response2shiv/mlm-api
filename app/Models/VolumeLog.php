<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VolumeLog extends Model
{
    public $timestamps = false;
    protected $table = 'volume_logs';
    protected $fillable = [
        'user_id',
        'order_id',
        'date_distributed',
        'bv',
        'qv',
        'cv',
        'pev',
        'bucket_id',
        'status',
        'week_no',
        'adjustment',
        'adjustment_dt',
        'adjustment_userid',
    ];

}

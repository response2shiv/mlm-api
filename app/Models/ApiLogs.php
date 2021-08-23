<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiLogs extends Model
{
    public $timestamps = false;
    protected $table = 'api_logs';
    protected $fillable = [
        'user_id',
        'api',
        'endpoint',
        'request',
        'response'
    ];

}

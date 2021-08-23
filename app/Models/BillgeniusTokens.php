<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillgeniusTokens extends Model
{
    protected $fillable = [
        'user_id',
          'customer_id',
          'billgenius_customer_id',
          'distid'
    ];
}

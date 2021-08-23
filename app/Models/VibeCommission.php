<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class VibeCommission extends Model
{
    protected $table = 'vibe_commissions';

    public $fillable = [
        'user_id',
        
        'rider_id',
        'rider_name',

        'driver_id',
        'driver_name',

        'ride_date',
        'ride_id',
        'ride_commission',
        'direct_payout',
        'cv',

        'calculation_date',
        'paid_date',
        'status'
    ];

    public $timestamps = false;

    protected $dates = [
        'ride_date',
        'calculation_date',
        'paid_date'
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo('App\User');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function order()
    {
        return $this->belongsTo('App\Order');
    }
}

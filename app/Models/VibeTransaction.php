<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VibeTransaction extends Model
{
    protected $table = "vibe_transactions";

    protected $fillable = [
    	'customer_id',
		'rider_id',
		'driver_id',
		'ride_id',
		'status',
		'total',
		'distance',
		'created_at',
		'updated_at',
		'driver_sponsor',
		'customer_sponsor',
		'ride_status',
		'payment_status',
		'ride_date',
		'duration',
		'commission_amount'
    ];

	protected $casts = [
    	'commission_amount' => 'decimal:2',
	];


 	public function setCommissionAmountAttribute($value)
    {
        $this->attributes['commission_amount'] = number_format($value/100, 2);
    }	

}

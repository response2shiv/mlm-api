<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPaymentAddress extends Model
{
    protected $fillable = [
        'address1',
        'address2',
        'city',
        'state',
        'zipcode',
        'country_code'
    ];

    public function paymentMethod()
    {
        return $this->belongsTo(UserPaymentMethod::class);
    }
}

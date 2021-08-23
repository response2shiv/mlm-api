<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPaymentMethodMerchant extends Model
{
    protected $fillable = [
        'user_payment_method_id'
    ];

    public function paymentMethod()
    {
        return $this->belongsTo(UserPaymentMethod::class);
    }
}

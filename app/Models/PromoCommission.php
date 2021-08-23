<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class BinaryCommission
 * @package App
 */
class PromoCommission extends Model
{
    const CALCULATED_STATUS = 'calculated';
    const POSTED_STATUS = 'posted';
    const PAID_STATUS = 'paid';

    protected $fillable = [
        'user_id',
        'amount',
        'created_dt',
        'status',
        'promo'
    ];

    /**
     * {@inheritDoc}
     */
    protected $table = 'promo_commission';

    /**
     * {@inheritDoc}
     */
    public $timestamps = false;

    protected $dates = [
        'created_dt'
    ];

    /**
     * @return BelongsTo
     */
    public function user()
    {
        return $this->belongsTo('App\User');
    }
}

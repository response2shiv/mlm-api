<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorldSeriesErrors extends Model
{
    protected $table = 'world_series_errors';

    protected $fillable = [
        'is_enrollment',
        'is_upgrade',
        'order_id',
        'order_refund_id',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function order()
    {
        return $this->belongsTo('App\Models\Order')->withDefault();
    }

}

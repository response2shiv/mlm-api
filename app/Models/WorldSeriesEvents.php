<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorldSeriesEvents extends Model
{
    protected $table = 'world_series_events';

    protected $fillable = [
        'position',
        'event_type',
        'description',
        'active',
        'sponsor_id',
        'user_id',
        'order_id',
        'moved_by_user_id',
        'created_at'
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo('App\Models\User')->withDefault();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function sponsor()
    {
        return $this->belongsTo('App\Models\User', 'sponsor_id')->withDefault();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function order()
    {
        return $this->belongsTo('App\Models\Order')->withDefault();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function hasTicket()
    {
        return $this->hasOne('App\Models\OrderItem', 'orderid', 'order_id')->where('productid', 38);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function ticket()
    {
        return $this->belongsTo('App\Models\Product', 'productid', 'id');
    }


}

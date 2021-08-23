<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorldSeriesOverviews extends Model
{
    protected $table = 'world_series_overviews';

    protected $fillable = [
		'sponsor_id',
		'first_base_user_id',
		'second_base_user_id',
		'third_base_user_id',
		'runs',
		'hits',
		'errors',
		'total',
		'season_name',
		'season_period',
        'bonus_runs'
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function sponsor()
    {
        return $this->belongsTo('App\Models\User', 'sponsor_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function firstBaseUser()
    {
        return $this->belongsTo('App\Models\User', 'first_base_user_id')->withDefault();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function secondBaseUser()
    {
        return $this->belongsTo('App\Models\User', 'second_base_user_id')->withDefault();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function thirdBaseUser()
    {
        return $this->belongsTo('App\Models\User', 'third_base_user_id')->withDefault();;
    }


    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function firstUserEvent()
    {
        return $this->belongsTo('App\Models\WorldSeriesEvents', 'first_base_user_id', 'user_id')->withDefault();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function secondUserEvent()
    {
        return $this->belongsTo('App\Models\WorldSeriesEvents', 'second_base_user_id', 'user_id')->withDefault();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function thirdUserEvent()
    {
        return $this->belongsTo('App\Models\WorldSeriesEvents', 'third_base_user_id', 'user_id')->withDefault();
    }

}

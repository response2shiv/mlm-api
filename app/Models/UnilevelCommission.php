<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model for Site Settings
 *
 * @package App\Models
 */
class UnilevelCommission extends Model
{
    /**
     * Name of the table.
     *
     * @var string
     */
    protected $table = 'unilevel_commission';

    /**
     * Disable timestamps for this model.
     *
     * @var bool
     */
    public $timestamps = false;


    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function sourceUser()
    {
        return $this->belongsTo('App\Models\User', 'dist_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function order()
    {
        return $this->belongsTo('App\Models\OrderItem');
    }

    /**
     * @param $user
     * @param $level
     * @param $date
     * @return mixed
     */
    public static function getCommissionRow($user, $level, $date)
    {
        return UnilevelCommission::where('user_id', $user->id)
            ->where('level', $level)
            ->whereDate('displaying_date', $date)
            ->first();
    }
}

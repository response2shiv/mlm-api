<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model for Site Settings
 *
 * @package App\Models
 */
class UserStatistic extends Model
{
    /**
     * Name of the table.
     *
     * @var string
     */
    protected $table = 'user_statistic';

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
        return $this->belongsTo('App\User');
    }
}

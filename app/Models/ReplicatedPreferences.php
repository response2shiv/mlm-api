<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model for Site Settings
 *
 * @package App\Models
 */
class ReplicatedPreferences extends Model
{
    const TYPE_DISPLAYED = 1;
    const TYPE_CO_NAME = 2;
    const TYPE_BUSINESS = 3;

    /**
     * Name of the table.
     *
     * @var string
     */
    protected $table = 'replicated_preferences';

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

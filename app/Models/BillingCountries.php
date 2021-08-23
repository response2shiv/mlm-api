<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class BillingCountries
 * @package App
 */
class BillingCountries extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'billing_countries';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;
}

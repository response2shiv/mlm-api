<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerType extends Model
{
    protected $table = "customer_types";

    public $timestamps = false;

   	const TYPE_DRIVER = 'driver';
   	const TYPE_RIDER  = 'rider';

}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerTypePivot extends Model
{
    protected $table = "customer_type_pivot";


    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
    	'customer_id', 
    	'customer_type_id'
    ];


}

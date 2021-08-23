<?php

namespace App\Models;

use Auth;
use DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class ShoppingCartSettings extends Model
{

    protected $table = "shopping_cart_settings";
    public $timestamps = false; 

}

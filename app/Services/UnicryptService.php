<?php

namespace App\Services;

use App\Models\Unicrypt;
use Illuminate\Http\Request;

class UnicryptService
{

    public static function createInvoice($user, $preOrder) 
    {
   		$preOrderItem = $preOrder->preOrderItems->first();

   		$callback_url = env('UNICRYPT_API_CALLBACK_URL');

        $response = Unicrypt::create($user, $preOrder->ordertotal, $preOrderItem->product->productname, $preOrderItem->product->productdesc, $callback_url);
    
        return $response["orderhash"] ? $response : false;
    }

}
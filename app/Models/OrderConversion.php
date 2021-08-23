<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DB;
use App\Helpers\CurrencyConverter;

class OrderConversion extends Model
{
    protected $table = "order_conversions";

    public $fillable = [
        'order_id',
        'original_amount',
        'original_currency',
        'converted_amount',
        'converted_currency',
        'exchange_rate',
        'created_at',
        'updated_at',
        'expires_at'
    ];

    public $dates = [
        'expires_at'
    ];

    public function order()
    {
        return $this->belongsTo('App\Orders');
    }

    public static function setOrderId($orderConversionId, $orderId)
    {
        $orderConversion = static::query()->find($orderConversionId);
        $orderConversion->order_id = $orderId;
        $orderConversion->save();
    }

    public static function getOrderConversionById($orderId)
    {
        $orderConversion = DB::table('order_conversions')
        ->where('order_id', $orderId)
        ->first();
        if (is_object($orderConversion)){
            $orderConversion->display_amount = ($orderConversion->display_amount) ? $orderConversion->display_amount : CurrencyConverter::convertCurrency(number_format($orderConversion->original_amount,2,'',''), $orderConversion->converted_currency, null);
            DB::table('order_conversions')
            ->where('order_id', $orderId)
            ->update([
                'display_amount' => $orderConversion->display_amount
            ]);
        }
        return $orderConversion;
    }
}

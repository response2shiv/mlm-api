<?php

namespace App\Models;

use Auth;
use DB;
use Illuminate\Database\Eloquent\Model;
use App\Helpers\Util;
use App\Helpers\CurrencyConverter;
use App\Models\OrderConversion;
use Illuminate\Support\Facades\Log;

class PreOrder extends Model
{

    protected $table = "pre_orders";
    public $timestamps = false;

    const ORDER_ACTIVE = 1;
    const ORDER_STATUS_REFUND = 6;
    const ORDER_STATUS_PARTIAL_REFUND = 9;
    const ORDER_STATUS_REFUNDED = 10;
    const ORDER_STATUS_PARTIALLY_REFUNDED = 11;

    public $fillable = [
        'userid',
        'statuscode',
        'ordersubtotal',
        'ordertax',
        'ordertotal',
        'orderbv',
        'orderqv',
        'ordercv',
        'trasnactionid',
        'updated_at',
        'created_at',
        'payment_methods_id',
        'user_payment_methods_id',
        'shipping_address_id',
        'inv_id',
        'created_date',
        'created_time',
        'processed',
        'coupon_code',
        'order_refund_ref',
        'created_dt',
        'orderqc',
        'orderac'
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo('App\Models\User', 'userid');
    }


    public function preOrderItems()
    {
        return $this->hasMany('App\Models\PreOrderItem', 'orderid', 'id');
    }

    /**
     * @param $userId
     * @param $subtotal
     * @param $orderTotal
     * @param $orderBV
     * @param $orderQV
     * @param $orderCV
     * @param $transactionId
     * @param $paymentMethodId
     * @param $shippingAddressId
     * @param $invId
     * @param string $createdDate
     * @param string $discountCode
     * @param null $orderStatus
     * @param null $order_refund_ref
     * @param int $orderQC
     * @param int $orderAC
     * @return mixed
     */
    public static function addNew(
        $userId,
        $subtotal,
        $orderTotal,
        $orderBV,
        $orderQV,
        $orderCV,
        $transactionId,
        $paymentMethodId,
        $shippingAddressId,
        $invId,
        $createdDate = '',
        $discountCode = '',
        $orderStatus = null,
        $order_refund_ref = null,
        $orderQC = 0,
        $orderAC = 0,
        $isTSBOrder = null
    ) {
        $rec = new PreOrder();
        $rec->userid = $userId;
        $rec->statuscode = (empty($orderStatus) ? 1 : $orderStatus);
        $rec->ordersubtotal = $subtotal;
        $rec->ordertotal = $orderTotal;
        $rec->orderbv = $orderBV;
        $rec->orderqv = $orderQV;
        $rec->ordercv = $orderCV;
        $rec->orderqc = $orderQC;
        $rec->orderac = $orderAC;
        $rec->trasnactionid = $transactionId;
        $rec->payment_methods_id = $paymentMethodId;
        $rec->shipping_address_id = $shippingAddressId;
        $rec->inv_id = $invId;
        $rec->coupon_code = $discountCode;
        $rec->order_refund_ref = $order_refund_ref;
        if (!$isTSBOrder) {
            if (!empty($createdDate)) {
                $rec->created_date = $createdDate;
                $rec->created_dt = $createdDate . " " . Util::getCurrentTime();
            } else {
                $rec->created_date = Util::getCurrentDate();
                $rec->created_dt = Util::getCurrentDateTime();
            }
            $rec->created_time = Util::getCurrentTime();
        } else {
            $rec->created_date = date("Y-m-d", strtotime($createdDate));
            $rec->created_dt = $createdDate;
            $rec->created_time = date("h:i:s", strtotime($createdDate));
        }

        $rec->save();
        return $rec->id;
    }

    public static function updateRec($orderId, $rec, $req)
    {
        $createdDt = $req->created_date . " " . Utill::getCurrentTime();
        $r = PreOrder::find($orderId);
        $r->ordertotal = $req->ordertotal;
        $r->ordersubtotal = $req->ordersubtotal;
        $r->orderbv = $req->orderbv;
        $r->orderqv = $req->orderqv;
        $r->ordercv = $req->ordercv;
        $r->orderqc = $req->orderqc;
        $r->orderac = $req->orderac;
        $r->created_date = $req->created_date;
        $r->created_dt = $createdDt;
        $r->save();
        //
        UpdateHistory::orderUpdate($orderId, $rec, $req);

        DB::table('orderItem')
            ->where('orderid', $orderId)
            ->update(['created_dt' => $createdDt]);
    }

    public static function getById($id)
    {
        return DB::table('orders')
            ->where('id', $id)
            ->first();
    }

    /**
     * @param $id
     * @return Order|
     */
    public static function getActiveOrder($id)
    {
        return PreOrder::query()
            ->where('id', $id)
            ->whereIn('statuscode', [self::ORDER_ACTIVE, self::ORDER_STATUS_PARTIALLY_REFUNDED])
            ->where('order_refund_ref', null)
            ->first();
    }

    public static function getByUser($id)
    {
        Log::info('User ID received was ' . $id);
        $orders = DB::table('orders')
            ->selectRaw('*, orders.id as id_order ')
            ->leftjoin('order_conversions', 'orders.id', '=', 'order_conversions.order_id')
            ->where('userid', $id)
            ->where('trasnactionid', 'not like', '%AMB%')
            ->where('trasnactionid', 'not like', '%SOR%')
            // ->orWhereNull ('trasnactionid')
            ->orWhereRaw(DB::raw("userid = " . $id . " AND trasnactionid is NULL"))
            ->orderBy('created_date', 'desc')
            ->get();
        return $orders;
    }

    public static function getInvoiceByUser($id)
    {
        Log::info('User ID received was ' . $id);
        $orders = DB::table('orders')
            ->selectRaw('*,orders.id as id_order')
            ->leftjoin('orderItem', 'orders.id', '=', 'orderItem.orderid')
            ->leftjoin('order_conversions', 'orders.id', '=', 'order_conversions.order_id')
            ->where('orderItem.productid', '<>', Product::ID_TRAVEL_SAVING_BONUS)
            ->where('userid', $id)
            ->Where(function ($sq) use ($id) {
                $sq->Where('trasnactionid', 'not like', '%AMB%')
                ->orWhere('trasnactionid', 'not like', '%SOR%')
                ->orWhereNull('trasnactionid');
            })
            ->orderBy('orders.created_dt', 'desc')
            ->get();
        return $orders;
    }

    public static function getUserPreOrder($id)
    {
        return DB::table('pre_orders')
            ->where('id', $id)
            ->where('userid', Auth::user()->id)
            ->first();
    }

    public static function createOrderForDonation($orderId, $cartProduct)
    {
        $bv = $cartProduct->product->bv * $cartProduct->quantity;
        $qv = $cartProduct->product->qv * $cartProduct->quantity;
        $cv = $cartProduct->product->cv * $cartProduct->quantity;

        PreOrderItem::addNew($orderId, $cartProduct->product_id, $cartProduct->quantity, $cartProduct->product_price, $bv, $qv, $cv);
    }

    public static function createOrderForSimpleProduct($orderId, $cartProduct)
    {
        $bv = $cartProduct->product->bv * $cartProduct->quantity;
        $qv = $cartProduct->product->qv * $cartProduct->quantity;
        $cv = $cartProduct->product->cv * $cartProduct->quantity;

        PreOrderItem::addNew($orderId, $cartProduct->product_id, $cartProduct->quantity, $cartProduct->product_price, $bv, $qv, $cv);
    }

    public static function createOrderForUpgrade($orderId, $cartProduct)
    {
        $bv = $cartProduct->product->bv * $cartProduct->quantity;
        $qv = $cartProduct->product->qv * $cartProduct->quantity;
        $cv = $cartProduct->product->cv * $cartProduct->quantity;

        PreOrderItem::addNew($orderId, $cartProduct->product_id, $cartProduct->quantity, $cartProduct->product_price, $bv, $qv, $cv);
    }

    public static function createOrderForTicket($orderId, $cartProduct)
    {
        $bv = $cartProduct->product->bv * $cartProduct->quantity;
        $qv = $cartProduct->product->qv * $cartProduct->quantity;
        $cv = $cartProduct->product->cv * $cartProduct->quantity;

        PreOrderItem::addNew($orderId, $cartProduct->product_id, $cartProduct->quantity, $cartProduct->product_price, $bv, $qv, $cv);
    }

    public static function createOrderForMembership($orderId, $cartProduct)
    {
        $bv = $cartProduct->product->bv * $cartProduct->quantity;
        $qv = $cartProduct->product->qv * $cartProduct->quantity;
        $cv = $cartProduct->product->cv * $cartProduct->quantity;

        PreOrderItem::addNew($orderId, $cartProduct->product_id, $cartProduct->quantity, $cartProduct->product_price, $bv, $qv, $cv);
    }

}
<?php

namespace App\Models;

use DB;
use Illuminate\Database\Eloquent\Model;
use App\Helpers\Util;

class PreOrderItem extends Model {

    protected $table = "pre_order_items";
    public $timestamps = false;

    public $fillable = [
        'orderid',
        'productid',
        'quantity',
        'itemprice',
        'bv',
        'qv',
        'cv',
        'created_at',
        'updated_at',
        'created_date',
        'created_time',
        'discount_coupon',
        'created_dt',
        'qc',
        'ac',
        'will_be_attend',
        'orderhash'
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function product()
    {
        return $this->belongsTo('App\Models\Product', 'productid');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function preorder()
    {
        return $this->belongsTo('App\Models\PreOrder', 'orderid');
    }

    /**
     * @param $orderId
     * @param $productId
     * @param $quantity
     * @param $itemPrice
     * @param $bv
     * @param $qv
     * @param $cv
     * @param bool $saveUpdateHistory
     * @param null $discount_voucher_id
     * @param int $qc
     * @param int $ac
     * @return mixed
     */
    public static function addNew(
        $orderId,
        $productId,
        $quantity,
        $itemPrice,
        $bv,
        $qv,
        $cv,
        $saveUpdateHistory = false,
        $discount_voucher_id = null,
        $qc = 0,
        $ac = 0
    ) {
        $rec = new PreOrderItem();
        $rec->orderid = $orderId;
        $rec->productid = $productId;
        $rec->quantity = $quantity;
        $rec->itemprice = $itemPrice;
        $rec->bv = $bv;
        $rec->qv = $qv;
        $rec->cv = $cv;
        $rec->qc = $qc;
        $rec->ac = $ac;
        $rec->discount_voucher_id = $discount_voucher_id;

        $septemberSubscriptionIds = array(80,81,82,83);                
        if ( in_array($productId, $septemberSubscriptionIds) ){
            $rec->created_date = Util::getCurrentDateSeptember();
            $rec->created_time = Util::getCurrentTimeSeptember();
            $rec->created_dt = Util::getCurrentDateTimeSeptember();
        }else{
            $rec->created_date = Util::getCurrentDate();
            $rec->created_time = Util::getCurrentTime();
            $rec->created_dt = Util::getCurrentDateTime();
        }
        
        $rec->save();

        if ($saveUpdateHistory) {
            UpdateHistory::orderItemAdd($rec->id, $rec);
        }

        return $rec->id;
    }

    public static function isAlreadyPurchased($userId, $productId) {
        $count = DB::table('orders as a')
                ->join('orderItem as b', 'a.id', '=', 'b.orderid')
                ->where('a.userid', $userId)
                ->where('b.productid', $productId)
                ->count();
        return $count > 0;
    }

    public static function getOrderItem($orderId) {
        return DB::table('orderItem as a')
                        ->join('orders as b', 'a.orderid', '=', 'b.id')
                        ->join('products as c', 'a.productid', '=', 'c.id')
                        ->select('c.productname', 'c.id as prod_id', 'a.discount_voucher_id', 'a.itemprice', 'a.bv', 'a.qv', 'a.cv', 'a.id as item_id', 'a.is_refunded')
                        ->where('b.id', $orderId)
                        ->get();
    }



    public static function getById($recId) {
        return DB::table('orderItem')
                        ->where('id', $recId)
                        ->first();
    }

    public static function updateRec($itemId, $rec, $req) {
        $r = OrderItem::find($itemId);
        $r->productid = $req->productid;
        $r->itemprice = $req->itemprice;
        $r->bv = $req->bv;
        $r->qv = $req->qv;
        $r->cv = $req->cv;
        $r->qc = $req->qc;
        $r->ac = $req->ac;
        $r->save();
        //
        UpdateHistory::orderItemUpdate($itemId, $rec, $req);
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->preorder()->first()->user()->first();
    }

    public function getDiscountCoupon()
    {
        return DiscountCoupon::query()
            ->where('id', $this->discount_voucher_id)
            ->first();
    }

    public function getActiveDiscountCoupon()
    {
        return DiscountCoupon::query()
            ->where('id', $this->discount_voucher_id)
            ->where('is_used', 0)
            ->where('is_active', 1)
            ->first();
    }
}

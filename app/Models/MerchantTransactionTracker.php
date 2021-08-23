<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Log;
use DB;

class MerchantTransactionTracker extends Model
{
    //
    const MERCHANT_UNICRYPT = 5;
    const MERCHANT_IPAYTOTAL = 7;

    protected $table = "merchant_transaction_tracker";

    public static function getPendingOrders(){
        $purge_due_time = Carbon::now()->subHours(6);
        return MerchantTransactionTracker::where('cron_processed', 0)
        ->whereIn('status', ['EXPIRED','UNPAID','CANCELLED'])
        ->whereDate('created_at', '<', $purge_due_time)->get();
    }

    /**
    * Get the pre_order of the transaction tracker.
    */
    public function preOrder()
    {
        return $this->belongsTo('App\Models\PreOrder', 'pre_order_id', 'id');
    }

}

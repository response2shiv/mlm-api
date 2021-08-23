<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Log;
use DB;

class UnicryptInvoiceTracker extends Model
{
    //Table to be used
    protected $table = 'unicrypt_invoice_tracker';

    public static function getPendingOrders(){
        $purge_due_time = Carbon::now()->subHours(6);
        return UnicryptInvoiceTracker::where('cron_processed', 0)
        ->whereIn('status', ['EXPIRED','UNPAID','CANCELLED'])
        ->whereDate('created_at', '<', $purge_due_time)->get();
    }
}

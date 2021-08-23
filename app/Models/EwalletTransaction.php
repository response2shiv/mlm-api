<?php

namespace App\Models;

use App\Helpers\Util;
use DB;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Log;

class EwalletTransaction extends Model {

    protected $table = "ewallet_transactions";
    public $timestamps = false;

    const TYPE_WITHDRAW = "WITHDRAW";
    const TYPE_DEPOSIT = "DEPOSIT";
    const TYPE_CODE_PURCHASE = "COUP_CODE_PURCHASE";
    const TYPE_CODE_REFUND = "COUP_CODE_REFUND";
    const TYPE_TRANSACTION_FEE = "TRANSACTION FEE";
    const TYPE_UPGRADE_PACKAGE = 'UPGRADE_PACKAGE';
    const TYPE_UPGRADE_REFUND = 'UPGRADE_REFUND';
    const TYPE_PURCHASED_VOUCHER_REFUND = 'D_VOUCHER_REFUND';
    const TYPE_CHECKOUT_TICKET = 'CHECKOUT_TICKET';
    const TYPE_SUBSCRIPTION_REFUND = 'SUBSCRIPTION_REFUND';
    const TYPE_FOUNDATION = 'FOUNDATION';
    const TYPE_OTHER_REFUND = 'OTHER_REFUND';
    const TYPE_REFUND = 'REFUND';
    const TYPE_CHECKOUT_SHOP = 'CHECKOUT_SHOP';
    const TYPE_SHOPPING_CART = 'SHOPPING_CART';


    const ADJUSTMENT_ADD = "ADJUSTMENT_ADD";
    const ADJUSTMENT_DEDUCT = "ADJUSTMENT_DEDUCT";
	const MONTHLY_SUBSCRIPTION = "MONTHLY_SUBSCRIPTION";
	const REACTIVATE_SUBSCRIPTION = "REACT_SUBSCRIPTION";
	const SUBSCRIPTION_REFUND = "SUBSCRIPTION_REFUND";

	const TYPE_BINARY_COMMISSION = "BC";
	const TYPE_UNILEVEL_COMMISSION = "UC";
	const TYPE_LEADERSHIP_COMMISSION = "LC";
	const TYPE_TSB_COMMISSION = "TSB";
    //
    const TRANSACTION_FEE = 1;
    //
    const WITHDRAW_METHOD_PAYAP = "PAYAP";
    // const NOTE_SUBSCRIPTION_REFUND = "Monthly subscription refunded";

    // const E_WALLET_PAYAP = 'payap';
    // const E_WALLET_IPAYOUR = 'iPayout';

    public static function addNewWithdraw($userId, $amount, $openingBalance, $payapMobile, $remarks) {
        // withdraw
        $r = new EwalletTransaction();
        $r->user_id = $userId;
        $r->created_at = Util::getCurrentDateTime();
        $r->opening_balance = $openingBalance;
        $r->amount = $amount;
        $r->closing_balance = $openingBalance - $amount;
        $r->type = self::TYPE_WITHDRAW;
        $r->payap_mobile = $payapMobile;
        $r->remarks = $remarks;
        $r->csv_generated = 2; // now we are using API directly to transfer. No need to write to csv
        $r->withdraw_method = self::WITHDRAW_METHOD_PAYAP;
        $r->save();
        // transaction fee
        $t = new EwalletTransaction();
        $t->user_id = $userId;
        $t->created_at = Util::getCurrentDateTime();
        $t->opening_balance = $r->closing_balance;
        $t->amount = self::TRANSACTION_FEE;
        $t->closing_balance = $r->closing_balance - self::TRANSACTION_FEE;
        $t->type = self::TYPE_TRANSACTION_FEE;
        $t->csv_generated = 2;
        $t->save();
        // set new estimated_balance
        User::where('id', $userId)
                ->update([
                    'estimated_balance' => $t->closing_balance
        ]);
    }

    public static function recsToTransfer() {
        return DB::table('v_ewallet_transactions')
                        ->select('et_id', 'payap_mobile', 'amount')
                        ->where('type', self::TYPE_WITHDRAW)
                        ->where('csv_generated', 0)
                        ->get();
    }

    public static function markAsTransfered($csvId, $recs) {
        foreach ($recs as $rec) {
            DB::table('ewallet_transactions')
                    ->where('id', $rec->et_id)
                    ->update([
                        'csv_generated' => 1,
                        'csv_id' => $csvId
            ]);
        }
    }

    public static function getLatestTen($userId) {
        return DB::table('ewallet_transactions')
                        ->where('user_id', $userId)
                        ->orderBy('id', 'desc')
                        ->limit(10)
                        ->get();
    }

    public static function addPurchase($userId, $type, $amount, $purchaseId = 0, $note = null) {
        $openingBalance = User::find($userId)->estimated_balance;
        $closingBalance = $openingBalance + $amount;

        $ew = new EwalletTransaction();
        $ew->user_id = $userId;
        $ew->opening_balance = $openingBalance;
        $ew->closing_balance = $closingBalance;
        $ew->amount = abs($amount);
        $ew->type = $type;
        $ew->remarks = $note;
        $ew->created_at = Util::getCurrentDateTime();
        $ew->purchase_id = $purchaseId;
        $ew->save();

        $user = User::find($userId);
        $user->estimated_balance = $closingBalance;
        $user->save();
        return $ew->id;

    }

    public static function refundCouponCode($codeId) {
        $couponCode = DB::table('discount_coupon')
                ->where('id', $codeId)
                ->first();

        $amount = $couponCode->discount_amount;

        self::addPurchase($couponCode->generated_for, 'COUP_CODE_REFUND', $amount);
    }

    public static function getThisWeekCommission($userId){
        Carbon::today()->toDateString();
        $now = Carbon::now();
        $start = $now->startOfWeek()->format('Y-m-d');
        $end = $now->endOfWeek()->format('Y-m-d');
        // Log::info("Start of week ".$start." end of week ".$end);
        return self::runCommissionQuery($userId, $start, $end);
    }

    public static function getThisMonthCommission($userId){
        Carbon::today()->toDateString();
        $now = Carbon::now();
        $start = $now->startOfMonth()->format('Y-m-d');
        $end = $now->endOfMonth()->format('Y-m-d');
        // Log::info("Start of month ".$start." end of week ".$end);
        return self::runCommissionQuery($userId, $start, $end);
    }

    public static function getThisYearCommission($userId){
        Carbon::today()->toDateString();
        $now = Carbon::now();
        $start = $now->startOfYear()->format('Y-m-d');
        $end = $now->endOfYear()->format('Y-m-d');
        // Log::info("Start of year ".$start." end of week ".$end);
        return self::runCommissionQuery($userId, $start, $end);
    }

    private static function runCommissionQuery($userId, $start, $end){
        $query = DB::table('ewallet_transactions')
        ->select(DB::raw("sum(amount) as total"))
        ->where('user_id', $userId)
        ->whereBetween('created_at', [$start, $end])
        ->where('type', 'DEPOSIT')->first();
        if($query->total==null){
            $query->total = number_format(0, 2, '.', '');
        }
        return number_format($query->total, 2, '.', ',');
    }

    public static function getUserBalance($userId){
        $query = DB::table('users')
        ->select("estimated_balance as balance")
        ->where('id', $userId)->first();
        if($query->balance==null){
            $query->balance = number_format(0, 2, '.', '');
        }
        return number_format($query->balance, 2, '.', ',');
    }

}

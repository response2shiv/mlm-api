<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DB;

class Commission extends Model {

    protected $table = 'commission';

    const SESSION_KEY_FROM_DATE = "com_eng_from";
    const SESSION_KEY_TO_DATE = "com_eng_to";
    //
    const SESSION_KEY_SEARCH_TRANSACTION_DATE = "q_transaction_date";
    const SESSION_KEY_SEARCH_APPROVED_DATE = "q_approved_date";

    public static function getComEngFromDate() {
        if (!\utill::isNullOrEmpty(self::SESSION_KEY_FROM_DATE))
            return session(self::SESSION_KEY_FROM_DATE);
        else
            return "";
    }

    public static function getComEngToDate() {
        if (!\utill::isNullOrEmpty(self::SESSION_KEY_TO_DATE))
            return session(self::SESSION_KEY_TO_DATE);
        else
            return "";
    }

    public static function setComEngFromDate($fromDate) {
        session([self::SESSION_KEY_FROM_DATE => $fromDate]);
    }

    public static function setComEngToDate($toDate) {
        session([self::SESSION_KEY_TO_DATE => $toDate]);
    }

    //
    public static function getSearchTranDate() {
        if (!\utill::isNullOrEmpty(self::SESSION_KEY_SEARCH_TRANSACTION_DATE))
            return session(self::SESSION_KEY_SEARCH_TRANSACTION_DATE);
        else
            return "";
    }

    public static function getSearchApprovedDate() {
        if (!\utill::isNullOrEmpty(self::SESSION_KEY_SEARCH_APPROVED_DATE))
            return session(self::SESSION_KEY_SEARCH_APPROVED_DATE);
        else
            return "";
    }

    public static function setSearchTranDate($fromDate) {
        session([self::SESSION_KEY_SEARCH_TRANSACTION_DATE => $fromDate]);
    }

    public static function setSearchApprovedDate($toDate) {
        session([self::SESSION_KEY_SEARCH_APPROVED_DATE => $toDate]);
    }

    public static function getCurrentMonthCommission($userId) {
        $rec = DB::select("SELECT * from get_commission_for_widget($userId)");
        if (count($rec) > 0) {
            return number_format($rec[0]->current_month_total, 2);
        } else {
            return number_format(0);
        }
    }

    public static function getLastMonthCommission($userId) {
        $rec = DB::select("SELECT * from get_commission_for_widget($userId)");
        if (count($rec) > 0) {
            return number_format($rec[0]->last_month_total, 2);
        } else {
            return number_format(0);
        }
    }

    public static function getApprovedDates($q = null) {
        $query = DB::table('commission');
        $query->select(DB::raw("DATE(processed_date) as id, DATE(processed_date) as text, processed_date"));
        if ($q != null) {
            $query->where('processed_date', 'ilike', $q . "%");
        }
        $query->groupBy("processed_date");
        $query->orderBy('processed_date', 'desc');
        return $query->paginate(10);
    }

}

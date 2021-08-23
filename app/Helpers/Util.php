<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class Util {
    const USER_TIME_ZONE = 'America/Chicago';
    const UTC_TIME_ZONE = 'UTC';

    public static function isNullOrEmpty($string) {
        return (!isset($string) || trim($string) === '');
    }

    public static function getRandomString($lenth, $chars = null) {
        if ($chars == null) {
            $chars = '123456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        }
        $chars = str_split($chars);
        $count = count($chars) - 1;
        $out = "";
        for ($c = 0; $c < (int) $lenth; $c++) {
            $out .= $chars[mt_rand(0, $count)];
        }
        return $out;
    }

    /**
     * @return Carbon
     */
    public static function getFirstDayCurrentMonth()
    {
        return Carbon::now(self::USER_TIME_ZONE)->startOfMonth();
    }

    /**
     * @return Carbon
     */
    public static function getUserCurrentDate()
    {
        return Carbon::now(self::USER_TIME_ZONE);
    }

    public static function getCurrentDate() {
        return date('Y-m-d');
    }

    public static function getCurrentTime() {
        return date('H:i:s');
    }

    public static function getCurrentDateTime() {
        return date('Y-m-d H:i:s');
    }

    public static function getCurrentDateSeptember() {
        return '2020-09-30';
    }

    public static function getCurrentTimeSeptember() {
        return '00:00:00';
    }

    public static function getCurrentDateTimeSeptember() {
        return '2020-09-30 00:00:00';
    }

    // 2/19/15 ==> 2015-02-19
    public static function getFormatedDate($date) {
        $temp = explode("/", $date);
        return "20" . $temp[2] . "-" . str_pad($temp[0], 2, "0", STR_PAD_LEFT) . "-" . str_pad($temp[1], 2, "0", STR_PAD_LEFT);
    }

    public static function getTimeDiff($timestamp) {
        if ($timestamp == 0 || self::isNullOrEmpty($timestamp))
            return "";
        $generated = \Carbon\Carbon::createFromTimestamp($timestamp);
        $now = \Carbon\Carbon::createFromTimestamp(time());
        return $generated->diffForHumans($now);
    }

    public static function getSetting($param)
    {
        $result = DB::table('settings')->select('value')->where('param', $param)->first();

        if (is_object($result)){
            return $result->value ?: false;
        }
        return false;
    }

    public static function getNumberOfWeek(Carbon $date)
    {
        return $date->weekOfYear;
    }

    public static function getStartDateOfWeek(Carbon $date)
    {
        return $date->startOfWeek();
    }

    public static function getEndDateOfWeek(Carbon $date)
    {
        return $date->endOfWeek();
    }

}

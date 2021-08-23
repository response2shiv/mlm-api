<?php

namespace App\Models;

use DB;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{

    public $timestamps = false;

    public static function getAll()
    {
        return DB::table('country')
            ->select('country', 'countrycode', 'id')
            ->orderBy('country', 'asc')
            ->get();
    }

    public static function getCountryByCode($countryCode)
    {
        return DB::table('country')
            ->where('countrycode', $countryCode)->first();
    }

    public static function isTier3($countryCode)
    {

        $country = self::getCountryByCode($countryCode);
        if (!$country) {
            return false;
        }

        if ($country->is_tier3) {
            return true;
        } else {
            return false;
        }
    }

    public static function addPaymentTypeCountry($country_id, $payment_type)
    {
        if ($payment_type == 'NMI - T1') {
            $payment_type = 'NMI - T1';
        } else {
            $payment_type = 'Trust my travel';
        }
        DB::table('payment_type_country')->insert(
            [
                'payment_type' => $payment_type,
                'country_id' => $country_id
            ]
        );
    }

    public static function deletePaymentTypeCountry($paymentTypeCountryId){
        DB::table('payment_type_country')
            ->where('id', '=', $paymentTypeCountryId)
            ->delete()
        ;
    }


}

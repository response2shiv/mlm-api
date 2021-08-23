<?php

namespace App\Models;

use DB;
use Illuminate\Database\Eloquent\Model;

class PayOutControl extends Model
{
    protected $table = 'payout_type_country';
    public $fillable = ['country_id', 'type'];

    public static function getPayoutTypeByCountryCode($countryCode)
    {
        $country = Country::getCountryByCode($countryCode);
        $payoutType = null;
        if (!empty($country)) {
            $payoutType = self::getPayuotTypeByCountryId($country);
        }
        return $payoutType;
    }

    public static function getPayuotTypeByCountryId($country)
    {
        return self::select('*')->where('country_id', $country->id)->first();
    }
}

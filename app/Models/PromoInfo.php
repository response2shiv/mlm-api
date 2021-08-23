<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DB;

class PromoInfo extends Model {

    protected $table = "promo_info";
    public $timestamps = false;

    public static function getPromoSummary() {
        return DB::table('promo_info')
                        ->select('top_banner_img', 'top_banner_url', 'top_banner_is_active', 'side_banner_img', 'side_banner_title', 'side_banner_short_desc', 'side_banner_is_active')
                        ->first();
    }

    public static function getPromoDetail() {
        return DB::table('promo_info')
                        ->select('side_banner_title', 'side_banner_long_desc', 'side_banner_is_active')
                        ->first();
    }

    public static function getPromoAll($id = 1) {
        return DB::table('promo_info')
            ->select('*')
            ->where('id',$id)
            ->first();
    }
}

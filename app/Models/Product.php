<?php

namespace App\Models;

use DB;
use Auth;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Product extends Model {

    protected $table = "products";
    public $timestamps = false;

    // enrollment packages
    const ID_NCREASE_NSBO = 1;
    const ID_VISIONARY_PACK = 3;
    const ID_BASIC_PACK = 2;
    const ID_FX_PACK = 10;
    // upgrade packages
    const UPG_ISBO_TO_FX = 11;
    const UPG_ISBO_TO_BASIC = 15;
    const UPG_ISBO_TO_VISIONARY = 12;
    const UPG_FX_TO_VISIONARY = 13;
    const UPG_BASIC_TO_VISIONARY =  14;
    
    // const ID_UPG_FIRST_PREMIUM = 20;
    //buy ibumerang
    const ENROLLMENT = 1;
    const UPGRADES = 2;
    const MEMBERSHIP = 3;
    //membership
    const MONTHLY_MEMBERSHIP = 11;
    const MONTHLY_MEMBERSHIP_STAND_BY_USER = 33;
    const TEIR3_COACHSUBSCRIPTION = 26;
    const ID_MONTHLY_MEMBERSHIP = 12;
    const ID_UPG_STANDBY_TO_PREMIUM_FC = 17;
    const ID_UPG_BUSINESS_TO_PREMIUM_FC = 19;
    const ID_UPG_COACH_TO_PREMIUM_FC = 18;
    const ID_MEMBERSHIP = 11;
    const ID_TIER3_COACH = 26;
    const ID_PRE_PAID_CODE = 25;
    const ID_FIRST_TO_PREMIUM = 20;
    const ID_REACTIVATION_PRODUCT = 50;
    const ID_VIBE_IMPORT_USER = 51;
    const ID_VIBE_OVERDRIVE_USER = 52;
    //donations
    const ID_TICKET = 38;
    const ID_FOUNDATION = 39;
    const TICKET_PURCHASE_DISCOUNT_PRICE = '49.98';
    const ID_TRAVEL_SAVING_BONUS = 41;

    //Events tickets
    const ID_EVENTS_TICKET_DREAM_WEEKEND = [46, 47, 48];
    const ID_EVENTS_TICKET_XCCELERATE = [49];

    public function photos()
    {
        return $this->hasMany('App\Models\ProductImage');
    }

    public static function getSubscriptionProducts()
    {
        return DB::table('products')
            ->select('*')
            ->whereIn('id',[
                \App\Product::MONTHLY_MEMBERSHIP,
                \App\Product::ID_MONTHLY_MEMBERSHIP,
                \App\Product::MONTHLY_MEMBERSHIP_STAND_BY_USER,
                \App\Product::TEIR3_COACHSUBSCRIPTION,
            ])
            ->orderBy('productname', 'asc')
            ->get();
    }

    public static function getAll() {
        return DB::table('products')
                        ->select('id', 'productname')
                        ->orderBy('producttype', 'asc')
                        ->orderBy('id', 'asc')
                        ->get();
    }

    public static function getProductsByCountryCode($data) {

        $user = Auth::user();

        $today = Carbon::today();
        $enrollemnt_date = Carbon::create($user->created_date);
        $diff_days = $enrollemnt_date->diff($today)->days;

        $sort = $data['sort'];

        $products_available = DB::table('products')
            ->join('product_countries', 'products.id', '=', 'product_countries.product_id')
            ->join('product_images', 'products.id', '=', 'product_images.product_id')
            ->join('country', 'product_countries.country_id', '=', 'country.id')
            ->join('product_enrollment_classes', 'product_enrollment_classes.product_id', '=', 'products.id')
            ->where('product_enrollment_classes.enrollment_product_id', $user->current_product_id)
            ->whereRaw(" (products.visible_days_from_enrollment >= ". $diff_days . " OR products.visible_days_from_enrollment = 0)")
            ->where('products.is_visible', 1)
            ->where('countrycode', $user->country_code)
            ->orderBy('products.price', $sort);

        return $products_available->get();
    }

    public static function getProductByCountryCodeById($country, $id) {
        return DB::table('products')
                ->join('product_countries', 'products.id', '=', 'product_countries.product_id')
                ->join('product_images', 'products.id', '=', 'product_images.product_id')
                ->join('country', 'product_countries.country_id', '=', 'country.id')
                ->where('countrycode', $country)
                ->where('products.id', $id)
                ->first();
    }

    public static function getByTypeId($typeId, $orderBy = "price", $ascDesc = "desc") {
        return DB::table('products')
                        ->where('producttype', $typeId)
                        ->where('is_enabled', 1)
                        ->orderBy($orderBy, $ascDesc)
                        ->get();
    }

    public static function getProduct($productId) {
        return DB::table('products')
                        ->where('id', $productId)
                        ->first();
    }

    public static function getProductsFromProductIdArray($productIdArray) {

        $query = DB::table('products')
            ->whereIn('id', $productIdArray)
            ->where('is_enabled', 1)
            ->orderBy('id', 'asc')
            ->get();

        return $query;
    }

    public function productTypes() {
        return $this->hasOne('App\ProductType', 'id', 'producttype');
    }

    public static function getProductName($productId) {
        if ($productId == 0)
            return "-";
        else if ($productId == self::ID_NCREASE_NSBO)
            return "Ncrease NSBO";
        else if ($productId == self::ID_VISIONARY_PACK)
            return "Visionary pack";
        else if ($productId == self::ID_BASIC_PACK)
            return "Basic Pack";
        
    }

    public static function getById($id) {
        return DB::table('products')
                        ->where('id', $id)
                        ->first();
    }

    public static function getByIdAndEnable($id) {
        return DB::table('products')
                        ->where('id', $id)
                        ->where('is_enabled', 1)
                        ->first();
    }

    public static function updateProduct($productId, $req) {
        $rec = Product::find($productId);
        $rec->productname = $req->productname;
        $rec->producttype = $req->producttype;
        $rec->is_enabled = $req->is_enabled;
        $rec->productdesc = $req->productdesc;
        $rec->price = $req->price;
        $rec->itemcode = $req->itemcode;
        $rec->bv = $req->bv;
        $rec->cv = $req->cv;
        $rec->qv = $req->qv;
        $rec->qc = $req->qc;
        $rec->ac = $req->ac;
        $rec->save();
    }

    public static function addProduct($req) {
        $maxID = DB::table('products')
                ->max('id');
        $rec = new Product;
        $rec->id = $maxID + 1;
        $rec->productname = $req->productname;
        $rec->producttype = $req->producttype;
        $rec->productdesc = $req->productdesc;
        $rec->price = $req->price;
        $rec->itemcode = $req->itemcode;
        $rec->bv = $req->bv;
        $rec->cv = $req->cv;
        $rec->qv = $req->qv;
        $rec->qc = $req->qc;
        $rec->ac = $req->ac;
        $rec->sku = $req->sku;
        
        if (isset($req->isautoship))
            $rec->isautoship = $req->isautoship;

        $rec->save();
        return $rec->id;
    }

    public static function getProductNameForInvoice($orderItem) {
        $product = DB::table('products')
                ->where('id', $orderItem->productid)
                ->first();

        $productName = $product->productname;

        if (!empty($orderItem->discount_voucher_id)) {
            $discountCode = DB::table('discount_coupon')
                    ->where('id', $orderItem->discount_voucher_id)
                    ->first();

            if ($discountCode) {
                $productName = $productName . ' (' . $discountCode->code . ')';
            }
        }

        return $productName;
    }

}

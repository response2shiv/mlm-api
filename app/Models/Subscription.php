<?php

namespace App\Models;

use App\Http\Controllers\SubscriptionAlertController;
use Auth;
use Carbon\Carbon;
use DB;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model {

    protected $table = 'subscription_history';
    protected $fillable = [
        'user_id',
        'subscription_product_id',
        'attempted_date',
        'attempt_count',
        'status',
        'response',
        'next_attempt_date'
    ];
    public $timestamps = false;

    public static function updateSubscription($userId, $req) {
        $user = User::find($userId);
        
        $userActivityLog = new UserActivityLog;
        $row = UserSettings::getByUserId(Auth::user()->id);
        $response = GeoIP::getInformationFromIP($row->current_ip);
        $userActivityLog->ip_address = $row->current_ip;
        $userActivityLog->user_id = Auth::user()->id;
        $userActivityLog->ip_details = $response;
        $userActivityLog->action = "UPDATE Subscription"; 
        $userActivityLog->old_data = "date = ".$user->next_subscription_date.", subscription_payment  = ".$user->subscription_payment_method_id.", gflag = ".$user->gflag;
        $userActivityLog->new_data = "date = ".$req['next_subscription_date'].", subscription_payment  = ".$req['subscription_payment_method_id'].", gflag = ".$req['gflag'];
        $userActivityLog->save();
        
        $user->next_subscription_date = $req['next_subscription_date'];
        $user->subscription_payment_method_id = $req['subscription_payment_method_id'];
        $user->gflag = $req['gflag'];
        $user->save();
    }

    public static function getCurrentSubscriptionPlan($userId, $convertObject=null) {
        
        $subscriptionProduct = self::getSubscriptionProduct($userId);
        
        if (!$subscriptionProduct) {
            return false;
        }

        $currentProductId = User::getCurrentProductId($userId);
        $product = Product::getById($currentProductId);
        
        if ($convertObject){
            return $product->productname .' ' . $convertObject["display_amount"] . '/month';
        }else{
             return $product->productname .' $' . $subscriptionProduct->price . '/month';
        }
        
        
    }

    public static function getSubscriptionProduct($userId) {
        $currentProductId = User::getCurrentProductId($userId);
        if (!$currentProductId) {
            return false;
        }
        $tvUser = User::getById($userId);

        if (!empty($tvUser->subscription_product)) {
            return Product::getById($tvUser->subscription_product);
        }

        if ($tvUser->is_tv_user == 1) {
//            $idecide = \App\IDecide::getIDecideUserId($userId);
            $idecide = DB::table('idecide_users')
                ->select('*')
                ->where('user_id', $userId)
                ->first();
            if (!empty($idecide) && $idecide->status == IDecide::ACTIVE) {
                $subscriptionProductId = Product::MONTHLY_MEMBERSHIP;
            } else {
                $tv_upg_products = [4];
                if (in_array($currentProductId, $tv_upg_products)) {
                    $subscriptionProductId = Product::MONTHLY_MEMBERSHIP;
                } else {
                    $subscriptionProductId = Product::ID_MONTHLY_MEMBERSHIP;
                }
            }
            return Product::getById($subscriptionProductId);
        } else if ($currentProductId == Product::ID_STANDBY_CLASS) {
            $subscriptionProductId = Product::MONTHLY_MEMBERSHIP_STAND_BY_USER;
            return Product::getById($subscriptionProductId);
        } else if ($currentProductId == Product::ID_COACH_CLASS) {
            $address = Address::getRec($userId, Address::TYPE_BILLING);
            $countryCode = (isset($address->countrycode) ? $address->countrycode : '');

            $country = Country::getCountryByCode($countryCode);
            if (!$country) {
                $subscriptionProductId = Product::MONTHLY_MEMBERSHIP;

                return Product::getById($subscriptionProductId);
                //return false;
            }

            if (Country::isTier3($countryCode)) {
                $subscriptionProductId = Product::TEIR3_COACHSUBSCRIPTION;
            } else {
                $subscriptionProductId = Product::MONTHLY_MEMBERSHIP;
            }

            return Product::getById($subscriptionProductId);
        } else {
            $subscriptionProductId = Product::MONTHLY_MEMBERSHIP;

            return Product::getById($subscriptionProductId);
        }
    }

    public static function runMonthlySubscriptionCron(Carbon $date = null)
    {
        if ( env('APP_ENV') === 'prod' || env('APP_ENV') === 'production' ) {
            $cron = new SubscriptionAlertController();
            return $cron->RunCronProcess($date);
        }
    }
}

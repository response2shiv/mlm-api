<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class SubscriptionHistory extends Model
{

    public static function UpdateSubscriptionHistoryOnly($user_id,
                                                         $attempt_date,
                                                         $attempt_count,
                                                         $status,
                                                         $products_id,
                                                         $payment_method_id,
                                                         $next_subscription_date,
                                                         $response = null, $isReactivate = 0)
    {
        // - Add subscription history.
        $subscription = new Subscription();
        $subscription->user_id = $user_id;
        $subscription->subscription_product_id = $products_id;
        $subscription->attempted_date = $attempt_date;
        $subscription->attempt_count = $attempt_count;
        $subscription->payment_method_id = $payment_method_id;
        $subscription->response = $response;
        $subscription->next_attempt_date = $next_subscription_date;
        $subscription->status = $status;
        $subscription->is_reactivate = $isReactivate;
        $subscription->save();
    }

    public static function getNextSubscriptionDate()
    {
        $currentDate = date('Y-m-d');
        $date = strtotime(date("Y-m-d", strtotime($currentDate)) . " +1 month");
        $date = date('Y-m-d', $date);

        $parts = explode('-', $date);

        if (end($parts) > 25) {
            $parts[2] = 25;
        }

        $nextSubscriptionDate = implode('-', $parts);
        return $nextSubscriptionDate;
    }
    public static function getNextSubscriptionDateSeptember()
    {
        $currentDate = Auth::user()->original_subscription_date;
        $date = strtotime(date("Y-m-d", strtotime($currentDate)) . " +1 month");
        $date = date('Y-m-d', $date);

        $parts = explode('-', $date);

        if (end($parts) > 25) {
            $parts[2] = 25;
        }

        $nextSubscriptionDate = implode('-', $parts);
        return $nextSubscriptionDate;
    }
}

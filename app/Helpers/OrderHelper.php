<?php

namespace App\Helpers;

use App\Models\User;
use App\Models\Order;
use App\Models\Helper;
use App\Models\Product;
use App\Models\OrderItem;
use App\Models\SubscriptionHistory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class OrderHelper
{
    
    public static function updateOrderForUpgrade($orderId, $product)
    {
        $user = Auth::user();

        # When unicrypt call our side, we dont' have the user on session
        if (is_null($user)) {
            $order = Order::find($orderId);
            $user = User::find($order->userid);
        }

        User::setCurrentProductId($user->id, $product->product->id);

        Helper::afterPaymentSuccess($product->product->id,  null, $user->id);
       
        if (!empty($user) && isset($user->currentProductId) && $user->currentProductId == Product::ID_STANDBY_CLASS) {
            $cOrder = Order::getById($orderId);
            $oCreatedDate = date('d', strtotime($cOrder->created_date));

            if ($oCreatedDate >= 25) {
                $sDate = strtotime(date("Y-m-25", strtotime($cOrder->created_date)) . " +1 month");
                $sDate = date("Y-m-d", $sDate);
            } else {
                $sDate = strtotime(date("Y-m-d", strtotime($cOrder->created_date)) . " +1 month");
                $sDate = date("Y-m-d", $sDate);
            }
            User::where('id', $user->id)->update(['next_subscription_date' => $sDate, 'original_subscription_date' => $sDate]);
        }
    }

    public static function updateOrderForDonation($orderId, $product)
    {
    }

    public static function updateOrderForTicket($orderId, $product)
    {
    }

    public static function updateOrderForMembership($orderId, $ShopCartProduct)
    {
        $user = Auth::user();

        # When unicrypt call our side, we dont' have the user on session
        if (is_null($user)) {
            $order = Order::find($orderId);
            $user = User::find($order->userid);
        }

        $bv = $ShopCartProduct->product->bv * $ShopCartProduct->quantity;
        $qv = $ShopCartProduct->product->qv * $ShopCartProduct->quantity;
        $cv = $ShopCartProduct->product->cv * $ShopCartProduct->quantity;        

        Helper::afterPaymentSuccess($ShopCartProduct->product->id,  null, $user->id);

        User::updateUserSitesStatus($user->id, 0, 0, 0);

        $attemptDate = date('Y-m-d');
        $attemptCount = 1;
        $status = '1';
        $productId = $ShopCartProduct->product->id;

        $septemberSubscriptionIds = array(80,81,82,83);
        if (!in_array($productId, $septemberSubscriptionIds) ){
            $nextSubscriptionDate = SubscriptionHistory::getNextSubscriptionDate();
        }else{
            $nextSubscriptionDate = SubscriptionHistory::getNextSubscriptionDateSeptember();
        }
                
        $order = Order::find($orderId);

        SubscriptionHistory::UpdateSubscriptionHistoryOnly(
            $user->id,
            $attemptDate,
            $attemptCount,
            $status,
            $productId,
            $order->payment_method_id,
            $nextSubscriptionDate,
            'Reactivate subscription',
            1
        );

        User::updateNextSubscriptionDate($user->id, $nextSubscriptionDate);

        Log::info("Create order for membership: ".$ShopCartProduct->product->producttype);
    }
}

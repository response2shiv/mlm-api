<?php

namespace App\Http\Controllers;

use Log;
use Validator;
use App\Models\User;
use App\Models\Order;
use App\Models\PreOrder;
use App\Models\Unicrypt;
use App\Models\Ipaytotal;
use App\Models\OrderItem;
use Illuminate\Http\Request;

use App\Models\OrderConversion;
use App\Services\PreOrderService;
use App\Models\MerchantTransactionTracker;

class MerchantTransactionController extends Controller
{
    //
    public function createTransaction(request $request)
    {
        $data = $request->all();

        $sesData = $this->validateTransactionData($request);
        if ($sesData['error'] == 1) {
            return response()->json($sesData);
        }

        $merchtracker = new MerchantTransactionTracker();
        $merchtracker->merchant_id = $request->merchant_id;
        $merchtracker->transaction_id = $request->transaction_id;
        $merchtracker->status = $request->status;
        $merchtracker->save();

        return response()->json(['error' => 0, 'data' => $merchtracker]);
    }

    public function validateTransactionData($request)
    {
        $validator = Validator::make($request->all(), [
            'merchant_id' => 'required|numeric|exists:merchants,id',
            'pre_order_id' => 'required|numeric|exists:pre_orders,id',
            // 'transaction_id' => 'required',
            'status' => 'required|max:20',
        ]);

        $msg = "";
        if ($validator->fails()) {
            $valid = 0;
            $messages = $validator->messages();
            foreach ($messages->all() as $m) {
                $msg = $m;
            }
            //Validation failed
            return [
                'error' => 1,
                'msg' => $msg
            ];
        }

        //Validation was successfull
        return [
            'error' => 0,
            'msg' => 'Validation successfull.'
        ];
    }

    public function checkMerchantTransactionStatus($transaction_id, $cron = 0)
    {
        $tracker = MerchantTransactionTracker::where('transaction_id', $transaction_id)->first();

        if (!$tracker) {
            return response()->json(['error' => 1, 'msg' => 'Transaction id not found']);
        }

        switch ($tracker->merchant_id) {
            case MerchantTransactionTracker::MERCHANT_UNICRYPT:
                $status = '';
                $merch_response = $this->checkUnicryptStatus($tracker);

                if ($merch_response->original['error'] == '0') {
                    $status = $merch_response->original['data']['status'];

                    if ($status == "PAID") {
                        var_dump('[OK] - Hash: '.$merch_response->original['data']['order_hash']. " - Status: ".$status);

                        # If payment is success, import the pre order to order and go ahead with the process of each product
                        $service = new PreOrderService;
                        $migrated = $service->migratePreOrderToOrder($tracker->transaction_id);

                        # If flow is completed, check if need change user's status
                        if ($migrated) {
                            var_dump('ORDER MIGRATED!');
                            User::activateUnicryptAccount($tracker->preOrder->user->id);
                        }
                    }
                }

                # If can get the status from unicrypt, then update our track.
                if (!empty($status)) {
                    var_dump('Update track: '.$transaction_id. " to status: ".$status);
                    $this->updateMerchantStatus($tracker, $status, $cron);
                }
            break;

            case MerchantTransactionTracker::MERCHANT_IPAYTOTAL:
                $merch_response = $this->checkIpaytotalStatus($tracker, $tracker->pre_order_id);

                if ($merch_response->original['data']['transaction']['transaction_status'] == "success") {
                    $this->processOrder($tracker);
                }
            break;
        }

        return response()->json([
            'error' => 0,
            'msg' => 'Order has been updated',
            'data' => $tracker
        ]);
    }

    private function processOrder($track)
    {
        # Copy the pre order into to the order
        $order = $this->migratePreOrderToOrder($track->transaction_id);

        if ($order) {
            //Set the user status to APPROVED
            User::activateUnicryptAccount($order->userid);
        }

        return true;
    }

    public function migratePreOrderToOrder($transaction_id)
    {
        $preOrder = PreOrder::where('orderhash', $transaction_id)->first();

        try {
            if ($preOrder) {
                $order = Order::where('trasnactionid', $preOrder->orderhash)->first();
                if (!$order) {
                    $order = $this->createOrder($preOrder);
                }
            }
        } catch (Exception $e) {
            Log::info("Failed to create Order and Order items");
        }

        return $order;
    }

    public function createOrder($preOrder)
    {
        $order = Order::create([
            'userid' =>$preOrder->userid,
            'statuscode' =>$preOrder->statuscode,
            'ordersubtotal' =>$preOrder->ordersubtotal,
            'ordertax' =>$preOrder->ordertax,
            'ordertotal' =>$preOrder->ordertotal,
            'orderbv' =>$preOrder->orderbv,
            'orderqv' =>$preOrder->orderqv,
            'ordercv' =>$preOrder->ordercv,
            'trasnactionid' =>$preOrder->orderhash,
            'updated_at' =>$preOrder->updated_at,
            'created_at' =>$preOrder->created_at,
            'payment_methods_id' =>$preOrder->payment_methods_id,
            'shipping_address_id' =>$preOrder->shipping_address_id,
            'inv_id' =>$preOrder->inv_id,
            'created_date' =>$preOrder->created_date,
            'created_time' =>$preOrder->created_time,
            'processed' =>$preOrder->processed,
            'coupon_code'  =>$preOrder->coupon_code,
            'order_refund_ref' =>$preOrder->order_refund_ref,
            'created_dt' =>$preOrder->created_dt,
            'orderqc' =>$preOrder->orderqc,
            'orderac' =>$preOrder->orderac
        ]);

        $orderConversion = OrderConversion::where('pre_order_id', $preOrder->id)->first();
        if ($orderConversion) {
            $orderConversion->order_id = $order->id;
            $orderConversion->save();
        }

        foreach ($preOrder->preOrderItems as $preOrderItem) {
            $OrderItem = OrderItem::create([
                'orderid' =>$order->id,
                'productid'=> $preOrderItem->productid,
                'quantity'=> $preOrderItem->quantity,
                'itemprice'=> $preOrderItem->itemprice,
                'bv' =>$preOrderItem->bv,
                'qv' =>$preOrderItem->qv,
                'cv' =>$preOrderItem->cv,
                'created_at' =>$preOrderItem->created_at,
                'updated_at'=>$preOrderItem->updated_at,
                'created_date'=> $preOrderItem->created_date,
                'created_time'=> $preOrderItem->created_time,
                'discount_coupon'=> $preOrderItem->discount_coupon,
                'created_dt' =>$preOrderItem->created_dt,
                'qc'=> $preOrderItem->qc,
                'ac'=> $preOrderItem->ac,
                'will_be_attend' =>$preOrderItem->will_be_attend
            ]);
        }

        return $order;
    }

    private function checkUnicryptStatus($tracker)
    {
        $resp = Unicrypt::getOrderStatus($tracker->transaction_id);

        if (!is_null($resp)) {
            if ($resp['status'] == 'PAID') {
                # Copy the pre order into to the order
                $order = $this->migratePreOrderToOrder($tracker->transaction_id);

                if ($order) {
                    User::activateUnicryptAccount($order->userid);
                }
            }

            return response()->json(['error' => 0, 'data' => $resp]);
        } else {
            return response()->json(['error' => 1, 'data' => null]);
        }
    }

    private function checkIpaytotalStatus($tracker)
    {
        $resp = Ipaytotal::getOrderStatus($tracker);

        if ($resp['status'] == 'PAID') {

            # Copy the pre order into to the order
            $order = $this->migratePreOrderToOrder($tracker->transaction_id);

            if ($order) {
                User::activateUnicryptAccount($order->userid);
            }
        }
        return response()->json(['error' => 0, 'data' => $resp]);
    }

    public function validateTransactionStatusData($request)
    {
        $validator = Validator::make($request->all(), [
            'transaction_id' => 'required|exists:merchant_transaction_tracker,transaction_id'
        ]);

        $msg = "";
        if ($validator->fails()) {
            $valid = 0;
            $messages = $validator->messages();
            foreach ($messages->all() as $m) {
                $msg = $m;
            }
            //Validation failed
            return [
                'error' => 1,
                'msg' => $msg
            ];
        }

        //Validation was successfull
        return [
            'error' => 0,
            'msg' => 'Validation successfull.'
        ];
    }

    public function updateMerchantStatus($track, $status, $is_cron = 0)
    {
        if (!$track) {
            return false;
        }

        # Update Merchant Tracker
        $track->status = $status;
        $track->cron_processed = $is_cron;
        $track->save();

        return true;
    }

}

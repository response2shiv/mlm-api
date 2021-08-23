<?php

namespace App\Http\Controllers;

use App\Helpers\OrderHelper;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PreOrder;
use App\Models\ProductType;
use App\Models\Unicrypt;
use App\Models\User;
use App\Models\OrderConversion;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Log;
use Storage;

class UnicryptController extends Controller
{
    public function createInvoice($user, $preOrder)
    {
        $user = User::where('id', 15163)->first();
        $resp = Unicrypt::create($user, $preOrder->total, 'Boomerang Pack', 'Boomerang Purchase', 'https://myibuumerang.com/pay/process/');

        return response()->json(['error' => 0, 'data' => $resp]);
    }

    public function checkStatus(Request $request)
    {
        $orderhash = $request->get('orderhash', request()->orderhash);

        $resp = Unicrypt::getOrderStatus($orderhash);

        if ($resp['status'] == 'PAID') {

            # Copy the pre order into to the order
            $order = $this->migratePreOrderToOrder($orderhash);

            if ($order) {
                User::activateUnicryptAccount($order->userid);
            }

            # Continue the ncreas flow by product type
            $this->doFlowByProductType($order);
        }

        $this->saveUnicryptLog($request);

        return response()->json(['error' => 0, 'data' => $resp]);
    }

    public function saveUnicryptLog($request)
    {
        Log::info("checkStatus Request received ", $request->all());

        try {
            $str_json = json_encode($request->all());
            Storage::disk('local')->append('unicrypt-log.txt', Carbon::now() . ' - received -> ' . $str_json);
        } catch (Exception $e) {
            Log::info("Failed to save the log file on UniCrypt Check Status");
        }
    }

    public function createOrder($preOrder)
    {
        $order = Order::create([
            'userid' => $preOrder->userid,
            'statuscode' => $preOrder->statuscode,
            'ordersubtotal' => $preOrder->ordersubtotal,
            'ordertax' => $preOrder->ordertax,
            'ordertotal' => $preOrder->ordertotal,
            'orderbv' => $preOrder->orderbv,
            'orderqv' => $preOrder->orderqv,
            'ordercv' => $preOrder->ordercv,
            'trasnactionid' => $preOrder->orderhash,
            'updated_at' => $preOrder->updated_at,
            'created_at' => $preOrder->created_at,
            'payment_methods_id' => $preOrder->payment_methods_id,
            'shipping_address_id' => $preOrder->shipping_address_id,
            'inv_id' => $preOrder->inv_id,
            'created_date' => $preOrder->created_date,
            'created_time' => $preOrder->created_time,
            'processed' => $preOrder->processed,
            'coupon_code'  => $preOrder->coupon_code,
            'order_refund_ref' => $preOrder->order_refund_ref,
            'created_dt' => $preOrder->created_dt,
            'orderqc' => $preOrder->orderqc,
            'orderac' => $preOrder->orderac
        ]);

        $orderConversion = OrderConversion::where('pre_order_id', $preOrder->id)->first();
        if ($orderConversion) {
            $orderConversion->order_id = $order->id;
            $orderConversion->save();
        }

        foreach ($preOrder->preOrderItems as $preOrderItem) {
            $OrderItem = OrderItem::create([
                'orderid' => $order->id,
                'productid' => $preOrderItem->productid,
                'quantity' => $preOrderItem->quantity,
                'itemprice' => $preOrderItem->itemprice,
                'bv' => $preOrderItem->bv,
                'qv' => $preOrderItem->qv,
                'cv' => $preOrderItem->cv,
                'created_at' => $preOrderItem->created_at,
                'updated_at' => $preOrderItem->updated_at,
                'created_date' => $preOrderItem->created_date,
                'created_time' => $preOrderItem->created_time,
                'discount_coupon' => $preOrderItem->discount_coupon,
                'created_dt' => $preOrderItem->created_dt,
                'qc' => $preOrderItem->qc,
                'ac' => $preOrderItem->ac,
                'will_be_attend' => $preOrderItem->will_be_attend
            ]);
        }

        return $order;
    }

    public function joinCreateInvoice(Request $request)
    {
        $user = User::find($request->userId); //User account
        $resp = Unicrypt::create($user, $request->orderTotal, 'Ibuumerang Enrollment', 'Ibuumerang Enrollment', $request->callback_url);

        $preOrder = PreOrder::where('userid', $request->userId)->orderBy('id', 'DESC')->first();
        $preOrder->orderhash = $resp['orderhash'];
        $preOrder->save();

        return response()->json(['error' => 0, 'data' => $resp]);
    }

    public function migratePreOrderToOrder($orderhash)
    {
        $preOrder = PreOrder::where('orderhash', $orderhash)->first();

        try {
            $order = Order::where('trasnactionid', $preOrder->orderhash)->first();

            if (!$order) {
                $order = $this->createOrder($preOrder);
            }
        } catch (Exception $e) {
            Log::info("Failed to create Order and Order items");
        }

        return $order;
    }

    public function doFlowByProductType($order)
    {
        $items = $order->orderItems;

        if (!$items->isEmpty()) {
            foreach ($items as $item) {
                switch ($item->product->producttype) {
                    case ProductType::TYPE_BOOMERANG:
                        OrderHelper::updateOrderForBuumerang($order->id, $item);
                        break;
                    case ProductType::TYPE_DONATION:
                        OrderHelper::updateOrderForDonation($order->id, $item);
                        break;
                    case ProductType::TYPE_TICKET:
                        OrderHelper::updateOrderForTicket($order->id, $item);
                        break;
                    case ProductType::TYPE_UPGRADE:
                        OrderHelper::updateOrderForUpgrade($order->id, $item);
                        break;
                    case ProductType::TYPE_MEMBERSHIP:
                        OrderHelper::updateOrderForMembership($order->id, $item);
                }
            }
        }
    }

    public function changeStatus(Request $request)
    {
        $orderhash = $request->get('orderhash', request()->orderhash);

        $resp = Unicrypt::getOrderStatus($orderhash);

        if ($request->get('status') == 'paid') {
            # Copy the pre order into to the order
            $order = $this->migratePreOrderToOrder($orderhash);

            if ($order) {
                User::activateUnicryptAccount($order->userid);
            }

            # Continue the ibuumerang flow by product type
            $this->doFlowByProductType($order);

            $this->saveUnicryptLog($request);

            return response()->json(['error' => 0, 'data' => $resp, 'message' => 'success']);
        } else {
            return response()->json(['error' => 1, 'data' => [], 'message' => 'failed']);
        }
    }
}

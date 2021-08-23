<?php

namespace App\Http\Controllers\Affiliates;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Models\WorldSeriesErrors;
use App\Models\WorldSeriesEvents;
use App\Models\WorldSeriesOverviews;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Log;
use Validator;

class WorldSeriesErrorsController extends Controller
{
    public function create(Request $request) 
    {
        $orderId = $request->get('order_id');

        # Get all orders inside on the world series, that has refund
        $refunds = $this->getRefundOrders($orderId); 

        $errors = ""; 

        foreach ($refunds as $refund) {
            # Add or update an error on table
            $errors = WorldSeriesErrors::updateOrCreate(
                [ 'order_id' => $refund->order_id ],
                (array) $refund
            );

            # Update all overviews
            $this->updateOverview($refund->order_id, $refund->event_type);
        }

        return $errors;
    }

    public function getRefundOrders($orderId = "") 
    {
        # To admin enviroment
        $whereOrderId = (!empty($orderId)) ? ' AND orders.id = '.$orderId : '';

        $sql = DB::select("

            SELECT DISTINCT orders.id AS order_id
                 , orders.order_refund_ref AS order_refund_id
                 , CASE WHEN wse.event_type = 'enrollment' THEN true ELSE false END is_enrollment
                 , CASE WHEN wse.event_type = 'upgrade'    THEN true ELSE false END is_upgrade 
                 , wse.event_type
 
              FROM orders 

        INNER JOIN world_series_events wse
                ON wse.order_id = orders.id 

             WHERE orders.statuscode = 10 
                   $whereOrderId ");
    
         return $sql;
    }

    public function updateOverview($orderId, $eventType) 
    {
        $order = WorldSeriesEvents::whereOrderId($orderId)->first();

        # if enrollment, update all overviews by sponsor and sponsor's sponsor
        if ($eventType == 'enrollment') {
            # Get the Sponsor of the user's sponsor.
            $masterSponsor = User::whereDistid($order->sponsor->sponsor_id)->first();
            $users = [$order->sponsor_id, $masterSponsor->id];
        } elseif ($eventType == 'upgrade') {
            $users = [$order->user_id, $order->sponsor_id];
        }

        # Get overviews
        $overviews = WorldSeriesOverviews::whereIn('sponsor_id', $users)->get();

        # Increments errors to users by the event type and recalculate total
        foreach ($overviews as $overview) {
            $overview->errors++;

            # Calculate total (2 errors decrement 1 run)
            $overview->total = $this->getTotalOverview($overview->runs, $overview->errors);
            $overview->save();
        }
    }

    public function getTotalOverview($runs, $errors) 
    {
        $total = $runs - (floor($errors / 2));
        
        return $total < 0 ? 0 : $total;
    }

}

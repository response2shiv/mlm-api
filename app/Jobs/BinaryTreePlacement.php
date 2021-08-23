<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Imtigger\LaravelJobStatus\Trackable;
use Carbon\Carbon;

use App\Models\Address;
use App\Models\PaymentMethod;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderConversion;
use App\Models\PreOrder;
use App\Models\PreOrderItem;
use App\Models\DiscountCoupon;
use App\Models\User;
use App\Facades\BinaryPlanManager;
use App\Models\BinaryCommissionCarryoverHistory;
use App\Models\UserActivityHistory;

use DB;
use Log;

class BinaryTreePlacement implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Trackable;

    public $user_id;
    public $action;
    public $direction;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($user_id, $action, $direction = BinaryPlanManager::DIRECTION_LEFT)
    {
        $this->user_id  = $user_id;
        $this->action   = $action;
        $this->direction= $direction;
        //Prepare to add stuff
        $this->prepareStatus();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //
        Log::info('Running Binary Placement...', [get_called_class(), 'startDate' => Carbon::now()]);

        if($this->action == "delete"){
            // $this->deleteUser();
        }else{
            $this->addUser();
        }
    }

    private function addUser(){

    }

    private function deleteUser(){
        $user = User::find($this->user_id);
        // Log::info('Inside queue user '.$this->user_id.'.');
        if(!$user){
            Log::info('User Purge failed for user '.$this->user_id.'.');
        }

        $progress = 1;
        $this->setProgressMax($progress);
        $this->setProgressNow($progress);
        //Trying to find the node so we can remove the user from the tree        
        // Log::info("User distid queue -> ".$user->distid);
        // Log::info("Job ID -> ".$this->job->getJobId());
        // user node check
        $agentNode = BinaryPlanManager::getNodeByAgentTsa($user->distid);
        if (!$agentNode) {
            Log::info('User Purge failed for user '.$this->user_id.'.');
        }else{
            //Delete the user from the tree
            // BinaryPlanManager::deleteNode($agentNode, false);
        }

        try {
            # Start Transaction
            DB::beginTransaction();

            
            //Remove all information from user
            Address::where('userid', $this->user_id)->delete();
            PaymentMethod::where('userID', $this->user_id)->delete();

            //Delete All orders from account
            $orders = Order::where('userid', $this->user_id)->get();
            foreach($orders as $order){                
                OrderItem::where('orderid', $order->id)->delete();
                OrderConversion::where('order_id', $order->id)->delete();
                Order::find($order->id)->delete();

                if($order->coupon_code>0){
                    // Log::info('Coupon Code '.$order->coupon_code.' found. Deactivating..');
                    $voucher = DiscountCoupon::find($order->coupon_code);
                    $voucher->is_used = 0;
                    $voucher->save();
                }
            }
            //We are not deleting the preorders anymore            
            /*$orders = PreOrder::where('userid', $this->user_id)->get();
            foreach($orders as $order){                
                PreOrderItem::where('orderid', $order->id)->delete();
                OrderConversion::where('order_id', $order->id)->delete();
                PreOrder::find($order->id)->delete();
            }*/
            
            BinaryCommissionCarryoverHistory::where('user_id', $this->user_id)->delete();
            UserActivityHistory::where('user_id', $this->user_id)->delete();
            
            $user               = User::find($this->user_id);
            $user->email        = "";
            $user->phonenumber  = "";
            $user->mobilenumber = "";
            $user->username     = "";
            $user->usertype     = 5;//Set the user type to DELETED
            $user->save();
            DB::commit();
        } catch (Exception $e) {
            $userActivityLog = new UserActivityLog;                        
            $userActivityLog->ip_address = "NOT DEFINED";
            $userActivityLog->user_id = $this->user_id;
            $userActivityLog->ip_details = "NOT DEFINED";
            $userActivityLog->action = "PURGE User"; 
            $userActivityLog->old_data = "";
            $userActivityLog->new_data = $e->getMessage();
            $userActivityLog->save();
            Log::info('User Purge failed for user '.$this->user_id.'.');
        }
        Log::info('User Purge is done.');        
    }
}

<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Imtigger\LaravelJobStatus\Trackable;

use Carbon\Carbon;
use App\Models\User;
use App\Models\UserRankHistory;
use App\Models\QvTransaction;
use Log;
use DB;


class CalculateQvTransactions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Trackable;

    /** @var Carbon */
    public $startDate;

    /** @var Carbon */
    public $endDate;
    
    public $timeout = 1200;
    
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($startDate, $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        
        $this->prepareStatus();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info('Calculating QV transactions...', [get_called_class(), 'startDate' => $this->startDate, 'endDate' => $this->endDate]);
        Log::info('Staring now '.Carbon::now()->format("Y/m/d H:i:s"));
        
        //Load all records between the period selected
        /*$transactions = DB::table('vorder_product_qv')
            ->join('users', 'vorder_product_qv.userid', '=', 'users.id')
            ->select('vorder_product_qv.*', 'users.sponsorid')
            ->whereBetween('vorder_product_qv.created_dt', [$this->startDate, $this->endDate])
            ->get();*/
        $transactions = DB::table('orders')
            ->join('users', 'orders.userid', '=', 'users.id')
            ->join('orderItem', 'orderItem.orderid', '=', 'orders.id')
            ->join('products', 'products.id', '=', 'orderItem.productid')
            ->select('orders.*', 'orderItem.*', 'products.qc', 'users.sponsorid')
            ->whereBetween('orderItem.created_date', [$this->startDate, $this->endDate])
            ->get();
        
        Log::info("transactions -> ", array($transactions));
        
        Log::info("count of transactions -> ".count($transactions));
        
        //Setting the max progress on the job_statuses table
        $progress = 1;
        $this->setProgressMax(count($transactions));
        
        DB::table('qv_transaction')->truncate();
        foreach($transactions as $transaction){
            
            //getting sponsor information
            $sponsor_id = $transaction->sponsorid;
            
            //setting counter
            $counter = 1;
            
            //Starting counting the progress on the database
            $this->setProgressNow($progress);
            
            //Save the first record for the root user of the order
            $this->createQvTransaction($transaction, $transaction->userid, $counter);
            
            // Log::info("Before while loop");
            while($counter>0){
                //Get all users directly sponsored by the root user on the order
                $user = DB::table('users')
                ->select('id', 'sponsorid')
                ->where('distid', $sponsor_id)
                ->first();
                if($user && $sponsor_id){
                    // Log::info("User ID ".$user->id);
                    //Save a record for each user going up on the tree
                    $this->createQvTransaction($transaction, $user->id, $counter);
                    $counter    = $counter + 1;
                    $sponsor_id = $user->sponsorid;
                }else{
                    // Log::info("Failed on user ".$user->id);
                    //Sponsor not found, exit loop
                    $counter    = 0;
                }
            }
            $progress = $progress + 1;
        }
        
        Log::info('QV transactions done now '.Carbon::now()->format("Y/m/d H:i:s"));
    }
    
    /**
     * Save record on qv_transactions table
     * @return void
     */
    private function createQvTransaction($transaction, $user_id, $position){
        $qv = QvTransaction::where('initiated_user_id',$user_id);
        
        $qv = new QvTransaction;
        $qv->transaction_id     = $transaction->orderid;
        $qv->transaction_date   = $transaction->created_date;
        $qv->qv                 = $transaction->qv;
        $qv->user_id            = $user_id;
        $qv->level              = $position;
        $qv->initiated_user_id  = $transaction->userid;
        $qv->cv                 = $transaction->cv;
        $qv->qc                 = $transaction->qc;
        $qv->save();
    }
}

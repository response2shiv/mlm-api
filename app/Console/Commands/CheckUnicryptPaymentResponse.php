<?php

namespace App\Console\Commands;

use App\Http\Controllers\MerchantTransactionController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\MerchantTransactionTracker;

class CheckUnicryptPaymentResponse extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'unicrypt:check-payment';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to check on Unicrypt Gateway the status of payments';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        # Get all transactions that have status = UNPAID from Unicrypt
        $preOrders = DB::table('merchant_transaction_tracker')
            ->join('pre_orders', 'merchant_transaction_tracker.transaction_id', '=', 'pre_orders.orderhash')
            ->whereMerchantId(5)
            ->whereStatus('UNPAID')
            ->whereCronProcessed(0)
            ->whereNotNull('pre_order_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('orders')
                    ->whereRaw('orders.trasnactionid = merchant_transaction_tracker.transaction_id');
            })
            ->get();

        $merchantStatus = new MerchantTransactionController;

        foreach ($preOrders as $preOrder) {
            # call it method to verify the unicrypt invoice status and if it is paid then create the order
            $merchantStatus->checkMerchantTransactionStatus($preOrder->transaction_id, 1);
        }
    }
}

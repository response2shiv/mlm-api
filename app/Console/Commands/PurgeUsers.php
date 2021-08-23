<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\User;
use App\Models\PreOrder;
use App\Models\MerchantTransactionTracker;
use App\Jobs\BinaryTreePlacement;
use App\Jobs\FlagOrderTracking;
use App\Facades\BinaryPlanManager;
use Log;

class PurgeUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'purge:users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to purge users that are on pending status on the system';

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
        Log::info("Purge Users Started");
        //Get a list of pending orders
        $pending_invoices = MerchantTransactionTracker::getPendingOrders();

        // Log::info("Pending Invoices ",[$pending_invoices]);

        foreach($pending_invoices as $invoice){
            $preorder = PreOrder::where('orderhash',$invoice->transaction_id)->first();
            // User::purgeUser($preorder->userid);
            if($preorder){
                $user = User::where('id',$preorder->userid)->first();

                // Log::info("Order Hash -> ".$preorder->orderhash." -- User ID: ".$user->id." -- distid ".$user->distid);

                // user node check
                $agentNode = BinaryPlanManager::getNodeByAgentTsa($user->distid);
                if (!$agentNode) {
                    Log::error("Agent Node not found");
                }else{
                    //Call queue to delete the user from the tree        
                    if (!BinaryPlanManager::checkIfNodeHasEnrolledDistributors($agentNode)) {
                        // BinaryTreePlacement::dispatch($preorder->userid, 'delete')->onQueue('binarytree');
                        // BinaryTreePlacement::withChain([new FlagOrderTracking($invoice->id)])->dispatch($preorder->userid, 'delete')->onQueue('binarytree');
                    }else{
                        Log::error("Agent Node has children");
                    }
                }
            }
        }
        
        Log::info("Purge Users finished");
    }
}

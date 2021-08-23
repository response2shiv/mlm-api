<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\MerchantTransactionTracker;
use Log;

class FlagOrderTracking implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $invoice_id;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($invoice_id)
    {
        //Receiving the invoice tracker id
        $this->invoice_id = $invoice_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $tracker = MerchantTransactionTracker::find($this->invoice_id);
        $tracker->cron_processed = 1;
        $tracker->save();
        Log::info('Invoice FlagOrderTracking hash is '.$tracker->orderhash);
    }
}

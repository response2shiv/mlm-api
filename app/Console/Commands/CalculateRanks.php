<?php

namespace App\Console\Commands;

use App\Jobs\RankCalculation;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use App\Jobs\CalculateUserRank;
use App\Jobs\CalculateQvTransactions;
use utill;
use Log;

/**
 * Class CalculateRanks
 * @package App\Console\Commands
 */
class CalculateRanks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ranks:calculate {--fromDate=} {--toDate=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate ranks.';

    /**
     * @throws Exception
     */
    public function handle()
    {
        set_time_limit(0);

        //Starting the process
        $this->info('Start calculation process.');

        // default values for cron-job rank calculation
        $startDate = Carbon::now()->startOfMonth();
        $endDate = Carbon::now()->endOfDay();

        // options should be set together if exist
        $fromDateOption = $this->option('fromDate');
        $toDateOption = $this->option('toDate'); 
        
        if (!empty($fromDateOption) || !empty($toDateOption)) {
            // validate passed option values
            if (!empty($fromDateOption) && !empty($toDateOption)) {
                $startDate = Carbon::parse($fromDateOption);
                $endDate = Carbon::parse($toDateOption)->endOfDay();
            } else {
                throw new Exception('options `fromDate` and `toDate` should be set together');
            }
        }

        if ($endDate->lt($startDate)) {
            throw new Exception('End date should be greater than start');
        }
        
        //Read all orders from view called vorder_product_qv and push them to
        //the table called qv_transaction
        // CalculateQvTransactions::dispatch($startDate, $endDate);
        
        Log::info("processing script with start and end date as ",[$startDate, $endDate]);
        
        // TerminatedRanks::dispatch($startDate, $endDate)->onQueue('default');
        CalculateUserRank::dispatch($startDate, $endDate)->onQueue('default');
        
        $this->info('Calculation is done.');
    }
}

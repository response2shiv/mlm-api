<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\TerminatedRanks;
use Carbon\Carbon;
use Log;

class TerminatedUsersRanks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rank:terminated  {--fromDate=} {--toDate=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate ranks on terminated/suspended accounts';

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
        
        
        Log::info("processing script with start and end date as ",[$startDate, $endDate]);
        
        TerminatedRanks::dispatch($startDate, $endDate)->onQueue('default');
        
        $this->info('Calculation is done.');
    }
}

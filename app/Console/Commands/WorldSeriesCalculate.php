<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\CalculateWorldSeries;
use Exception;

use Carbon\Carbon;
use Log;

class WorldSeriesCalculate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'worldseries:calculate {--fromDate=} {--toDate=} {--truncate=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate World Series based on period';

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
        set_time_limit(0);

        //Starting the process
        $this->info('Start calculation process.');

        // default values for cron-job rank calculation
        $startDate  = Carbon::now()->startOfMonth();
        $endDate    = Carbon::now()->endOfDay();

        // options should be set together if exist
        $fromDateOption = $this->option('fromDate');
        $toDateOption   = $this->option('toDate'); 
        $truncate       = $this->option('truncate'); 
        
        if (!empty($fromDateOption) || !empty($toDateOption)) {
            // validate passed option values
            if (!empty($fromDateOption) && !empty($toDateOption)) {
                $startDate = Carbon::parse($fromDateOption);
                $endDate = Carbon::parse($toDateOption)->endOfDay();
            } else {
                throw new Exception('options `fromDate` and `toDate` should be set together');
            }
        }
        
        if (empty($truncate)) {
            $truncate = false;
        }

        if ($endDate->lt($startDate)) {
            throw new Exception('End date should be greater than start');
        }
        
        CalculateWorldSeries::dispatch($startDate->format('Y-m-d'), $endDate->format('Y-m-d'), $truncate);
        
        $this->info('Calculation is done.');
    }
}

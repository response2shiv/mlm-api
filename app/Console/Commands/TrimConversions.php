<?php

namespace App\Console\Commands;

use App\Jobs\RankCalculation;
use App\Models\Order;
use App\Models\OrderConversion;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use utill;

/**
 * Class CalculateRanks
 * @package App\Console\Commands
 */
class TrimConversions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'conversions:trim';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Removes old conversions that are no longer usable from the order_conversions table';

    /**
     * @throws Exception
     */
    public function handle()
    {
        $count = OrderConversion::query()->whereDate('expires_at', '<', now())->delete();
        $this->info($count . ' old conversions deleted..');
    }
}

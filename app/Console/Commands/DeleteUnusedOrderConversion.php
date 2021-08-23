<?php

namespace App\Console\Commands;

use App\Models\OrderConversion;
use Illuminate\Console\Command;

class DeleteUnusedOrderConversion extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'conversions:delete';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to delete all conversions that are no longer used.';

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
        $yesterday = date('Y-m-d', strtotime('-1 day', strtotime(now())));

        # Delete all conversions unused.
        OrderConversion::whereDate('created_at', '<=', $yesterday)
            ->whereNull('pre_order_id')
            ->whereNull('order_id')
            ->delete();
    }
}

<?php

namespace App\Console\Commands;

use App\Services\WorldSeriesOverviewsSnapshotsService;
use Illuminate\Console\Command;

class UpdateWorldSeriesSnapshot extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'worldseries:snapshots {--snapshot_date=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a snapshot every monday from the world series overviews';

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
        $snapshot_date = $this->option('snapshot_date');

        WorldSeriesOverviewsSnapshotsService::updateEverySunday($snapshot_date);
    }
}

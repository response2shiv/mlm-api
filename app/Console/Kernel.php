<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\UpdateWorldSeriesSnapshot::class,
        Commands\TerminatedUsersRanks::class,
        Commands\PurgeUsers::class,
        //Commands\ImportVGSData::class
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')
        //          ->hourly();
        $schedule->command('telescope:prune')->daily();
        //$schedule->command('conversions:trim')->everyThirtyMinutes();
        $schedule->command('worldseries:snapshots')->weeklyOn(1, '00:05');
        //Just for testing purposes.
        $schedule->command('worldseries:snapshots')->weekly()->fridays()->at('13:30');
        // $schedule->command('cron:log')->everyMinute();

        $schedule->command('horizon:snapshot')->everyFiveMinutes();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}

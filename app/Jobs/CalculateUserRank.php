<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Imtigger\LaravelJobStatus\Trackable;
use Carbon\Carbon;
use App\Models\User;
use App\Models\UserRankHistory;
use Log;

class CalculateUserRank implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Trackable;

    /** @var Carbon */
    public $startDate;

    /** @var Carbon */
    public $endDate;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($startDate, $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;

        $this->prepareStatus();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info('Running ranks...', [get_called_class(), 'startDate' => $this->startDate, 'endDate' => $this->endDate]);
        $this->monthStart = $this->startDate;
        $this->monthEnd = $this->endDate;

        Log::info('CalculateUserRank Calculation Started');
        
        // $month  = Carbon::format($this->endDate)->format('m');
        // $year   = Carbon::format($this->endDate)->format('Y');
        
        $users = User::getRankUsers();
        
        $progress = 1;
        $this->setProgressMax(count($users));
        
        Log::info('Progress max -> '.count($users));
        
        $response = array();
        foreach($users as $user){
            
            // $pqv = User::getMonthPQV($user->distid, $month, $year);
            $this->setProgressNow($progress);
            
            $treeCount = User::getPersonallyEnrolledActive($user->id, $this->startDate, $this->endDate);
            
            
            $rankQVLimit = User::getRankRQVLimit($user->id, $this->endDate);
            
            $response['rank_qv_limit'] = $rankQVLimit;
            
            $rankLimits = User::getQualifiedRankLimits($user->id, $this->endDate);
            $response['rank_limits'] = $rankLimits;
            
            $rootUserPQV = User::getRootUserPQV($user->distid, $this->startDate, $this->endDate);
            $response['root_user_pqv'] = $rootUserPQV;
            
            $rootUserPQC = User::getRootUserPQC($user->distid, $this->startDate, $this->endDate);
            $response['root_user_pqc'] = $rootUserPQC;
            
            // Log::info("User ID is ".$user->distid." -- PQV: ".$pqv);
            Log::info("User ID is ".$user->distid." QV Limit -> ",$response);
            
            // $mqv = User::getMonthPQV($user->distid, $month, $year);
            
            Log::info("Tree Count",array($treeCount));
            
            $progress = $progress + 1;
        }
        Log::info('calculateQVRank is done.');
    }
}

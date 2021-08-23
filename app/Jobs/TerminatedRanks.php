<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Imtigger\LaravelJobStatus\Trackable;

use App\Models\User;
use App\Models\UserRankHistory;
use Carbon\Carbon;
use Log;

class TerminatedRanks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Trackable;

    /** @var Carbon */
    private $startDate;

    /** @var Carbon */
    private $endDate;
    
    /**
     * Create a new job instance.
     * @param Carbon $startDate
     * @param Carbon $endDate
     */
    public function __construct(Carbon $startDate, Carbon $endDate)
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
        $this->populateTransactionsTable($this->startDate, $this->endDate);
        $this->calculateTerminatedRank($this->startDate, $this->endDate);
    }
    
    /**
     * Calculate terminated rank by both QV and QC
     *
     * @param $startDate
     * @param $endDate
     */
    public function calculateTerminatedRank($startDate, $endDate)
    {
        Log::info('Running ranks...', [get_called_class(), 'startDate' => $startDate, 'endDate' => $endDate]);
        $this->monthStart = $startDate;
        $this->monthEnd = $endDate;

        Log::info('calculateQVRank Calculation Started');
        
        // $month  = Carbon::format($endDate)->format('m');
        // $year   = Carbon::format($endDate)->format('Y');
        
        // Log::info("Month ".$month." -- Year: ".$year);
        
        $users = User::getRankUsers();
        
        $response = array();
        foreach($users as $user){
            
            // $pqv = User::getMonthPQV($user->distid, $month, $year);
            
            $treeCount = User::getPersonallyEnrolledActive($user->id, $startDate, $endDate);
            
            
            $rankQVLimit = User::getRankRQVLimit($user->id, $endDate);
            
            $response['rank_qv_limit'] = $rankQVLimit;
            
            $rankLimits = User::getQualifiedRankLimits($user->id, $endDate);
            $response['rank_limits'] = $rankLimits;
            
            $rootUserPQV = User::getRootUserPQV($user->distid, $startDate, $endDate);
            $response['root_user_pqv'] = $rootUserPQV;
            
            // Log::info("User ID is ".$user->distid." -- PQV: ".$pqv);
            Log::info("User ID is ".$user->distid." QV Limit -> ",$response);
            
            // $mqv = User::getMonthPQV($user->distid, $month, $year);
            
            Log::info("Tree Count",array($treeCount));
        }
        Log::info('calculateQVRank is done.');
    }
    
    
    private function populateTransactionsTable($startDate, $endDate){
        
        
        
    }
}

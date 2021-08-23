<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use utill;
use Auth;
use DB;

class UserRankHistory extends Model {

    /**
     * {@inheritDoc}
     */
    protected $table = 'user_rank_history';

    public static function getCurrentMonthUserInfo($userId) {
        return DB::table('vuser_rank_summary')
            ->select('monthly_rank_desc', 'achieved_rank_desc', 'monthly_qv', 'monthly_rank', 'monthly_tsa','monthly_qc', 'qualified_qv')
            ->where('user_id', $userId)
            ->first();
    }

    public static function getRankMatrics($distId, $currentMonthRank) {
        $rec = DB::select("SELECT * FROM get_rank_metrice('$distId',$currentMonthRank)");
        if (count($rec) > 0)
            return $rec[0];
        else
            return null;
    }

    public static function getRankMetricsMonth($distId, $currentMonthRank, $month, $year) {
        // Log::info("Month now is ".Carbon::now()->format('m'));
        $response = array();
        $last_day = Carbon::createFromDate($year, $month, '01', 'America/Chicago')->endOfMonth()->format('Y-m-d');
        $period = $last_day.' 00:00:00';

        $history = UserRankHistory::getRankInMonth(Auth::user(), $period);

        if(Carbon::now()->format('m') == $month && Carbon::now()->format('Y') == $year){
            // $current_rank_info = $this->getCurrentMonthUserInfo($distId);
            $current_rank_info = UserRankHistory::getCurrentMonthUserInfo(Auth::user()->id);
            if ($current_rank_info == null) {
                $response['rank']               = 10;
                $response['achieved_rank_desc'] = strtoupper("Ambassador");
                $response['monthly_rank_desc']  = strtoupper("Ambassador");
                $response['monthly_qv']         = number_format(Auth::user()->current_month_qv, 0, '',',');
                $response['monthly_tsa']        = 0;
                $response['monthly_qc']         = 0;
            } else {
                $response['rank']               = $current_rank_info->monthly_rank;
                $response['achieved_rank_desc'] = strtoupper($current_rank_info->achieved_rank_desc);
                $response['monthly_rank_desc']  = strtoupper($current_rank_info->monthly_rank_desc);
                $response['monthly_qv']         = number_format(Auth::user()->current_month_qv, 0, '',',');
                $response['monthly_tsa']        = number_format($current_rank_info->monthly_tsa, 0, '',',');
                $response['monthly_qc']         = number_format($current_rank_info->monthly_qc, 0, '',',');
            }
            $rk                         = UserRankHistory::getRankMatrics(Auth::user()->distid, $currentMonthRank);
            $response['rank_qv']        = number_format($rk->rankqv, 0, '',',');
            $next_rank                  = UserRankHistory::getRankMatrics($distId, $currentMonthRank);
            $next_rank->nextlevel_qv    = number_format($next_rank->nextlevel_qv, 0, '',',');
            $response['next_rank']      = $next_rank;
            
            //get next rank data based on current month
            $next_rank_current = $current_rank_info->monthly_rank;
            

        }else{                        
            if(!$history){
                $response['rank']               = 10;
                $response['achieved_rank_desc'] = strtoupper("Ambassador");
                $response['monthly_rank_desc']  = strtoupper("Ambassador");
                $response['monthly_qv']         = 0;
                $response['monthly_tsa']        = 0;
                $response['monthly_qc']         = 0;
                $response['rank_qv']            = 0;
                $next_rank_current              = 0;        
            }else{
                $response['rank']               = $history->monthly_rank;
                $response['achieved_rank_desc'] = strtoupper($history->monthly_rank_desc);
                $response['monthly_rank_desc']  = strtoupper($history->monthly_rank_desc);
                $response['monthly_qv']         = number_format($history->monthly_qv, 0, '',',');
                $response['monthly_tsa']        = number_format($history->qualified_tsa, 0, '',',');
                $response['monthly_qc']         = number_format($history->monthly_qc, 0, '',',');
                $response['rank_qv']            = number_format($history->qualified_qv, 0, '',',');
                // $currentMonthRank               = number_format($history->monthly_rank, 0, '',',');
                //get next rank data based on current month
                $next_rank_current = $history->monthly_rank;        
            }
            
            $next_rank                  = UserRankHistory::getRankMatrics($distId, $currentMonthRank);
            $next_rank->nextlevel_qv    = number_format($next_rank->nextlevel_qv, 0, '',',');
            $response['next_rank']      = $next_rank;

            
        }
        $rmetrics = UserRankHistory::getRankMatrics(Auth::user()->distid, $next_rank_current);
        $rmetrics->nextlevel_qv = number_format($rmetrics->nextlevel_qv, 0, '',',');
        $response['next_rank_current'] = $rmetrics;
        
        $last_day = Carbon::createFromDate($year, $month)->endOfMonth()->format('Y-m-d');
        $period = $last_day.' 00:00:00';
        $history = UserRankHistory::getRankInMonth(Auth::user(), $period);
        if($history){
            // $response['next_rank_current']->rankqv = number_format($history->qualified_qv, 0, '',',');
            if(Carbon::now()->format('m') == $month && Carbon::now()->format('Y') == $year){
                $response['next_rank_current']->rankqv = number_format(UserRankHistory::getQV(Auth::user()->distid, $next_rank_current), 0, '',',');
            }else{
                $response['next_rank_current']->rankqv = number_format($history->qualified_qv, 0, '',',');
            }
        }else{
            $response['next_rank_current']->rankqv = 0;
        }
        
        
        if($currentMonthRank){
            $cmetrics = UserRankHistory::getRankMatrics(Auth::user()->distid, $currentMonthRank - 10);
            
            $rank_def = DB::select("SELECT * FROM rank_definition where rankval='".$currentMonthRank."'");
            $rank_def[0]->min_qv = number_format($rank_def[0]->min_qv, 0, '',',');
            $response['rank_definition'] = $rank_def[0];
            // $response['rank_definition']->rankqv = number_format($cmetrics->rankqv, 0, '',',');
            $response['rank_definition']->rankqv = number_format(UserRankHistory::getQV(Auth::user()->distid, $currentMonthRank - 10), 0, '',',');
            $response['rank_definition']->min_qc = number_format($response['rank_definition']->min_qc, 0, '',','); 
        }else{
            $response['rank_definition'] = array();
            // $response['rank_definition']->rankqv = number_format($cmetrics->rankqv, 0, '',',');
        }
        
        
        return $response;
        
    }

    public static function getTopContributorsMonth($distId, $currentMonthRank, $month, $year) {
        //limit of the current rank is always the mac of the previous rank
        $limit = $currentMonthRank - 10;
        // Log::info("Query -> SELECT * FROM get_personal_sponsor_qv_month('$distId',$limit, $month, $year)");
        
        $rec = DB::select("SELECT * FROM get_personal_sponsor_qv_month('$distId',$limit, $month, $year)");
        if (count($rec) > 0)
            return $rec;
        else
            return null;
    }

    /**
     * @param $distId
     * @param $currentMonthRank
     * @return mixed
     */
    public static function getTopContributors($distId, $currentMonthRank)
    {
        return DB::select(
            sprintf(
                "SELECT * FROM(SELECT * FROM get_personal_sponsor_qv('$distId',$currentMonthRank))x LIMIT %d",
                \App\Services\AchievedRankService::TOP_LEG_COUNT
            )
        );
    }

    /**
     * @param $distId
     * @param $currentMonthRank
     * @return int
     */
    public static function getQV($distId, $currentMonthRank)
    {
        $qv = 0;
        
        // Log::info("Query 1 = select sum(qv_contribution) as qv_contribution from get_personal_sponsor_qv('$distId', $currentMonthRank)");
        $rec = DB::select(
            "select sum(qv_contribution) as qv_contribution from get_personal_sponsor_qv('$distId', $currentMonthRank)"
        );
        if (count($rec) > 0) {
            $qv += $rec[0]->qv_contribution;
        }

        $pqv = DB::select(sprintf(
            '
            SELECT COALESCE(SUM(o.orderqv), 0) as pqv
            FROM users u
            JOIN orders o
            ON u.id = o.userid
            WHERE o.created_dt >= \'%s\'
                AND o.created_dt <= \'%s\'
                AND (o.statuscode = 1 OR o.statuscode = 6)
                AND u.distid = \'%s\';
            ',
            Carbon::now()->startOfMonth()->format('Y-m-d H:i:s'),
            Carbon::now()->endOfMonth()->format('Y-m-d H:i:s'),
            $distId
        ));
        
        // Log::info("Query 2 = SELECT COALESCE(SUM(o.orderqv), 0) as pqv
        //     FROM users u
        //     JOIN orders o
        //     ON u.id = o.userid
        //     WHERE o.created_dt >= '".Carbon::now()->startOfMonth()->format('Y-m-d H:i:s')."'
        //         AND o.created_dt <= '".Carbon::now()->endOfMonth()->format('Y-m-d H:i:s')."'
        //         AND (o.statuscode = 1 OR o.statuscode = 6)
        //         AND u.distid = '".$distId."'");

        if ($pqv) {
            $qv += $pqv[0]->pqv;
        }

        return $qv;
    }

    public static function getTSA($distId, $currentMonthRank) {
        $rec = DB::select("select sum(tsa_contribution) as tsa_contribution from get_qualifying_qv_tsa('$distId',$currentMonthRank)");
        if (count($rec) > 0)
            return number_format($rec[0]->tsa_contribution);
        else
            return 0;
    }

    public static function getCurrentMonthlyRec($userId) {
        return DB::table('vmonthly_rank_for_widget')
                        ->where('user_id', $userId)
                        ->first();
    }

    public static function getPreviousMonthlyRec($userId) {
        return DB::table('vmonthly_rank_for_widget_prev')
                        ->where('user_id', $userId)
                        ->first();
    }

    // as cron job
    public static function calculateDownlineQV(Carbon $fromDate, Carbon $toDate) {
        set_time_limit(0);

        // Log::info('Running calculateDownlineQv() on DB', [get_called_class(), 'fromDate' => $fromDate, 'toDate' => $toDate]);
        DB::select(sprintf("select * from calculate_downline_qv_with_tsa('%s', '%s')", $fromDate, $toDate));
        // Log::info('Done running calculateDownlineQv() on DB', [get_called_class()]);
    }

    /**
     * @param $user
     * @param $period
     * @return mixed
     */
    public static function getRankInMonth($user, $period)
    {
        return DB::table('user_rank_history')
            ->where('user_id', $user->id)
            ->whereDate('period', $period)
            ->first();
    }

    /**
     * Load percentual of active Ambassadors on the dashboard
     */
    public static function getMonthlyPerformanceAmbassadors(){    
        //run monthly performance last months
        $amb_last_month = UserRankHistory::runMonthlyPerformanceQuery(Carbon::now()->startOfMonth()->subMonth()->endOfMonth()->format('Y-m-d'));

        //run monthly performance for 2 months ago
        $amb_two_months = UserRankHistory::runMonthlyPerformanceQuery(Carbon::now()->startOfMonth()->subMonths(2)->endOfMonth()->format('Y-m-d'));
        
        try{
            $percentage = (($amb_last_month / $amb_two_months) - 1) * 100;
            $transform = number_format($percentage, 2);
            return $transform;
        } catch (\Exception $e) {
            //If division fails
            return 0;
        }        
    }

    private static function runMonthlyPerformanceQuery($date){
        $sql = "with recursive distributors as (
            SELECT 0 as knt, id, distid, is_active, current_product_id, created_dt, account_status FROM users WHERE distid = '".Auth::user()->distid."'
            UNION
            SELECT d.knt+1, sp.id, sp.distid, sp.is_active, sp.current_product_id, sp.created_dt, sp.account_status FROM users sp INNER JOIN distributors d ON d.distid = sp.sponsorid
          )
          select count(*) from distributors u
          WHERE (
                  u.id IN (
                    SELECT userid FROM orders
                    WHERE date(created_dt) >= to_date('".$date."', 'YYYY-MM-DD') - interval '1 month' GROUP BY userid HAVING sum(orderqv) >= 100
                    )
                    or (u.current_product_id = 16 and u.created_dt >= to_date('".$date."', 'YYYY-MM-DD') - interval '1 year')
                    or u.distid in (
                      'A1357703',
                      'A1637504',
                      'TSA9846698',
                      'TSA3564970',
                      'TSA9714195',
                      'TSA8905585',
                      'TSA2593082',
                      'TSA0707550',
                      'TSA9834283',
                      'TSA5138270',
                      'TSA8715163'
                    )
                    )
                    and u.account_status not in ('TERMINATED', 'SUSPENDED')
                    and u.distid <> '".Auth::user()->distid."'";
        $counter = DB::select($sql);
        if($counter){
            return $counter[0]->count;
        }else{
            return 0;
        }
    }

    /**
     * Load percentual of active Ambassadors on the dashboard
     */
    public static function getMontlhyPerformanceCustomers(){
        //Get data until Last two months
        $sql = sprintf("select count(*) from customers where userid='".Auth::user()->id."' and created_date between '%s' and '%s'", '2019-03-01', Carbon::now()->startOfMonth()->subMonths(2)->endOfMonth()->format('Y-m-d'));
        $two_months_counter = DB::select($sql);
        //Get data until Last month
        $sql = sprintf("select count(*) from customers where userid='".Auth::user()->id."' and created_date between '%s' and '%s'", '2019-03-01', Carbon::now()->startOfMonth()->subMonth()->endOfMonth()->format('Y-m-d'));
        $last_month_counter = DB::select($sql);

        try{
            $percentage = (($last_month_counter[0]->count / $two_months_counter[0]->count) - 1) * 100;
            $transform = number_format($percentage, 2);
            return $transform;
        } catch (\Exception $e) {
            //If division fails
            return 0;
        }        
    }
}

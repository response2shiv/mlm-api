<?php

namespace App\Http\Controllers\Affiliates;

use App\Http\Controllers\Controller;
use App\Models\BinaryCommission;
use App\Models\EwalletTransaction;
use App\Models\LeadershipCommission;
use App\Models\UnilevelCommission;
use App\Models\TSBCommission;
use App\Models\PromoCommission;
use App\Models\Commission;
use App\Services\TsbCommissionService;
use App\Services\UnilevelService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class CommissionController extends Controller
{
    const PAGE_ITEMS_LIMIT = 100;
    const UNILEVEL_KEY = 'unilevel';
    const LEADERSHIP_KEY = 'leadership';

    public function getSqlCommission($year = "") 
    {
        //DB::enableQueryLog();

        # Conditional to retrieve just months of the selected year
        $unilevel   = !empty($year) ? " AND date_part('year', end_date) = ".$year : "";
        $leadership = !empty($year) ? " AND date_part('year', end_date) = ".$year : "";
        $tsb        = !empty($year) ? " AND date_part('year', created_at) = ".$year : "";
        $fsb        = !empty($year) ? " AND date_part('year', processed_date) = ".$year : "";
        $dualTeam   = !empty($year) ? " AND date_part('year', week_ending) = ".$year : "";

        $sqlCommission = DB::select("
            SELECT SUM(unilevel) AS unilevel
                 , SUM(leadership) AS leadership
                 , SUM(tsb) AS tsb
                 , SUM(fsb) AS fsb
                 , SUM(dual_team) AS dual_team
                 , year || '-' || LPAD(month::VARCHAR, 2, '0') AS period
                 , month AS month
                 , (SELECT name FROM months WHERE months.id = month) as name

            FROM (
                    SELECT SUM(amount) AS unilevel
                         , 0 AS leadership
                         , 0 AS tsb
                         , 0 AS fsb
                         , 0 AS dual_team
                         , date_part('month', end_date) AS month
                         , date_part('year', end_date) AS year
                      FROM unilevel_commission 
                     WHERE user_id = :user_id 
                       AND status in ('posted', 'paid') 
                           $unilevel
                  GROUP BY month, year

                     UNION

                    SELECT 0 AS unilevel
                         , SUM(amount) AS leadership
                         , 0 AS tsb
                         , 0 AS fsb
                         , 0 AS dual_team
                         , date_part('month', end_date) AS month
                         , date_part('year', end_date) AS year
                      FROM leadership_commission 
                     WHERE user_id = :user_id 
                       AND status in ('paid', 'posted') 
                           $leadership
                  GROUP BY month, year

                     UNION

                    SELECT 0 AS unilevel
                         , 0 AS leadership
                         , SUM(amount) AS tsb
                         , 0 AS fsb
                         , 0 AS dual_team
                         , date_part('month', created_at) AS month
                         , date_part('year', created_at) AS year
                      FROM tsb_commission 
                     WHERE user_id = :user_id 
                       AND status in ('paid', 'posted')
                           $tsb
                  GROUP BY month, year

                     UNION

                    SELECT 0 AS unilevel
                         , 0 AS leadership
                         , 0 AS tsb
                         , SUM(amount) AS fsb
                         , 0 AS dual_team
                         , date_part('month', processed_date) AS month
                         , date_part('year', processed_date) AS year
                      FROM commission 
                     WHERE user_id = :user_id 
                           $fsb
                  GROUP BY month, year

                     UNION

                    SELECT 0 AS unilevel
                         , 0 AS leadership
                         , 0 AS tsb
                         , 0 AS fsb
                         , SUM(amount_earned) AS dual_team
                         , date_part('month', week_ending) AS month
                         , date_part('year', week_ending) AS year
                      FROM binary_commission 
                     WHERE user_id = :user_id 
                           $dualTeam
                  GROUP BY month, year
                
                ) AS results

           GROUP BY year
                  , month 

           ORDER BY results.year ASC
                  , results.month DESC", 
           [
               'user_id' => Auth::user()->id, 
           ]);

        //dd(DB::getQueryLog());

        return $sqlCommission;

    }


    public function getCommission() {
        
        $pendingPost = DB::table('commission_dates')
            ->select('*')
            ->get();
        
        $this->setResponse([
            'weeks' => $this->getWeeklyCommissionDates(),
            'pendingPost' => $pendingPost,
            'monthCommissionDates' => $this->getMonthCommissionDates(),
        ]);

        $this->setResponseCode(200);

        return $this->showResponse();
    }

    public function getCommissionViewer(Request $request)
    {
        # Get year from request
        $year = $request->get('year', date('Y'));

       $this->setResponse([
           'graphs' => $this->getSqlCommission(),
           'resume' => $this->getSqlCommission($year),
       ]);

       $this->setResponseCode(200);

       return $this->showResponse();
    }

    public function getCommissionDetails(Request $request)
    {

        if ($request->has('period')) {
            list($year, $month) = explode('-', $request->get('period'));
            $filterDate = " AND date_part('month', processed_date) = ".$month." AND date_part('year', processed_date) = ".$year;
        }

        if ($request->has('week')) {
            $week = $request->get('week') . " 23:59:59";
            $filterDate = " AND processed_date = '".$week."'";
        }

        $type  = $request->get('type');

        DB::enableQueryLog();
    
        switch ($type) {
            case 'unilevel':
                $resume = UnilevelCommission::where('user_id', '=', Auth::user()->id)
                    ->whereRaw("date_part('month', end_date) = ".$month)
                    ->whereRaw("date_part('year', end_date) = ".$year)
                    ->whereIn('status', [UnilevelService::POSTED_STATUS, UnilevelService::PAID_STATUS])
                    ->groupByRaw("level, level_percent")
                    ->selectRaw("sum(amount) AS amount, level, 
                        CASE WHEN level = 1 THEN 10
                             WHEN level = 2 THEN 10
                             WHEN level = 3 THEN 10
                             WHEN level = 4 THEN 8
                             WHEN level = 5 THEN 7
                             WHEN level = 6 THEN 6
                             WHEN level = 7 THEN 5
                         END AS level_percent ")
                    ->orderBy('level')
                    ->get();
                break;
            
            case 'leadership':
                $resume = LeadershipCommission::where('user_id', '=', Auth::user()->id)
                    ->whereRaw("date_part('month', end_date) = ".$month)
                    ->whereRaw("date_part('year', end_date) = ".$year)
                    ->whereIn('status', [UnilevelService::POSTED_STATUS, UnilevelService::PAID_STATUS])
                    ->groupByRaw("level, level_percent")
                    ->selectRaw("sum(amount) as amount, level,
                        CASE WHEN level = 1 THEN 2
                             WHEN level = 2 THEN 3
                             WHEN level = 3 THEN 4
                             WHEN level = 4 THEN 5
                         END AS level_percent ")
                    ->orderBy('level')
                    ->get();
                break;

            case 'tsb':
                $resume = TSBCommission::where('user_id', '=', Auth::user()->id)
                    ->whereRaw("date_part('month', created_at) = ".$month)
                    ->whereRaw("date_part('year', created_at) = ".$year)
                    ->whereIn('status', [TsbCommissionService::POSTED_STATUS, TsbCommissionService::PAID_STATUS])
                    ->groupByRaw("level")
                    ->selectRaw("sum(amount) as amount, '-' as level, '-' as level_percent")
                    ->orderBy('level')
                    ->get();
                break;

            case 'fsb':

                $resume = DB::table('commission_setting')
                    ->selectRaw("  commission_setting.level
                                 , commission_setting.percentage AS level_percent
                                 , COALESCE(( 
                                    SELECT SUM(amount) as amount 
                                      FROM commission
                                     WHERE commission_setting.level = commission.level
                                       AND user_id = ".Auth::user()->id."
                                       $filterDate
                                 ), 0) AS amount")
                    ->orderBy('commission_setting.level')
                    ->groupByRaw("commission_setting.level, level_percent")
                    ->get();
                break;

            default:
                # code...
                break;
        }

//dd(DB::getQueryLog());

       $this->setResponse([
           'resume' => $resume,
           'amount' => $resume->sum('amount')
       ]);

       $this->setResponseCode(200);

       return $this->showResponse();
    }

    public function getCommissionDetailsByLevel(Request $request)
    {

        $period = $request->get('period');
        $week = $request->get('week');

        if (!is_null($period) && !empty($period)) {
            list($year, $month) = explode('-', $period);
            $filterDate = " date_part('month', processed_date) = ".$month." AND date_part('year', processed_date) = ".$year;
        } elseif (!is_null($week) && !empty($week)) {
            $week .= " 23:59:59";
            $filterDate = " processed_date = '".$week."'";
        }

        $type  = $request->get('type');
        $level = $request->get('level');

        DB::enableQueryLog();

        switch (strtolower($type)) {
            case 'unilevel':
                $commissionsByLevel = UnilevelCommission::where('user_id', '=', Auth::user()->id)
                    ->join('orderItem AS oi', 'unilevel_commission.order_id', '=', 'oi.id')
                    ->join('orders', 'oi.orderid', '=', 'orders.id')
                    ->join('products', 'oi.productid', '=', 'products.id')
                    ->join('users', 'orders.userid', '=', 'users.id')
                    ->whereRaw("date_part('month', end_date) = ".$month)
                    ->whereRaw("date_part('year', end_date) = ".$year)
                    ->whereIn('unilevel_commission.status', [UnilevelService::POSTED_STATUS, UnilevelService::PAID_STATUS])
                    ->where('unilevel_commission.level', '=', $level)
                    ->selectRaw("unilevel_commission.amount, unilevel_commission.level, (unilevel_commission.percent * 100) AS percent, users.firstname || ' ' || users.lastname AS user, oi.cv, orders.created_date, productdesc, orders.id as order_id")
                    ->get();
                break;

            case 'leadership':
                $commissionsByLevel = LeadershipCommission::where('user_id', '=', Auth::user()->id)
                    ->join('orderItem AS oi', 'leadership_commission.order_id', '=', 'oi.id')
                    ->join('orders', 'oi.orderid', '=', 'orders.id')
                    ->join('products', 'oi.productid', '=', 'products.id')
                    ->join('users', 'orders.userid', '=', 'users.id')
                    ->whereRaw("date_part('month', end_date) = ".$month)
                    ->whereRaw("date_part('year', end_date) = ".$year)
                    ->whereIn('leadership_commission.status', [UnilevelService::POSTED_STATUS, UnilevelService::PAID_STATUS])
                    ->where('leadership_commission.level', '=', $level)
                    ->selectRaw("leadership_commission.amount, leadership_commission.level, (leadership_commission.percent * 100) AS percent, users.firstname || ' ' || users.lastname as user, oi.cv, orders.created_date, productdesc, orders.id as order_id")
                    ->get();
                break;

            case 'tsb':
                $commissionsByLevel = TSBCommission::where('user_id', '=', Auth::user()->id)
                    ->join('orderItem AS oi', 'tsb_commission.order_id', '=', 'oi.id')
                    ->join('orders', 'oi.orderid', '=', 'orders.id')
                    ->join('products', 'oi.productid', '=', 'products.id')
                    ->join('users', 'orders.userid', '=', 'users.id')
                    ->whereRaw("date_part('month', tsb_commission.created_at) = ".$month)
                    ->whereRaw("date_part('year', tsb_commission.created_at) = ".$year)
                    ->whereIn('tsb_commission.status', [UnilevelService::POSTED_STATUS, UnilevelService::PAID_STATUS])
                    ->where('tsb_commission.level', '=', $level)
                    ->selectRaw("tsb_commission.*, 'N/A' AS level, 'N/A' AS percent, users.firstname || ' ' || users.lastname as user, oi.cv, orders.created_date, productdesc, orders.id as order_id")
                    ->get();
                break;

            case 'fsb':
                $commissionsByLevel = Commission::where('user_id', '=', Auth::user()->id)
                    ->join('orders', 'commission.order_id', '=', 'orders.id')
                    ->join('orderItem AS oi', 'orders.id', '=', 'oi.orderid')
                    ->join('products', 'oi.productid', '=', 'products.id')
                    ->join('users', 'orders.userid', '=', 'users.id')
                    ->join('commission_setting', 'commission_setting.level', '=', 'commission.level')
                    ->whereRaw($filterDate)
//                    ->whereRaw("date_part('year', processed_date) = ".$year)
//                    ->whereIn('tsb_commission.status', [UnilevelService::POSTED_STATUS, UnilevelService::PAID_STATUS])
                    ->where('commission.level', '=', $level)
                    ->where('oi.cv', '>', 0)
                    ->selectRaw("commission.*, commission_setting.percentage as percent, users.firstname || ' ' || users.lastname as user, oi.cv, orders.created_date, 
                        CASE WHEN productid = 2 THEN 'Coach Enrollment Pack'
                             WHEN productid = 3 THEN 'Business Enrollment Pack'
                             WHEN productid = 4 THEN 'First Class Enrollment Pack'
                             WHEN productid = 5 
                               OR productid = 6 
                               OR productid = 7 
                               OR productid = 8 
                               OR productid = 9 
                               OR productid = 10 THEN 'Upgrade Pack'
                             WHEN productid = 52 THEN 'Vibe Overdrive Enrollment Pack'
                         END AS productdesc, orders.id as order_id")
                    ->get();
                break;

            default:
                # code...
                break;
        }


       // dd(DB::getQueryLog());

       $this->setResponse([
           'resume' => $commissionsByLevel,
       ]);

       $this->setResponseCode(200);

       return $this->showResponse();
    }
    

    public function getWeeklyCommission(Request $request) 
    {
        $year = $request->get('year', date('Y'));
        $month = ($year == Carbon::now()->format('Y')) ? Carbon::now()->format('m') : 12;

        // To Debug
        DB::enableQueryLog();

        $query = DB::select("
            SELECT months.name 
                 , months.short_name
                 , $year || '-' || LPAD(months.id::VARCHAR, 2, '0') AS period
                 , LPAD(months.id::VARCHAR, 2, '0') AS month 
                 ,  (
                        SELECT SUM(total)  
                          FROM week_detail 
                         WHERE user_id = :user_id 
                           AND date_part('year', week_ending) = :year 
                           AND date_part('month', week_ending) = months.id
                      GROUP BY date_part('month', week_ending)
                    ) AS fsb
                  , (
                        SELECT SUM(amount_earned) AS TOTAL
                          FROM binary_commission 
                         WHERE user_id = :user_id 
                           AND date_part('year', week_ending) = :year 
                           AND date_part('month', week_ending) = months.id 
                    ) AS dual_team
                  , (
                        SELECT SUM(cv) as total_left 
                          FROM public.get_binary_orders(
                                 (SELECT distid FROM users WHERE id = :user_id),
                                 'L',
                                 (
                                  SELECT MIN(week_ending) - (interval '7 days') 
                                    FROM binary_commission 
                                   WHERE user_id = :user_id 
                                     AND date_part('year', week_ending) = :year 
                                     AND date_part('month', week_ending) = months.id
                                 ),
                                 (
                                  SELECT MAX(week_ending) 
                                    FROM binary_commission 
                                   WHERE user_id = :user_id 
                                     AND date_part('year', week_ending) = :year 
                                     AND date_part('month', week_ending) = months.id
                                 )
                        )
                    ) AS volume_left
                  , (
                        SELECT SUM(cv) as total_right 
                          FROM public.get_binary_orders(
                                 (SELECT distid FROM users WHERE id = :user_id),
                                 'R',
                                 (
                                  SELECT MIN(week_ending) - (interval '7 days') 
                                    FROM binary_commission 
                                   WHERE user_id = :user_id 
                                     AND date_part('year', week_ending) = :year 
                                     AND date_part('month', week_ending) = months.id
                                 ),
                                 (
                                  SELECT MAX(week_ending) 
                                    FROM binary_commission 
                                   WHERE user_id = :user_id 
                                     AND date_part('year', week_ending) = :year 
                                     AND date_part('month', week_ending) = months.id
                                 )
                        )
                    ) AS volume_right

               FROM months
              
              WHERE months.id <= :month

           ORDER BY months.id DESC", [
                'user_id' => Auth::user()->id, 
                'month'   => $month,
                'year'    => $year
            ]);

       $this->setResponse([
           'resume' => $query,
       ]);

       $this->setResponseCode(200);

       return $this->showResponse();
    }

/*
    public function getWeeklyCommission(Request $request) 
    {
        $year = $request->get('year', date('Y'));
        $month = ($year == Carbon::now()->format('Y')) ? Carbon::now()->format('m') : 12;

        // To Debug
        DB::enableQueryLog();

        $query = DB::select("
            SELECT months.name 
                 , months.short_name
                 , $year || '-' || LPAD(months.id::VARCHAR, 2, '0') AS period
                 , LPAD(months.id::VARCHAR, 2, '0') AS month 
                 ,  (
                        SELECT SUM(total)  
                          FROM week_detail 
                         WHERE user_id = :user_id 
                           AND date_part('year', week_ending) = :year 
                           AND date_part('month', week_ending) = months.id
                      GROUP BY date_part('month', week_ending)
                    ) AS fsb
                  , (
                        SELECT SUM(amount_earned) AS TOTAL
                          FROM binary_commission 
                         WHERE user_id = :user_id 
                           AND date_part('year', week_ending) = :year 
                           AND date_part('month', week_ending) = months.id 
                    ) AS dual_team
                  , (
                        SELECT SUM(total_volume_left) AS TOTAL
                          FROM binary_commission 
                         WHERE user_id = :user_id 
                           AND date_part('year', week_ending) = :year 
                           AND date_part('month', week_ending) = months.id 
                    ) AS volume_left
                  , (
                        SELECT SUM(total_volume_right) AS TOTAL
                          FROM binary_commission 
                         WHERE user_id = :user_id 
                           AND date_part('year', week_ending) = :year 
                           AND date_part('month', week_ending) = months.id 
                    ) AS volume_right

               FROM months
              
              WHERE months.id <= :month

           ORDER BY months.id DESC", [
                'user_id' => Auth::user()->id, 
                'month'   => $month,
                'year'    => $year
            ]);

       $this->setResponse([
           'resume' => $query,
       ]);

       $this->setResponseCode(200);

       return $this->showResponse();
    }
*/


    public function getWeeklyCommissionDetails(Request $request) 
    {
        $year = $request->get('year', date('Y'));
        $month = $request->get('month');

        DB::enableQueryLog();

        $query = DB::select("
            SELECT SUM(result.amount_earned) AS amount_earned
                  , (
                        SELECT  SUM(cv) as total
                          FROM  public.get_binary_orders(
                                    (SELECT distid FROM users WHERE id = :user_id),
                                    'L',
                                    result.week_ending - (interval '7 days'),
                                    result.week_ending 
                                )
                    ) as volume_left 
                 , (
                        SELECT  SUM(cv) as total 
                          FROM  public.get_binary_orders(
                                   (SELECT distid FROM users WHERE id = :user_id),
                                   'R',
                                   result.week_ending - (interval '7 days'),
                                   result.week_ending 
                                )
                    ) as volume_right

                 , SUM(result.fsb) AS fsb
                 , TO_CHAR(result.week_ending, 'YYYY-MM-DD') AS week_ending
                 , result.month

              FROM (

                SELECT SUM(amount_earned) AS amount_earned
                     , 0 as fsb
                     , week_ending
                     , :month as month
                  FROM binary_commission 
                 WHERE user_id = :user_id 
                   AND date_part('month', week_ending) = :month  
                   AND date_part('year', week_ending) = :year  
                   AND status in ('paid', 'posted')
              GROUP BY week_ending

                  UNION 

                SELECT 0
                     , SUM(total) as fsb
                     , week_ending
                     , :month as month
                  FROM week_detail 
                 WHERE user_id = :user_id 
                   AND date_part('month', week_ending) = :month 
                   AND date_part('year', week_ending) = :year  
              GROUP BY week_ending

            ) as result

            --WHERE result.month <= :month

         GROUP BY result.week_ending, result.month
              
           ORDER BY result.week_ending DESC ", [
                'user_id' => Auth::user()->id, 
                'month'   => $month,
                'year'    => $year
            ]);

       $this->setResponse([
           'resume' => $query,
       ]);

       $this->setResponseCode(200);

       return $this->showResponse();
    }


/*  BACKUP
    public function getWeeklyCommissionDetails(Request $request) 
    {
        $year = $request->get('year', date('Y'));
        $month = $request->get('month');

        DB::enableQueryLog();

        $query = DB::select("
            SELECT SUM(result.amount_earned) AS amount_earned
                 , SUM(result.volume_left) AS volume_left
                 , SUM(result.volume_right) AS volume_right
                 , SUM(result.fsb) AS fsb
                 , TO_CHAR(result.week_ending, 'YYYY-MM-DD') AS week_ending
                 , result.month

              FROM (

                SELECT SUM(amount_earned) AS amount_earned
                     , SUM(total_volume_left) AS volume_left
                     , SUM(total_volume_right) AS volume_right
                     , 0 as fsb
                     , week_ending
                     , :month as month
                  FROM binary_commission 
                 WHERE user_id = :user_id 
                   AND date_part('month', week_ending) = :month  
                   AND date_part('year', week_ending) = :year  
                   AND status in ('paid', 'posted')
              GROUP BY week_ending

                  UNION 

                SELECT 0
                     , 0
                     , 0
                     , SUM(total) as fsb
                     , week_ending
                     , :month as month
                  FROM week_detail 
                 WHERE user_id = :user_id 
                   AND date_part('month', week_ending) = :month 
                   AND date_part('year', week_ending) = :year  
              GROUP BY week_ending

            ) as result

            --WHERE result.month <= :month

         GROUP BY result.week_ending, result.month
              
           ORDER BY result.week_ending DESC ", [
                'user_id' => Auth::user()->id, 
                'month'   => $month,
                'year'    => $year
            ]);

       $this->setResponse([
           'resume' => $query,
       ]);

       $this->setResponseCode(200);

       return $this->showResponse();
    }
*/

    public function getWeeklyCommissionBinaryDetails(Request $request) 
    {
        $weekEnding = $request->get('week');

        $query = DB::table('binary_commission')
            ->where('user_id', Auth::user()->id)
            ->where('week_ending', $weekEnding. ' 23:59:59')
            ->whereIn('status', [BinaryCommission::PAID_STATUS, BinaryCommission::POSTED_STATUS])
            ->get();

       $this->setResponse([
           'resume' => $query,
       ]);

       $this->setResponseCode(200);

       return $this->showResponse();
    }


    public function commissionWeekly(Request $request) {
        $selected = $request->input('unilevel_date');

        $pendingPost = DB::table('commission_dates')
            ->select('*')
            ->get();

        $weekEnding = $request->input('week_ending');
        $weekEnding = explode('#', $weekEnding);

        $sum = UnilevelCommission::where('user_id', '=', Auth::user()->id)
            ->whereRaw('end_date::date = ?', [$selected])
            ->sum('amount');


        $unilevelCommissions = DB::table('unilevel_commission')
            ->selectRaw('end_date, ' . round($sum, 2) . ' as sum')
            ->where('user_id', Auth::user()->id)
            ->whereIn('status', [UnilevelService::POSTED_STATUS, UnilevelService::PAID_STATUS])
            ->whereRaw('end_date::date = ?', [$selected])
            ->groupBy('end_date')
            ->get();


        $sum = LeadershipCommission::where('user_id', '=', Auth::user()->id)
            ->whereRaw('end_date::date = ?', [$selected])
            ->sum('amount');

        $leadershipCommissions = DB::table('leadership_commission')
            ->selectRaw('end_date, ' . round($sum, 2) . ' as sum')
            ->where('user_id', Auth::user()->id)
            ->whereIn('status', [UnilevelService::POSTED_STATUS, UnilevelService::PAID_STATUS])
            ->whereRaw('end_date::date = ?', [$selected])
            ->groupBy('end_date')
            ->get();


        $sum = DB::table('tsb_commission')->where('user_id', '=', Auth::user()->id)
            ->whereRaw('created_at::date = ?', [$selected])
            ->sum('amount');

        $tsbCommissions = DB::table('tsb_commission')
            ->selectRaw('created_at as end_date, ' . round($sum, 2) . ' as sum')
            ->where('user_id', Auth::user()->id)
            ->whereIn('status', [UnilevelService::POSTED_STATUS, UnilevelService::PAID_STATUS])
            ->whereRaw('created_at::date = ?', [$selected])
            ->groupBy('created_at')
            ->get();
        
        // $sum = DB::table('vibe_commissions')->where('user_id', '=', Auth::user()->id)
        //     ->whereRaw('paid_date::date = ?', [$selected])
        //     ->sum('direct_payout');

        // $vibeCommissions = DB::table('vibe_commissions')
        //     ->selectRaw('paid_date as end_date, ' . round($sum, 2) . ' as sum')
        //     ->where('user_id', Auth::user()->id)
        //     ->whereIn('status', [UnilevelService::POSTED_STATUS, UnilevelService::PAID_STATUS])
        //     ->whereRaw('paid_date::date = ?', [$selected])
        //     ->groupBy('paid_date')
        //     ->get();
            
        $promoCommissions = DB::table('promo_commission')
            ->selectRaw("created_dt as end_date, SUM(amount) as sum")
            ->where('user_id', Auth::user()->id)
            ->whereIn('status', [UnilevelService::POSTED_STATUS, UnilevelService::PAID_STATUS])
            ->whereRaw('created_dt::date = ?', [$selected])
            ->groupBy('created_dt')
            ->get();

        if (count($weekEnding) > 1) {
            //pending post
            $pendingCommission = DB::table('commission_temp_post')
                ->where('user_id', Auth::user()->id)
                ->get();

            $this->setResponse([
                'weeks' => $this->getWeeklyCommissionDates(),
                'week_ending' => $weekEnding,
                'pendingCommission' => $pendingCommission ?: 0,
                'pendingPost' => $pendingPost,
                'unilevelCommissions' => $unilevelCommissions,
                'leadershipCommissions' => $leadershipCommissions,
                'tsbCommissions' => $tsbCommissions,
                // 'vibeCommissions' => $vibeCommissions,
                'promoCommissions' => $promoCommissions,
                'selected' => $selected,
                'monthCommissionDates' => $this->getMonthCommissionDates(),
            ]);
            $this->setResponseCode(200);
            return $this->showResponse();

        } else {
            $weekEnding = $weekEnding[0];
            $weekCommissionDetail = $binaryCommission = null;
            if ($weekEnding) {
                $weekCommissionDetail = DB::table('week_detail')
                    ->where('user_id', Auth::user()->id)
                    ->where('week_ending', $weekEnding)
                    ->get();

                $binaryCommission = DB::table('binary_commission')
                    ->where('user_id', Auth::user()->id)
                    ->where('week_ending', $weekEnding)
                    ->whereIn('status', [BinaryCommission::PAID_STATUS, BinaryCommission::POSTED_STATUS])
                    ->value('amount_earned');
            }

            // TODO: Remove it in future
            $adjustmentBinary26 = null;
            if ($weekEnding === '2019-06-02 00:00:00') {

                $adjustTransactRow = EwalletTransaction::where('commission_type', 'BC_ADJUST_26')
                    ->where('user_id', Auth::user()->id)
                    ->first();

                if ($adjustTransactRow) {
                    $adjustmentBinary26 = floatval($adjustTransactRow->amount);
                }
            }

            $adjustment5_12 = null;
            $adjustment5_19 = null;
            $adjustment5_26 = null;
            $adjustment6_02 = null;

            if ($weekEnding === '2019-05-12 23:59:59') {
                $adjustTransactRow512 = EwalletTransaction::where('commission_type', 'BC_ADJUST_5_12')
                    ->where('user_id', Auth::user()->id)
                    ->first();

                if ($adjustTransactRow512) {
                    $adjustment5_12 = floatval($adjustTransactRow512->amount);
                }
            }

            if ($weekEnding === '2019-05-19 23:59:59') {
                $adjustTransactRow519 = EwalletTransaction::where('commission_type', 'BC_ADJUST_5_19')
                    ->where('user_id', Auth::user()->id)
                    ->first();

                if ($adjustTransactRow519) {
                    $adjustment5_19 = floatval($adjustTransactRow519->amount);
                }
            }

            if ($weekEnding === '2019-05-26 23:59:59') {
                $adjustTransactRow526 = EwalletTransaction::where('commission_type', 'BC_ADJUST_5_26')
                    ->where('user_id', Auth::user()->id)
                    ->first();

                if ($adjustTransactRow526) {
                    $adjustment5_26 = floatval($adjustTransactRow526->amount);
                }
            }

            if ($weekEnding === '2019-06-02 23:59:59') {
                $adjustTransactRow602 = EwalletTransaction::where('commission_type', 'BC_ADJUST_6_02')
                    ->where('user_id', Auth::user()->id)
                    ->first();

                if ($adjustTransactRow602) {
                    $adjustment6_02 = floatval($adjustTransactRow602->amount);
                }
            }

            $uni_5_31 = null;
            $uni_5_31_prefix = '+';
            if ($selected === '2019-05-31 23:59:59') {
                $uni5_31_row = EwalletTransaction::where('commission_type', 'UL_5_31')
                    ->where('user_id', Auth::user()->id)
                    ->first();

                if ($uni5_31_row) {
                    $uni_5_31_prefix = $uni5_31_row->type === EwalletTransaction::ADJUSTMENT_DEDUCT ? '-' : '+';
                    $uni_5_31 = number_format(floatval($uni5_31_row->amount), 2);
                }
            }
           $this->setResponse([
               'weeks' => $this->getWeeklyCommissionDates(),
               'week_ending' => $weekEnding,
               'week_commission_detail' => $weekCommissionDetail,
               'binaryCommission' => $binaryCommission ?: 0,
               'adjustmentBinary26' => $adjustmentBinary26,
               'pendingPost' => $pendingPost,
               'unilevelCommissions' => $unilevelCommissions,
               'leadershipCommissions' => $leadershipCommissions,
               'tsbCommissions' => $tsbCommissions,
            //    'vibeCommissions' => $vibeCommissions,
               'promoCommissions' => $promoCommissions,
               'selected' => $selected,
               'adjustment_5_31' => sprintf('%s$%s', $uni_5_31_prefix, $uni_5_31),
               'monthCommissionDates' => $this->getMonthCommissionDates(),
               'adjustment5_12' => $adjustment5_12,
               'adjustment5_19' => $adjustment5_19,
               'adjustment5_26' => $adjustment5_26,
               'adjustment6_02' => $adjustment6_02,
           ]);
            $this->setResponseCode(200);
            return $this->showResponse();
        }
    }

    public function commissionWeeklyDetails(Request $request) {

        $weekEnding       = $request->input('week_ending');
        $binaryWeekEnding = $request->input('binary_week_ending');

        $pendingPost = DB::table('commission_dates')
            ->select('*')
            ->get();

        $weekDate = $weekEnding ?: $binaryWeekEnding;

        $weekCommissionDetail = DB::table('week_detail')
            ->where('user_id', Auth::user()->id)
            ->where('week_ending', $weekDate)
            ->get();

        $binaryCommission = DB::table('binary_commission')
            ->select('amount_earned')
            ->where('user_id', Auth::user()->id)
            ->whereIn('status', [BinaryCommission::PAID_STATUS, BinaryCommission::POSTED_STATUS])
            ->where('week_ending', $weekDate)
            ->value('amount_earned');

        $commissions = $weekEnding ? DB::table('commission')
            ->join('users', 'commission.initiated_user_id', '=', 'users.id')
            ->select('commission.*', 'users.firstname', 'users.lastname')
            ->where('user_id', Auth::user()->id)
            ->where('processed_date', $weekDate)
            ->get() : null;

        $binaryCommissions = $binaryWeekEnding ? BinaryCommission::select('*')
            ->where('user_id', Auth::user()->id)
            ->where('week_ending', $weekDate)
            ->get() : null;

        // TODO: Remove it in future
        $adjustmentBinary26 = null;
        if ($binaryWeekEnding === '2019-06-02 00:00:00') {

            $adjustTransactRow = EwalletTransaction::where('commission_type', 'BC_ADJUST_26')
                ->where('user_id', Auth::user()->id)
                ->first();

            if ($adjustTransactRow) {
                $adjustmentBinary26 = floatval($adjustTransactRow->amount);
            }
        }

        $adjustment5_12 = null;
        $adjustment5_19 = null;
        $adjustment5_26 = null;
        $adjustment6_02 = null;

        if ($binaryWeekEnding === '2019-05-12 23:59:59') {
            $adjustTransactRow512 = EwalletTransaction::where('commission_type', 'BC_ADJUST_5_12')
                ->where('user_id', Auth::user()->id)
                ->first();

            if ($adjustTransactRow512) {
                $adjustment5_12 = floatval($adjustTransactRow512->amount);
            }
        }

        if ($binaryWeekEnding === '2019-05-19 23:59:59') {
            $adjustTransactRow519 = EwalletTransaction::where('commission_type', 'BC_ADJUST_5_19')
                ->where('user_id', Auth::user()->id)
                ->first();

            if ($adjustTransactRow519) {
                $adjustment5_19 = floatval($adjustTransactRow519->amount);
            }
        }

        if ($binaryWeekEnding === '2019-05-26 23:59:59') {
            $adjustTransactRow526 = EwalletTransaction::where('commission_type', 'BC_ADJUST_5_26')
                ->where('user_id', Auth::user()->id)
                ->first();

            if ($adjustTransactRow526) {
                $adjustment5_26 = floatval($adjustTransactRow526->amount);
            }
        }

        if ($binaryWeekEnding === '2019-06-02 23:59:59') {
            $adjustTransactRow602 = EwalletTransaction::where('commission_type', 'BC_ADJUST_6_02')
                ->where('user_id', Auth::user()->id)
                ->first();

            if ($adjustTransactRow602) {
                $adjustment6_02 = floatval($adjustTransactRow602->amount);
            }
        }

        $unilevelCommissions = DB::table('unilevel_commission')
            ->select('end_date')
            ->where('user_id', Auth::user()->id)
            ->whereIn('status', [UnilevelService::POSTED_STATUS, UnilevelService::PAID_STATUS])
            ->groupBy('end_date')
            ->orderBy('end_date', 'desc')
            ->get();

        $this->setResponse([
            'weeks' => $this->getWeeklyCommissionDates(),
            'week_ending' => $weekEnding ?: $binaryWeekEnding,
            'week_commission_detail' => $weekCommissionDetail,
            'commissions' => $commissions,
            'binaryCommissions' => $binaryCommissions,
            'binaryCommission' => $binaryCommission ?: 0,
            'adjustmentBinary26' => $adjustmentBinary26,
            'pendingPost' => $pendingPost,
            'unilevelCommissions' => $unilevelCommissions,
            'monthCommissionDates' => $this->getMonthCommissionDates(),
            'adjustment5_12' => $adjustment5_12,
            'adjustment5_19' => $adjustment5_19,
            'adjustment5_26' => $adjustment5_26,
            'adjustment6_02' => $adjustment6_02,
        ]);

        $this->setResponseCode(200);
        return $this->showResponse();
    }

    public function getWeeklyCommissionDates()
    {
        $date = Carbon::now()->endOfDay();

        /** @var Collection $fsbWeeks */
        $fsbWeeks = DB::table('week_summary')
            ->select('week_ending')
            ->orderBy('week_ending', 'desc')
            ->groupBy('week_ending')
            ->get();

        /** @var Collection $binaryWeeks */
        $binaryWeeks = DB::table('binary_commission')
            ->select('week_ending')
            ->where('week_ending', '<=', $date)
            ->whereIn('status', [BinaryCommission::PAID_STATUS, BinaryCommission::POSTED_STATUS])
            ->orderBy('week_ending', 'desc')
            ->groupBy('week_ending')
            ->get();

        $allWeeks = $fsbWeeks->merge($binaryWeeks->toArray())->map(function ($week) {
            return $week->week_ending;
        })->toArray();

        $select = array_unique($allWeeks);
        rsort($select);

        return $select;
    }

    private function getMonthCommissionDates()
    {
        $unilevelCommissions = DB::table('unilevel_commission')
            ->selectRaw("end_date::date as end_date")
            ->where('user_id', Auth::user()->id)
            ->whereIn('status', [UnilevelService::POSTED_STATUS, UnilevelService::PAID_STATUS])
            ->groupBy('end_date')
            ->orderBy('end_date', 'desc')
            ->get();

        $leadershipCommissions = DB::table('leadership_commission')
            ->selectRaw("end_date::date as end_date")
            ->where('user_id', Auth::user()->id)
            ->whereIn('status', [UnilevelService::POSTED_STATUS, UnilevelService::PAID_STATUS])
            ->groupBy('end_date')
            ->orderBy('end_date', 'desc')
            ->get();

        $tsbCommission = DB::table('tsb_commission')
            ->selectRaw("created_at::date as end_date")
            ->where('user_id', Auth::user()->id)
            ->whereIn('status', [TsbCommissionService::POSTED_STATUS, TsbCommissionService::PAID_STATUS])
            ->groupBy('created_at')
            ->orderBy('created_at', 'desc')
            ->get();

        $promoCommission = DB::table('promo_commission')
            ->selectRaw("created_dt as end_date")
            ->where('user_id', Auth::user()->id)
            ->whereIn('status', [TsbCommissionService::POSTED_STATUS, TsbCommissionService::PAID_STATUS])
            ->groupBy('created_dt')
            ->orderBy('created_dt', 'desc')
            ->get();

        // $vibeCommission = DB::table('vibe_commissions')
        //     ->selectRaw("paid_date::date as end_date")
        //     ->where('user_id', Auth::user()->id)
        //     ->whereIn('status', [TsbCommissionService::POSTED_STATUS, TsbCommissionService::PAID_STATUS])
        //     ->groupBy('paid_date')
        //     ->orderBy('paid_date', 'desc')
        //     ->get();
            
        $select = [];
        foreach ($unilevelCommissions as $commission) {
            $select[] = $commission->end_date;
        }

        foreach ($leadershipCommissions as $commission) {
            $select[] = $commission->end_date;
        }

        foreach ($tsbCommission as $commission) {
            $select[] = $commission->end_date;
        }
        
        foreach ($promoCommission as $commission) {
            $select[] = $commission->end_date;
        }

        // foreach ($vibeCommission as $commission) {
        //     $select[] = $commission->end_date;
        // }

        $select = array_unique($select);
        rsort($select);

        return $select;
    }

    public function unilevelCommissionDetails(Request $request)
    {
        $date = $request->input('date');

        # FIX IT (relations)
        # $unilevelCommissions = UnilevelCommission::select('unilevel_commission.*', 'orders.created_date', 'products.sku', 'users.firstname', 'users.lastname')
        #     ->leftjoin('orders', 'unilevel_commission.order_id', '=', 'orders.id')
        #     ->leftJoin('users', 'orders.userid', '=', 'users.id')
        #     ->leftJoin('public.orderItem', 'public.orderItem.orderid', '=', 'orders.id')
        #     ->leftJoin('products', 'products.id', '=', 'public.orderItem.productid')
        #     ->where('user_id', '=', Auth::user()->id)
        #     ->where('end_date', '=', $date)
        #     ->whereIn('status', [UnilevelService::POSTED_STATUS, UnilevelService::PAID_STATUS])
        #     ->get();

        $unilevelCommissions = UnilevelCommission::with('order.order.user', 'order.product')
            ->where('user_id', '=', Auth::user()->id)
            ->where('end_date', '=', $date)
            ->whereIn('status', [UnilevelService::POSTED_STATUS, UnilevelService::PAID_STATUS])
            ->get();

        $sum = UnilevelCommission::where('user_id', '=', Auth::user()->id)
            ->where('end_date', '=', $date)
            ->whereIn('status', [UnilevelService::POSTED_STATUS, UnilevelService::PAID_STATUS])
            ->sum('amount');

        $this->setResponseCode(200);
        
        $this->setResponse([
            'weeks' => $this->getWeeklyCommissionDates(),
            'monthCommissionDates' => $this->getMonthCommissionDates(),
            'montlyCommissions' => $unilevelCommissions,
            'sum' => round($sum, 2),
            'typeCommission' => 'Unilevel'
        ]);


        return $this->showResponse(); 
    }


    public function leadershipCommissionDetails(Request $request)
    {
        $date = $request->input('date');

        # FIX IT (relations)
        # $commissions = LeadershipCommission::select('leadership_commission.*', 'orders.created_date', 'products.sku', 'users.firstname', 'users.lastname')
        #     ->join('orders', 'leadership_commission.order_id', '=', 'orders.id')
        #     ->leftJoin('users', 'orders.userid', '=', 'users.id')
        #     ->leftJoin('public.orderItem', 'public.orderItem.orderid', '=', 'orders.id')
        #     ->leftJoin('products', 'products.id', '=', 'public.orderItem.productid')
        #     ->where('user_id', '=', Auth::user()->id)
        #     ->where('end_date', '=', $date)
        #     ->whereIn('status', [UnilevelService::POSTED_STATUS, UnilevelService::PAID_STATUS])
        #     ->get();

        $commissions = LeadershipCommission::with('order.order.user', 'order.product')
            ->where('user_id', '=', Auth::user()->id)
            ->where('end_date', '=', $date)
            ->whereIn('status', [UnilevelService::POSTED_STATUS, UnilevelService::PAID_STATUS])
            ->get();

        $sum = LeadershipCommission::where('user_id', '=', Auth::user()->id)
            ->where('end_date', '=', $date)
            ->whereIn('status', [UnilevelService::POSTED_STATUS, UnilevelService::PAID_STATUS])
            ->sum('amount');

        $this->setResponseCode(200);
        
        $this->setResponse([
            'weeks' => $this->getWeeklyCommissionDates(),
            'monthCommissionDates' => $this->getMonthCommissionDates(),
            'montlyCommissions' => $commissions,
            'sum' => round($sum, 2),
            'typeCommission' => 'Leadership'
        ]);

       return $this->showResponse();      
    }
    
    public function tsbCommissionDetails(Request $request)
    {
        $date = $request->input('date');

        $commissions = TSBCommission::with('order.order.user', 'order.product')
            ->where('user_id', '=', Auth::user()->id)
            ->where('created_at', '=', $date)
            ->whereIn('status', [UnilevelService::POSTED_STATUS, UnilevelService::PAID_STATUS])
            ->get();
            
        $sum = TSBCommission::where('user_id', '=', Auth::user()->id)
            ->where('created_at', '=', $date)
            ->whereIn('status', [UnilevelService::POSTED_STATUS, UnilevelService::PAID_STATUS])
            ->sum('amount');

        $this->setResponseCode(200);

        $this->setResponse([
            'montlyCommissions' => $commissions,
            'sum' => round($sum, 2),           
            'monthCommissionDates' => $this->getMonthCommissionDates(),
            'weeks' => $this->getWeeklyCommissionDates(),
            'typeCommission' => 'Tsb'
        ]);

        return $this->showResponse();
    }

    
    public function promoCommissionDetails(Request $request)
    {
        $date = $request->input('date');

        $commissions = PromoCommission::where('user_id', '=', Auth::user()->id)
            ->where('created_dt', '=', $date)
            ->whereIn('status', [UnilevelService::POSTED_STATUS, UnilevelService::PAID_STATUS])
            ->get();
            
        $sum = PromoCommission::where('user_id', '=', Auth::user()->id)
            ->where('created_dt', '=', $date)
            ->whereIn('status', [UnilevelService::POSTED_STATUS, UnilevelService::PAID_STATUS])
            ->sum('amount');

        $this->setResponseCode(200);

        $this->setResponse([
            'montlyCommissions' => $commissions,
            'sum' => round($sum, 2),           
            'monthCommissionDates' => $this->getMonthCommissionDates(),
            'weeks' => $this->getWeeklyCommissionDates(),
            'typeCommission' => 'promo'
        ]);

        return $this->showResponse();
    }

}

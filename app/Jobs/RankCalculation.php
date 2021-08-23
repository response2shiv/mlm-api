<?php

namespace App\Jobs;

// use App\Facades\RankManager;
// use App\Facades\BinaryPlanManager;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Imtigger\LaravelJobStatus\Trackable;
use App\Models\UserStatistic;
use App\Models\Product;
use App\Models\RankInterface;
use App\Models\User;
use App\Models\UserRankHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;

/**
 * Class RankCalculation
 * @package App\Jobs
 */
class RankCalculation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Trackable;

    /** @var Carbon */
    private $startDate;

    /** @var Carbon */
    private $endDate;

    const BATCH_SIZE = 100;
    const QC_KEY_FORMAT = 'qc_volume:%s';
    const QC_VOLUME_TOTAL = 'qc_volume_total';
    const TOP_LEG_COUNT = 5;

    private $monthStart;
    private $monthEnd;
    private $ordersTotalCached;
    private $timeExec = [];
    private $qcVolume = [];

    /**
     * RankCalculation constructor.
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
        set_time_limit(0);


        $this->calculateRank($this->startDate, $this->endDate);
    }

    /**
     * Calculate rank by both QV and QC
     *
     * @param $startDate
     * @param $endDate
     */
    public function calculateRank($startDate, $endDate)
    {
        Log::info('Running ranks...', [get_called_class(), 'startDate' => $startDate, 'endDate' => $endDate]);
        $this->monthStart = $startDate;
        $this->monthEnd = $endDate;

        Log::info('calculateQVRank Calculation Started');
        $this->calculateQVRank($startDate, $endDate);
        Log::info('calculateQVRank is done.');


        $this->prepareJob();
        $this->calculateQCRank();
    }

    public function prepareJob()
    {
        Log::info('PrepareJob - Clearing Cache Storage...');
        Cache::store('memcached')->flush();

        Log::info('PrepareJob - Caching Orders Total...');
        $ordersTotals = DB::table('orders')
            ->selectRaw('userid, COALESCE(SUM(products.qc), 0) as qc_volume')
            ->leftJoin('orderItem', 'orders.id', '=', 'orderItem.orderid')
            ->leftJoin('products', 'products.id', '=', 'orderItem.productid')
            ->leftJoin('users', 'users.id', '=', 'orders.userid')
            //->where('users.current_month_rank', '>=', RankInterface::RANK_VALUE_EXECUTIVE)
            ->where(function ($query) {
                $query->where(function ($query) {
                    $query
                        ->whereDate('orderItem.created_dt', '>=', $this->monthStart)
                        ->whereDate('orderItem.created_dt', '<=', $this->monthEnd);
                })
                ->orWhere(function ($query) {
                    $query
                        ->whereDate('orderItem.created_dt', '>=', date('Y-m-d', strtotime("-1 Year")))
                        ->whereIn('products.id', [
                            Product::ID_PREMIUM_FIRST_CLASS,
                            Product::ID_FIRST_TO_PREMIUM,
                            Product::ID_UPG_COACH_TO_PREMIUM_FC,
                            Product::ID_UPG_STANDBY_TO_PREMIUM_FC,
                            Product::ID_UPG_BUSINESS_TO_PREMIUM_FC,
                        ]);
                });
            })
            ->groupby('userid')
            ->get();
        foreach ($ordersTotals as $ordersTotal) {
            $expiresAt = Carbon::now()->addMinutes(120);

            //Log::info("PrepareJob - Saving key orders_total_$ordersTotal->userid with total $ordersTotal->qc_volume");
            Cache::store('memcached')->put("orders_total_$ordersTotal->userid", $ordersTotal->qc_volume, $expiresAt);

            //$this->ordersTotalCached[$ordersTotal->userid] = $ordersTotal->qc_volume;
            ////Log::info(Cache::store('memcached')->get("orders_total_$ordersTotal->userid"));
        }


        Log::info('PrepareJob - Caching Active Users');

            // get the range with UTC timezone because the DB works using it as the default timezone
            $monthAgo = Carbon::now('UTC')->subDays(30)->format('Y-m-d');
            // for premium FC activate for 12 months from enrollment date
            $yearAgo = date('Y-m-d', strtotime("-1 Year"));
            //now
            $now = Carbon::now('UTC');

            $activeUsers = DB::select("
                select u.id,
                u.distid,
                (CASE WHEN count(u.id) > 0 THEN true ELSE false end) as isactive
                from users u
                where (
                    u.id in (select userid from orders where date(created_dt) >= :monthAgo group by userid having sum(orderqv) >= :minPqvValue)
                    or (u.current_product_id = :idPremiumFirstClass and u.created_dt > :yearAgo)
                )
                and u.account_status not in ('TERMINATED', 'SUSPENDED')
                group by u.id;",
                [
                    'monthAgo' => $monthAgo,
                    'yearAgo' => $yearAgo,
                    'minPqvValue' => User::MIN_QV_MONTH_VALUE,
                    'idPremiumFirstClass' => Product::ID_PREMIUM_FIRST_CLASS,
                ]
            );

            $alwaysActiveUsers = [
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
            'TSA8715163',
            'TSA3516402',
            'TSA8192292',
            'TSA9856404'
        ];

            foreach ($activeUsers as $user) {

            $expiresAt = Carbon::now()->addMinutes(120);

            if (in_array($user->distid, $alwaysActiveUsers)) {
                $user->isactive = true;
            }

            //Log::info("PrepareJob - Saving key user_active_$user->id with value $user->isactive");
            Cache::store('memcached')->put("user_active_$user->id", $user->isactive, $expiresAt);
        }

        Log::info('PrepareJob - Finished');
    }

    /**
     * Calculate rank by QV
     *
     * @param $startDate
     * @param $endDate
     */
    public function calculateQVRank($startDate, $endDate)
    {
        //$this->calculateTimeStart('calculateQVRank');
        UserRankHistory::calculateDownlineQV($startDate, $endDate);
        //$this->calculateTimeEnd('calculateQVRank');
    }

    public function getCurrentActiveStatus($userId)
    {
        $cachedActive = Cache::store('memcached')->get("user_active_$userId");
        if($cachedActive !== null) {
            return $cachedActive;
        }else{
            return false;
        }
    }

    /**
     * Calculate rank by QC
     */
    public function calculateQCRank()
    {
        ////Log::info('Clear QC Values(Processing)');
        // $this->clearQCValues();
        ////Log::info('Clear QC Values(Done)');

        $numUsers = User::where('current_month_rank', '>=', RankInterface::RANK_VALUE_EXECUTIVE)->count();
        $numBatches = ceil($numUsers / static::BATCH_SIZE); // 2
        $showEveryThisAmount = ceil(static::BATCH_SIZE / 10); // 10

        Log::info('Calculating QC rank...', [get_called_class(), 'numBatches' => $numBatches]);

        if ($numBatches == 0) {
            Log::info('Nothing to do. Exiting.', [get_called_class()]);
        }

        User::where('current_month_rank', '>=', RankInterface::RANK_VALUE_EXECUTIVE)
            ->orderBy('level', 'DESC')
            ->chunk(static::BATCH_SIZE, function ($users, $page) use ($numBatches, $numUsers, $showEveryThisAmount) {
                //$this->calculateTimeStart('userloop');
                $counter = 0;

                foreach ($users as $user) {
                    $counter++;

                    if ($counter == 1 || $counter == $numUsers || ($counter % $showEveryThisAmount) == 0) {
                        $userProgress = $counter . '/' . $numUsers;
                        Log::info('Processing user...', [get_called_class(), 'user' => $userProgress]);
                        //Log::info('time', [get_called_class(), 'time' => $this->timeExec]);
                    }

                    /** @var User $user */

                    // 4.5 segs
                    //$this->calculateTimeStart('currentActiveStatus');
                    $activeStatus = $this->getCurrentActiveStatus($user->id);
                    //$this->calculateTimeEnd('currentActiveStatus');

                    if ($activeStatus) {
                        $this->qcVolume = [];
                        $total = $this->getOrdersTotal($user->id);

                        //Log::info('getOrdersTotal'.$counter. ':' . $total);

                        $this->qcVolume[$user->distid] =  $total > 1 ? 1 : $total;

                        $users = User::where('sponsorid', $user->distid)
                            ->get();


                        //$this->calculateTimeStart('foreachUserList');
                        foreach ($users as $subtreeUser) {
                            $this->qcVolume[$subtreeUser->distid] = 0;

                            $this->getOrCalculateSubtreeTotal($subtreeUser);
                        }
                        //$this->calculateTimeEnd('foreachUserList');

                        $this->saveCurrentQCVolume($user);

                        $nextRank = DB::table('rank_definition')
                            ->where('rankval', '>', $user->current_month_rank)
                            ->orderBy('rankval', 'asc')
                            ->first();

                        if ($nextRank) {
                            $this->checkRank($nextRank, $user);
                        }
                    }
                    //$this->calculateTimeEnd('userloop');

                }
            });
    }

    /**
     * @param $userDistId
     */
    private function calculateQCVolume($userDistId)
    {
        //$this->calculateTimeStart('calculateQCVolume');
        if (!$userDistId) {
            return;
        }

        //$this->calculateTimeStart('varUsersQCVolume');
        $users = User::where('sponsorid', $userDistId)
            ->get();
        //$this->calculateTimeEnd('varUsersQCVolume');

        //$this->calculateTimeStart('foreachQCVolume');
        foreach ($users as $user) {
            //$this->calculateTimeStart('ifQCVolume');

            $subtreeTotal = Cache::store('memcached')->get(sprintf(self::QC_KEY_FORMAT, $user->distid));

            if ($subtreeTotal) {
                //Log::info('SubtreeTotal'.$user->id.': ' . $subtreeTotal);
                $this->qcVolume[$user->distid] = $subtreeTotal;

                //$this->calculateTimeEnd('ifQCVolume');
            } else {
                //$this->calculateTimeStart('elseQCVolume');
                $this->addQCFromLeg($user, $userDistId);
                //$this->calculateTimeEnd('elseQCVolume');
            }
        }
        //$this->calculateTimeEnd('foreachQCVolume');

        //$this->calculateTimeEnd('calculateQCVolume');
    }

    /**
     * @param User $user
     * @param $legId
     */
    private function addQCFromLeg($user, $legId)
    {
        //$this->calculateTimeStart('addQCFromLeg');

        //$this->calculateTimeStart('varTotalAddQCFromLeg');
        $total = $this->getCurrentActiveStatus($user->id) ? $this->getOrdersTotal($user->id) : 0;
        //$this->calculateTimeEnd('varTotalAddQCFromLeg');

        //$this->calculateTimeStart('arrQCVolumeAddQCFromLeg');
        $this->qcVolume[$legId] += $total > 1 ? 1 : $total;
        //$this->calculateTimeStart('arrQCVolumeAddQCFromLeg');
        // //Log::info('getOrdersTotal: '. $this->getOrdersTotal($user->id));

        if (!$user->distid) {
            return;
        }

        //$this->calculateTimeStart('varUsersAddQCFromLeg');
        $users = User::where('sponsorid', $user->distid)
            ->get();
        //$this->calculateTimeEnd('varUsersAddQCFromLeg');

        unset($user);
        gc_collect_cycles();

        //$this->calculateTimeStart('foreachAddQCFromLeg');
        foreach ($users as $userItem) {
            if ($subtreeTotal = Cache::store('memcached')->get(sprintf(self::QC_KEY_FORMAT, $userItem->distid))) {
                $this->qcVolume[$userItem->distid] = $subtreeTotal;
            } else {
                $this->addQCFromLeg($userItem, $legId);
            }
        }
        //$this->calculateTimeEnd('foreachAddQCFromLeg');


        //$this->calculateTimeEnd('addQCFromLeg');
    }

    private function calculateTimeStart($variable){
        if(!isset($this->timeExec[$variable])){
            $this->timeExec[$variable]['startTime'] = 0;
            $this->timeExec[$variable]['endTime'] = 0;
            $this->timeExec[$variable]['execCount'] = 0;
            $this->timeExec[$variable]['time'] = 0;
            $this->timeExec[$variable]['totalTimeSpended'] = 0;
        }

        $this->timeExec[$variable]['startTime'] = microtime(true);
    }

    private function calculateTimeEnd($variable){
        $time_exec_total = microtime(true);
        $finished = microtime(true);
        $time = $finished - $this->timeExec[$variable]['startTime'];

        $this->timeExec[$variable]['time'] = $time;
        $this->timeExec[$variable]['execCount'] ++;
        $this->timeExec[$variable]['endTime'] = $finished;
        $this->timeExec[$variable]['totalTimeSpended'] += $time;
    }


        /**
     * @param $userId
     * @return \Illuminate\Support\Collection
     */
    private function getOrdersTotalOld2($userId)
    {
        //$this->calculateTimeStart('getOrdersTotal');
        $result = $this->ordersTotalCached[$userId];
        //$this->calculateTimeEnd('getOrdersTotal');
        return $result;
    }

    /**
     * @param $userId
     * @return \Illuminate\Support\Collection
     */
    private function getOrdersTotal($userId)
    {
        //$this->calculateTimeStart('getOrdersTotal');
        $result = Cache::store('memcached')->get("orders_total_$userId");

        //$this->calculateTimeEnd('getOrdersTotal');
        return $result;
    }

    private function getOrdersTotalOld($userId)
    {
        //$this->calculateTimeStart('getOrdersTotal');
        $result =  DB::table('orders')
            ->selectRaw('COALESCE(SUM(products.qc), 0) as qc_volume')
            ->leftJoin('orderItem', 'orders.id', '=', 'orderItem.orderid')
            ->leftJoin('products', 'products.id', '=', 'orderItem.productid')
            ->where('userid', $userId)
            ->where(function ($query) {
                $query->where(function ($query) {
                    $query
                        ->whereDate('orderItem.created_dt', '>=', $this->monthStart)
                        ->whereDate('orderItem.created_dt', '<=', $this->monthEnd);
                })
                ->orWhere(function ($query) {
                    $query
                        ->whereDate('orderItem.created_dt', '>=', date('Y-m-d', strtotime("-1 Year")))
                        ->whereIn('products.id', [
                            Product::ID_PREMIUM_FIRST_CLASS,
                            Product::ID_FIRST_TO_PREMIUM,
                            Product::ID_UPG_COACH_TO_PREMIUM_FC,
                            Product::ID_UPG_STANDBY_TO_PREMIUM_FC,
                            Product::ID_UPG_BUSINESS_TO_PREMIUM_FC,
                        ]);
                });
            })
            ->value('qc_volume');

            //$this->calculateTimeEnd('getOrdersTotal');

            return $result;

        }

    /**
     * @param $rank
     * @param User $user
     */
    private function checkRank($rank, $user)
    {
        //$this->calculateTimeStart('checkRank');

        if (!$rank) {
            return;
        }

        $limit = $rank->min_qc * $rank->qc_percent;
        $tempTotal = $this->qcVolume;

        foreach ($tempTotal as $key => $value) {
            if ($value > $limit) {
                $tempTotal[$key] = $limit;
            }
        }

        if (array_sum($tempTotal) >= $rank->min_qc) {
            $binaryQualified = $user->getBinaryQualifiedValues();

            if ($binaryQualified['left'] < $rank->min_binary_count
                || $binaryQualified['right'] < $rank->min_binary_count) {
                return;
            }

            $this->addRankHistoryRow($rank, $user);

            $this->updateUserRankHistory($rank, $user, array_sum($tempTotal));

            DB::table('users')
                ->where('id', $user->id)
                ->update(
                    [
                        'current_month_rank' => $rank->rankval,
                    ]
                );

            $nextRank = DB::table('rank_definition')
                ->where('rankval', '>', $rank->rankval)
                ->orderBy('rankval', 'asc')
                ->first();

            $this->checkRank($nextRank, $user);
        }

        //$this->calculateTimeEnd('checkRank');
    }

    /**
     * @param $rank
     * @param $user
     */
    private function addRankHistoryRow($rank, $user)
    {
        //$this->calculateTimeStart('addRankHistoryRow');

        $rowCount = DB::table('rank_history')
            ->where('users_id', '=', $user->id)
            ->where('lifetime_rank', $rank->rankval)
            ->count();

        if ($rowCount < 1) {
            DB::table('rank_history')
                ->insert([
                    'users_id' => $user->id,
                    'lifetime_rank' => $rank->rankval,
                    'created_dt' => Carbon::now(),
                    'remarks' => 'QC',
                ]);
        }
        //$this->calculateTimeEnd('addRankHistoryRow');

    }

    /**
     * @param $rank
     * @param $user
     * @param $qcTotal
     */
    private function updateUserRankHistory($rank, $user, $qcTotal)
    {
        //$this->calculateTimeStart('updateUserRankHistory');

        $rowCount = DB::table('user_rank_history')
            ->where('user_id', '=', $user->id)
            ->whereDate('period', Carbon::now()->endOfMonth())
            ->count();
        if ($rowCount < 1) {
            DB::table('user_rank_history')
                ->insert([
                    'user_id' => $user->id,
                    'period' => Carbon::now()->endOfMonth(),
                    'monthly_rank' => $rank->rankval,
                    'monthly_rank_desc' => $rank->rankdesc,
                    'qualified_qc' => floor($qcTotal),
                ]) ;
        } else {
            DB::table('user_rank_history')
                ->where('user_id', $user->id)
                ->whereDate('period', Carbon::now()->endOfMonth())
                ->update([
                    'monthly_rank' => $rank->rankval,
                    'monthly_rank_desc' => $rank->rankdesc,
                    'qualified_qc' => floor($qcTotal),
                ]);
        }

        //$this->calculateTimeEnd('updateUserRankHistory');
    }

    /**
     * Reset previous values
     */
    // private function clearQCValues()
    // {
    //     DB::table('user_statistic')
    //         ->update([
    //             'current_month_qc' => null,
    //         ]);

    //     $this->clearRedisValues();
    // }

        /**
     * Delete all previous QC values
     */
    // private function clearRedisValues()
    // {
    //     foreach (Redis::keys(sprintf(self::QC_KEY_FORMAT, '*')) as $key) {
    //         Redis::del($key);
    //     }
    // }

    /**
     * @param User $subtreeRoot
     */
    private function getOrCalculateSubtreeTotal($subtreeRoot)
    {
        //$this->calculateTimeStart('getOrCalculateSubtreeTotal');

        //$this->calculateTimeStart('varCachedSubtreeTotal');
        $subtreeTotal = Cache::store('memcached')->get(sprintf(self::QC_KEY_FORMAT, $subtreeRoot->distid));
        //$this->calculateTimeEnd('varCachedSubtreeTotal');

        //$this->calculateTimeStart('ifSubtreeTotal');
        if ($subtreeTotal) {
            $this->qcVolume[$subtreeRoot->distid] = $subtreeTotal;
            //$this->calculateTimeEnd('ifSubtreeTotal');
        } else {
            //$this->calculateTimeStart('elseSubtreeTotal');
            //$this->calculateQCVolume($subtreeRoot->distid);
            //$this->calculateTimeEnd('elseSubtreeTotal');
        }

        //$this->calculateTimeStart('varRootTotal');
        $rootTotal = $this->getCurrentActiveStatus($subtreeRoot->id);
        //$this->calculateTimeEnd('varRootTotal');

        //$this->calculateTimeStart('ifRootTotal');
        if($rootTotal){
            $rootTotal = $this->getOrdersTotal($subtreeRoot->id);
            //$this->calculateTimeEnd('ifRootTotal');
        }else{
            //$this->calculateTimeStart('elseRootTotal');
            $rootTotal = 0;
            //$this->calculateTimeEnd('elseRootTotal');
        }


        $this->qcVolume[$subtreeRoot->distid] += $rootTotal > 1 ? 1 : $rootTotal;

        //$this->calculateTimeEnd('getOrCalculateSubtreeTotal');

    }

    /**
     * @param $user
     */
    private function saveCurrentQCVolume($user)
    {
        //$this->calculateTimeStart('saveCurrentQCVolume');
        $statistics = UserStatistic::where('user_id', $user->id)
            ->first();

        if (!$statistics) {
            $statistics = new UserStatistic();

            $statistics->user_id = $user->id;
        }

        $statistics->current_month_qc = json_encode($this->qcVolume);
        $statistics->save();
        $expiresAt = Carbon::now()->addMinutes(120);

        Cache::store('memcached')->put(sprintf(self::QC_KEY_FORMAT, $user->distid), array_sum($this->qcVolume), $expiresAt);
        //$this->calculateTimeEnd('saveCurrentQCVolume');

        //Log::info('saveCurrentQCVolume'.$user->id.': ' . Cache::store('memcached')->get(sprintf(self::QC_KEY_FORMAT, $user->distid)));
    }


}

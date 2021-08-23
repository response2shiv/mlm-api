<?php

namespace App\Services;

use App\Facades\BinaryPlanManager;
use App\Models\UserStatistic;
use App\Product;
use App\RankInterface;
use App\User;
use App\UserRankHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Class AchievedRankService
 * @package App\Services
 */
class AchievedRankService
{
    const BATCH_SIZE = 100;
    const QC_KEY_FORMAT = 'qc_volume:%s';
    const TOP_LEG_COUNT = 5;

    private $monthStart;
    private $monthEnd;

    private $qcVolume = [];

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

        $this->calculateQVRank($startDate, $endDate);
        $this->calculateQCRank();
    }

    /**
     * Calculate rank by QV
     *
     * @param $startDate
     * @param $endDate
     */
    public function calculateQVRank($startDate, $endDate)
    {
        UserRankHistory::calculateDownlineQV($startDate, $endDate);
    }

    /**
     * Calculate rank by QC
     */
    public function calculateQCRank()
    {
        $this->clearQCValues();

        $numUsers = User::where('current_month_rank', '>=', RankInterface::RANK_VALUE_EXECUTIVE)->count();
        $numBatches = ceil($numUsers / static::BATCH_SIZE);
        $showEveryThisAmount = ceil(static::BATCH_SIZE / 10);

        Log::info('Calculating QC rank...', [get_called_class(), 'numBatches' => $numBatches]);

        if ($numBatches == 0) {
            Log::info('Nothing to do. Exiting.', [get_called_class()]);
        }

        User::where('current_month_rank', '>=', RankInterface::RANK_VALUE_EXECUTIVE)
            ->orderBy('level', 'DESC')
            ->chunk(static::BATCH_SIZE, function ($users, $page) use ($numBatches, $numUsers, $showEveryThisAmount) {

                $counter = 0;

                foreach ($users as $user) {
                    $counter++;

                    if ($counter == 1 || $counter == $numUsers || ($counter % $showEveryThisAmount) == 0) {
                        $userProgress = $counter . '/' . $numUsers;
                        Log::info('Processing user...', [get_called_class(), 'user' => $userProgress]);
                    }

                    /** @var User $user */
                    if ($user->getCurrentActiveStatus()) {
                        $this->qcVolume = [];
                        $total = $this->getOrdersTotal($user->id);
                        $this->qcVolume[$user->distid] =  $total > 1 ? 1 : $total;

                        $users = User::where('sponsorid', $user->distid)
                            ->get();

                        foreach ($users as $subtreeUser) {
                            $this->qcVolume[$subtreeUser->distid] = 0;

                            $this->getOrCalculateSubtreeTotal($subtreeUser);
                        }

                        $this->saveCurrentQCVolume($user);

//                        $nextRank = DB::table('rank_definition')
//                            ->where('rankval', '>', $user->current_month_rank)
//                            ->orderBy('rankval', 'asc')
//                            ->first();
//
//                        if ($nextRank) {
//                            $this->checkRank($nextRank, $user);
//                        }
                    }
                }
            });
    }

    /**
     * @param $userDistId
     */
    private function calculateQCVolume($userDistId)
    {
        if (!$userDistId) {
            return;
        }

        $users = User::where('sponsorid', $userDistId)
            ->get();

        foreach ($users as $user) {
            if ($subtreeTotal = Redis::get(sprintf(self::QC_KEY_FORMAT, $user->distid))) {
                $this->qcVolume[$user->distid] = $subtreeTotal;
            } else {
                $this->addQCFromLeg($user, $userDistId);
            }
        }
    }

    /**
     * @param User $user
     * @param $legId
     */
    private function addQCFromLeg($user, $legId)
    {
        $total = $user->getCurrentActiveStatus() ? $this->getOrdersTotal($user->id) : 0;
        $this->qcVolume[$legId] += $total > 1 ? 1 : $total;

        if (!$user->distid) {
            return;
        }

        $users = User::where('sponsorid', $user->distid)
            ->get();

        unset($user);
        gc_collect_cycles();

        foreach ($users as $userItem) {
            if ($subtreeTotal = Redis::get(sprintf(self::QC_KEY_FORMAT, $userItem->distid))) {
                $this->qcVolume[$userItem->distid] = $subtreeTotal;
            } else {
                $this->addQCFromLeg($userItem, $legId);
            }
        }
    }

    /**
     * @param $userId
     * @return \Illuminate\Support\Collection
     */
    private function getOrdersTotal($userId)
    {
        return DB::table('orders')
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
    }

    /**
     * @param $rank
     * @param User $user
     */
    private function checkRank($rank, $user)
    {
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
    }

    /**
     * @param $rank
     * @param $user
     */
    private function addRankHistoryRow($rank, $user)
    {
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
    }

    /**
     * @param $rank
     * @param $user
     * @param $qcTotal
     */
    private function updateUserRankHistory($rank, $user, $qcTotal)
    {
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
                ])
            ;
        }
    }

    /**
     * Reset previous values
     */
    private function clearQCValues()
    {
        DB::table('user_statistic')
            ->update([
                'current_month_qc' => null,
            ]);

        $this->clearRedisValues();
    }

    /**
     * Delete all previous QC values
     */
    private function clearRedisValues()
    {
        foreach (Redis::keys(sprintf(self::QC_KEY_FORMAT, '*')) as $key) {
            Redis::del($key);
        }
    }

    /**
     * @param User $subtreeRoot
     */
    private function getOrCalculateSubtreeTotal($subtreeRoot)
    {
        if ($subtreeTotal = Redis::get(sprintf(self::QC_KEY_FORMAT, $subtreeRoot->distid))) {
            $this->qcVolume[$subtreeRoot->distid] = $subtreeTotal;
        } else {
            $this->calculateQCVolume($subtreeRoot->distid);
        }

        $rootTotal = $subtreeRoot->getCurrentActiveStatus() ? $this->getOrdersTotal($subtreeRoot->id) : 0;
        $this->qcVolume[$subtreeRoot->distid] += $rootTotal > 1 ? 1 : $rootTotal;
    }

    /**
     * @param $user
     */
    private function saveCurrentQCVolume($user)
    {
        $statistics = UserStatistic::where('user_id', $user->id)
            ->first();

        if (!$statistics) {
            $statistics = new UserStatistic();

            $statistics->user_id = $user->id;
        }

        $statistics->current_month_qc = json_encode($this->qcVolume);
        $statistics->save();

        Redis::set(sprintf(self::QC_KEY_FORMAT, $user->distid), array_sum($this->qcVolume));
    }
}

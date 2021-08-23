<?php

namespace App\Services;

use App\Models\RankInterface;
use App\Models\UnilevelCommission;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Class DynamicCompressionCommission
 * @package App\Services
 */
abstract class DynamicCompressionCommissionService
{
    const LEVEL_COUNTS = 7;
    const ADMIN_DISTID = 'TSA0002566';
    const SUBSCRIPTION_ID = 3;

    const TABLE_NAME = '';
    const COMMISSION_TYPE = '';

    const LEVEL_BY_RANK = [
        RankInterface::RANK_AMBASSADOR => 3,
        RankInterface::RANK_DIRECTOR => 3,
        RankInterface::RANK_SENIOR_DIRECTOR => 3,
        RankInterface::RANK_EXECUTIVE => 3,
        RankInterface::RANK_SAPPHIRE_AMBASSADOR => 4,
        RankInterface::RANK_RUBY => 5,
        RankInterface::RANK_EMERALD => 6,
        RankInterface::RANK_DIAMOND => 7,
        RankInterface::RANK_BLUE_DIAMOND => 7,
        RankInterface::RANK_BLACK_DIAMOND => 7,
        RankInterface::RANK_PRESIDENTIAL_DIAMOND => 7,
        RankInterface::RANK_CROWN_DIAMOND => 7,
        RankInterface::RANK_DOUBLE_CROWN_DIAMOND => 7,
        RankInterface::RANK_TRIPLE_CROWN_DIAMOND => 7,
    ];

    const PERCENT_BY_LEVEL = [
        1 => 0.1,
        2 => 0.1,
        3 => 0.1,
        4 => 0.08,
        5 => 0.07,
        6 => 0.06,
        7 => 0.05,
    ];

    const BATCH_SIZE = 100;

    protected $fromDate;
    protected $toDate;
    /**
     * @var User|null
     */
    protected $currentUser;

    /**
     * @var array
     */
    protected $levels;

    /**
     * UnilevelCommission constructor.
     */
    public function __construct()
    {
        $this->fromDate = new \DateTime();
        $this->toDate = new \DateTime();
        $this->currentUser = null;
    }

    /**
     * @param $user
     * @return mixed
     */
    abstract public function isValidUser($user);

    /**
     * @param $fromDate
     * @param $toDate
     */
    public function calculateCommission($fromDate, $toDate)
    {
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;

        $this->clearCommissionResults();

        User::where('is_active', true)
            ->chunk(static::BATCH_SIZE, function ($users) {
                foreach ($users as $user) {
                    $this->calculateForUser($user);
                }

                unset($users);
                gc_collect_cycles();
            });
    }

    /**
     * @param User $user
     */
    protected function calculateForUser($user)
    {
        if (!$user) {
            return;
        }

        $this->currentUser = $user;
        $userLevel = $this->getLevelByRank($user->getCommissionRank($this->toDate, static::COMMISSION_TYPE));
        $this->getLevelsAmount($user);

        $this->payToUser($user, 1, $userLevel);

        if ($userLevel < static::LEVEL_COUNTS) {
            $this->payToSponsors($user->sponsorid, $userLevel + 1);
        }
    }

    /**
     * @param $sponsorId
     * @param $levelFrom
     */
    protected function payToSponsors($sponsorId, $levelFrom)
    {
        if ($levelFrom > static::LEVEL_COUNTS) {
            return;
        }

        /** @var User $sponsor */
        $sponsor = User::where('distid', $sponsorId)->first();

        if (!$sponsor) {
            //$this->payToRoot($levelFrom);
            return;
        }

        $sponsorLevel = $this->getLevelByRank($sponsor->getCommissionRank($this->toDate, static::COMMISSION_TYPE));

        if ($sponsorLevel >= $levelFrom && $this->isValidUser($sponsor)) {
            $this->payToUser($sponsor, $levelFrom, $sponsorLevel);
            $sponsorLevel++;
        }

        $sponsorId = $sponsor->sponsorid;

        unset($sponsor);
        gc_collect_cycles();

        if (!$sponsorId) {
            //$this->payToRoot($levelFrom);
            return;
        }

        $this->payToSponsors($sponsorId, $levelFrom > $sponsorLevel ? $levelFrom : $sponsorLevel);
    }

    /**
     * @param $levelFrom
     */
    protected function payToRoot($levelFrom)
    {
        $root = User::where('distid', static::ADMIN_DISTID)->first();
        $this->payToUser($root, $levelFrom, static::LEVEL_COUNTS);
    }

    /**
     * @param $user
     * @param $fromLevel
     * @param $toLevel
     */
    protected function payToUser($user, $fromLevel, $toLevel)
    {
        for ($i = $fromLevel; $i <= $toLevel; $i++) {
            if (isset($this->levels[$i]) && $orders = $this->levels[$i]) {
                foreach ($orders as $order) {
                    $this->addCommissionRow($user, $i, $order->id, $order->cv);
                }
            }
        }
    }

    /**
     * @param $userId
     * @param $dateFrom
     * @param $dateTo
     * @return \Illuminate\Support\Collection
     */
    protected function getOrderItems($userId, $dateFrom, $dateTo)
    {
        return DB::table('orders')
            ->select('orderItem.id', 'orderItem.cv')
            ->leftJoin('orderItem', 'orders.id', '=', 'orderItem.orderid')
            ->leftJoin('products', 'products.id', '=', 'orderItem.productid')
            ->where('userid', $userId)
            ->whereIn('products.producttype', [static::SUBSCRIPTION_ID])
            ->whereDate('orderItem.created_dt', '>=', $dateFrom)
            ->whereDate('orderItem.created_dt', '<=', $dateTo)
            ->get();
    }

    /**
     * @param $rank
     * @return int
     */
    protected function getLevelByRank($rank)
    {
        if (!$rank) {
            return 1;
        }

        return isset(static::LEVEL_BY_RANK[$rank]) ? static::LEVEL_BY_RANK[$rank] : 1;
    }

    /**
     * @param $user
     * @return array
     */
    protected function getLevelsAmount($user)
    {
        for ($i = 1; $i <= self::LEVEL_COUNTS; $i++) {
            $this->levels[$i] = collect();
        }

        $this->fillLevel(1, $user);
    }

    /**
     * @param $level
     * @param $rootUser
     */
    protected function fillLevel($level, $rootUser)
    {
        if ($level > static::LEVEL_COUNTS) {
            return;
        }

        $users = DB::table('users')
            ->select('id', 'distid')
            ->where('sponsorid', $rootUser->distid)
            ->get();

        foreach ($users as $user) {
            if ($items = $this->getOrderItems($user->id, $this->fromDate, $this->toDate)) {
                $this->levels[$level] = $this->levels[$level]->merge($items);

                $this->fillLevel($level + 1, $user);
            } else {
                $this->fillLevel($level, $user);
            }
        }
    }

    /**
     * @param User $user
     * @param $level
     * @param $orderId
     * @param $amount
     */
    protected function addCommissionRow($user, $level, $orderId, $amount)
    {
        DB::table(static::TABLE_NAME)->insert(
            [
                'user_id' => $user->id,
                'dist_id' => $this->currentUser->id,
                'level' => $level,
                'rank_id' => $user->getCommissionRank($this->toDate, static::COMMISSION_TYPE),
                'amount' => $amount * static::PERCENT_BY_LEVEL[$level],
                'percent' => static::PERCENT_BY_LEVEL[$level],
                'order_id' => $orderId,
                'calculation_date' => Carbon::today(),
                'start_date' => $this->fromDate,
                'end_date' => $this->toDate,
                'status' => UnilevelService::CALCULATED_STATUS,
            ]
        );
    }

    protected function clearCommissionResults()
    {
        DB::table(static::TABLE_NAME)
            ->whereDate('start_date', '>=', $this->fromDate)
            ->whereDate('start_date', '<=', $this->toDate)
            ->where('status', UnilevelService::CALCULATED_STATUS)
            ->delete();

        DB::table(static::TABLE_NAME)
            ->whereDate('end_date', '>=', $this->fromDate)
            ->whereDate('end_date', '<=', $this->toDate)
            ->where('status', UnilevelService::CALCULATED_STATUS)
            ->delete();
    }
}

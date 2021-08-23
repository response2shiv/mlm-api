<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\RankInterface;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Class UnilevelCommission
 * @package App\Services
 */
class UnilevelService
{
    const LEVEL_COUNTS = 7;
    const ADMIN_DISTID = 'TSA0002566';
    const TABLE_NAME = 'unilevel_commission';
    const COMMISSION_TYPE = 'UC';
    const SUBSCRIPTION_ID = 3;

    const CALCULATED_STATUS = 'calculated';
    const POSTED_STATUS = 'posted';
    const PAID_STATUS = 'paid';

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

    const BATCH_SIZE = 1000;

    protected $fromDate;
    protected $toDate;

    /**
     * @var OrderItem|null
     */
    protected $currentOrder;
    protected $checkLevelCount = 0;

    /**
     * UnilevelCommission constructor.
     */
    public function __construct()
    {
        $this->fromDate = new \DateTime();
        $this->toDate = new \DateTime();
        $this->currentOrder = null;
    }

    /**
     * @param $fromDate
     * @param $toDate
     */
    public function calculateCommission($fromDate, $toDate)
    {
        Log::info('Starting commission...', [get_called_class(), 'fromDate' => $fromDate, 'toDate' => $toDate]);

        $this->fromDate = $fromDate;
        $this->toDate = $toDate;

        $this->clearCommissionResults();

        $query = DB::table('orders')
            ->select('orderItem.id', 'orderItem.cv', 'orders.userid')
            ->leftJoin('orderItem', 'orders.id', '=', 'orderItem.orderid')
            ->leftJoin('products', 'products.id', '=', 'orderItem.productid')
            ->whereIn('products.producttype', [static::SUBSCRIPTION_ID])
            ->whereDate('orders.created_dt', '>=', $fromDate)
            ->whereDate('orders.created_dt', '<=', $toDate)
            ->orderBy('orders.created_dt');


        $numOrders = $query->count();
        $showEveryThisAmount = ceil(static::BATCH_SIZE / 10);

        $counter = 0;

        $query->chunk(static::BATCH_SIZE, function ($orders) use ($counter, $numOrders, $showEveryThisAmount) {
                foreach ($orders as $order) {
                    $counter++;

                    if ($counter == 1 || $counter == $numOrders || ($counter % $showEveryThisAmount == 0)) {
                        $progress = $counter . '/' . $numOrders;
                        Log::info('Processing users...', [get_called_class(), 'order' => $progress]);
                    }

                    $this->calculateForOrder($order);
                }
            });

        Log::info('Commission finished..', [get_called_class()]);
    }

    /**
     * @param $endDate
     * @return bool
     */
    public function isPaidCommission($endDate)
    {
        $qb = DB::table('unilevel_commission')
            ->select(['end_date'])
            ->where('status', self::PAID_STATUS)
            ->where('end_date', '=', $endDate)
            ->groupBy('end_date');

        return $qb->get()->isNotEmpty();
    }

    /**
     * @param $endDate
     * @return bool
     */
    public function isPostedCommission($endDate)
    {
        $qb = DB::table('unilevel_commission')
            ->select(['end_date'])
            ->where('status', self::POSTED_STATUS)
            ->where('end_date', '=', $endDate)
            ->groupBy('end_date');

        return $qb->get()->isNotEmpty();
    }

    /**
     * @param null $date
     * @return \Illuminate\Support\Collection
     */
    public function getUnpaidCommissions($date = null)
    {
        $qb = DB::table('unilevel_commission')
            ->select(['end_date'])
            ->where('status', '<>', UnilevelService::PAID_STATUS)
            ->groupBy('end_date');

        if ($date) {
            $qb->whereDate('end_date', $date);
        }

        return $qb->get();
    }

    /**
     * @param $date
     * @return int
     */
    public function getCommissionUsersCount($date)
    {
        return DB::table('unilevel_commission')
            ->distinct()
            ->whereDate('end_date', $date)
            ->count('user_id');
    }

    /**
     * @param $endDate
     * @return mixed
     */
    public function getPaidAmount($endDate)
    {
        return DB::table('unilevel_commission')
            ->where('end_date', '=', $endDate)
            ->sum('amount');
    }

    /**
     * @param $date
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|null|object
     */
    public function getMaxPayoutForCommission($date)
    {
        return DB::table('unilevel_commission')
            ->select([
                DB::raw('user_id'),
                DB::raw('SUM(amount) as total')
            ])
            ->where('end_date', '=', $date)
            ->where('end_date', '=', $date)
            ->groupBy('user_id')
            ->orderBy('total', 'desc')
            ->first();
    }

    /**
     * @param $endDate
     * @return mixed
     */
    public function getCommissionByDate($endDate)
    {
        return \App\Models\UnilevelCommission::where('end_date', '=', $endDate)
            ->first();
    }

    /**
     * @param $order
     */
    protected function calculateForOrder($order)
    {
        $this->currentOrder = $order;
        $user = User::where('id', $order->userid)->first();

        if (!$user) {
            return;
        }

        $sponsor = $user->sponsor;

        if (!$sponsor) {
            return;
        }

        $this->checkLevelCount = 0;
        $this->payFromOrder(1, $sponsor->id);
    }

    /**
     * @param $level
     * @param $userId
     */
    protected function payFromOrder($level, $userId)
    {
        if ($level > static::LEVEL_COUNTS) {
            return;
        }

        /** @var User $user */
        $user = User::where('id', $userId)->first();

        if (!$user) {
            return;
        }

        if ($user->isUserActive($this->toDate)) {
            $this->checkLevelCount++;

            if ($this->getLevelByRank($user->getCommissionRank($this->toDate, static::COMMISSION_TYPE)) >= $level) {
                $this->addCommissionRow($user, $level, $this->currentOrder->id, $this->currentOrder->cv);
                $level++;

                if ($this->checkLevelCount > self::LEVEL_COUNTS) {
                    $this->payFromOrder($level, $user->id);
                    return;
                }
            }
        }

        $sponsor = $user->sponsor;

        if (!$sponsor) {
            return;
        }

        $sponsorId = $sponsor->id;
        unset($user);
        unset($sponsor);
        gc_collect_cycles();

        $this->payFromOrder($level, $sponsorId);
    }

    /**
     * @param $level
     */
    protected function payToRoot($level)
    {
        $root = User::where('distid', static::ADMIN_DISTID)->first();
        $this->addCommissionRow($root, $level, $this->currentOrder->id, $this->currentOrder->cv);
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
                'dist_id' => $this->currentOrder->id,
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
        Log::info('Clearing previous commissions...', [get_called_class()]);

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

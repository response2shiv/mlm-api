<?php

namespace App\Services;

use App\Models\RankInterface;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
//use Sentry\ErrorHandler;

/**
 * Class LeadershipCommission
 * @package App\Services
 */
class LeadershipCommissionService extends DynamicCompressionCommissionService
{
    const BATCH_SIZE = 100;
    const LEVEL_COUNTS = 4;
    const TABLE_NAME = 'leadership_commission';
    const SAPPHIRE_RANK_VALUE = 50;
    const COMMISSION_TYPE = 'LC';

    const CALCULATED_STATUS = 'calculated';
    const POSTED_STATUS = 'posted';
    const PAID_STATUS = 'paid';

    const LEVEL_BY_RANK = [
        RankInterface::RANK_AMBASSADOR => 0,
        RankInterface::RANK_DIRECTOR => 0,
        RankInterface::RANK_SENIOR_DIRECTOR => 0,
        RankInterface::RANK_EXECUTIVE => 0,
        RankInterface::RANK_SAPPHIRE_AMBASSADOR => 1,
        RankInterface::RANK_RUBY => 2,
        RankInterface::RANK_EMERALD => 3,
        RankInterface::RANK_DIAMOND => 4,
        RankInterface::RANK_BLUE_DIAMOND => 4,
        RankInterface::RANK_BLACK_DIAMOND => 4,
        RankInterface::RANK_PRESIDENTIAL_DIAMOND => 4,
        RankInterface::RANK_CROWN_DIAMOND => 4,
        RankInterface::RANK_DOUBLE_CROWN_DIAMOND => 4,
        RankInterface::RANK_TRIPLE_CROWN_DIAMOND => 4,
    ];

    const PERCENT_BY_LEVEL = [
        1 => 0.02,
        2 => 0.03,
        3 => 0.04,
        4 => 0.05,
    ];

    /**
     * @param User $user
     * @return boolean
     */
    public function isValidUser($user)
    {
        return $user->isUserActive($this->toDate) && $user->getCommissionRank(
            $this->toDate,
            static::COMMISSION_TYPE
        ) >= RankInterface::RANK_SAPPHIRE_AMBASSADOR;
    }

    /**
     * @param $fromDate
     * @param $toDate
     */
    public function calculateCommission($fromDate, $toDate)
    {
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;

        Log::info('Starting commission', [get_called_class(), 'fromDate' => $fromDate, 'toDate' => $toDate]);

        DB::beginTransaction();

        try {
            $this->clearCommissionResults();

            $numUsers = User::with('activity')->count();
            $counter = 0;
            $showEveryThisAmount = ceil(static::BATCH_SIZE * 0.01);

            User::with('activity')
                ->chunk(static::BATCH_SIZE, function ($users) use ($numUsers, $counter, $showEveryThisAmount) {

                    /** @var User $user */
                    foreach ($users as $user) {
                        $counter++;

                        if ($counter % $showEveryThisAmount == 0 || $counter == 1 || $counter == $numUsers) {
                            $userProgress = $counter . '/' . $numUsers;
                            Log::info('Processing user...', [get_called_class(), 'progress' => $userProgress]);
                        }

                        if (!$user->activity()->where('created_at', $this->toDate)->count()) {
                            continue;
                        }

                        if ($this->isValidUser($user)) {
                            $this->calculateForUser($user);
                        }
                    }
                });

            Log::info('Commission completed. Committing result.', [get_called_class()]);
            DB::commit();
        } catch (\Exception $e) {
            Log::critical('Commission failed! Exception fired. Doing rollback. See Sentry for exception.', [get_called_class(), get_class($e)]);
            app('sentry')->captureException($e);
            DB::rollback();
        }
    }

    /**
     * @param $endDate
     * @return bool
     */
    public function isPaidCommission($endDate)
    {
        $qb = DB::table('leadership_commission')
            ->select(['end_date'])
            ->where('status', self::PAID_STATUS)
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
        $qb = DB::table('leadership_commission')
            ->select(['end_date'])
            ->where('status', '<>', self::PAID_STATUS)
            ->groupBy('end_date');

        if ($date) {
            $qb->where('end_date', '>=', $date);
        }

        return $qb->get();
    }

    /**
     * @param $endDate
     * @return mixed
     */
    public function getCommissionByDate($endDate)
    {
        return LeadershipCommissionService::where('end_date', '=', $endDate)
            ->first();
    }

    /**
     * @param $date
     * @return int
     */
    public function getCommissionUsersCount($date)
    {
        return DB::table('leadership_commission')
            ->distinct()
            ->where('end_date', '=', $date)
            ->count('user_id');
    }

    /**
     * @param $endDate
     * @return mixed
     */
    public function getPaidAmount($endDate)
    {
        return DB::table('leadership_commission')
            ->where('end_date', '=', $endDate)
            ->sum('amount');
    }

    /**
     * @param $date
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|null|object
     */
    public function getMaxPayoutForCommission($date)
    {
        return DB::table('leadership_commission')
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
     * @param $user
     * @return array|void
     */
    protected function getLevelsAmount($user)
    {
        for ($i = 1; $i <= self::LEVEL_COUNTS; $i++) {
            $this->levels[$i] = collect();
        }

        $this->fillLevel(0, $user);
    }

    /**
     * @param $level
     * @param $rootUser
     */
    protected function fillLevel($level, $rootUser)
    {
        if ($level > static::LEVEL_COUNTS || !$rootUser->distid) {
            return;
        }

        $users = User::where('sponsorid', $rootUser->distid)
            ->get();

        foreach ($users as $user) {
            if ($user->getCommissionRank($this->toDate, static::COMMISSION_TYPE) >= RankInterface::RANK_SAPPHIRE_AMBASSADOR) {
                $this->fillLevel($level + 1, $user);
            } else {
                if ($level > 0 &&($items = $this->getOrderItems($user->id, $this->fromDate, $this->toDate))) {
                        $this->levels[$level] = $this->levels[$level]->merge($items);
                }

                $this->fillLevel($level, $user);
            }
        }
    }
}

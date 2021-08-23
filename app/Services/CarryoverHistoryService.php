<?php


namespace App\Services;

use App\Models\BinaryCommissionCarryoverHistory;
use App\Models\BinaryCommissionHistory;

/**
 * Class CarryoverHistoryService
 * @package App\Services
 */
class CarryoverHistoryService
{
    /**
     * @param BinaryCommissionHistory $commissionHistory
     * @return mixed
     */
    public function getCountCarryoverHistoryByCommission(BinaryCommissionHistory $commissionHistory)
    {
        return BinaryCommissionCarryoverHistory::where('bc_history_id', $commissionHistory->id)->count();
    }

    /**
     * @param $user
     * @param $endDateOfCommission
     * @return array
     */
    public function getCarryoverForUserByCommission($user, $endDateOfCommission)
    {
        $commission = BinaryCommissionHistory::where('end_date', $endDateOfCommission)->first();

        if (!$commission) {
            return [
                'right' => 0,
                'left' => 0
            ];
        }

        $carryover = BinaryCommissionCarryoverHistory::where('bc_history_id', $commission->id)
            ->where('user_id', $user->id)
            ->first();

        return [
            'right' => $carryover ? $carryover->right_carryover : 0,
            'left' => $carryover ? $carryover->left_carryover : 0
        ];
    }
}

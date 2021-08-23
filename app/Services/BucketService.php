<?php

namespace App\Services;

use App\Helpers\Util;
use App\Models\Product;
use App\Models\User;
use App\Models\UserBucketVolume;
use App\Models\VolumeLog;
use Carbon\Carbon;

class BucketService
{


    protected static function getBucketVolumeOfWeek(Carbon $today, $userId)
    {

        $today = Carbon::parse($today);
        $week_no = $today->weekOfYear;

        $user_bucket_volume = UserBucketVolume::where('week_no', $week_no)->where('user_id', $userId)->first();


        if (!$user_bucket_volume) {

            return self::createBucketVolume($week_no, $userId);
        }

        return $user_bucket_volume;
    }


    protected static function createBucketVolume($week_no, $userId)
    {

        return UserBucketVolume::create([
            'week_no' => $week_no,
            'user_id' => $userId,
            'start_of_week' => now()->startOfWeek(),
            'end_of_week' => now()->endOfWeek(),
        ]);
    }


    public static function createNewUserBucketVolume($user, $bv = 0, $cv = 0, $qv = 0)
    {


        $userBucket = self::getBucketVolumeOfWeek(now(), $user->id);


        $userBucket->bv_a = 0;
        $userBucket->bv_b = 0;
        $userBucket->bv_c = 0;
        $userBucket->total_bv = 0;
        $userBucket->qv += $qv;
        $userBucket->cv += $cv;
        $userBucket->pv += $qv;
        $userBucket->pev += $qv;
        $userBucket->save();
    }

    public static function distributeDirectLineVolumes($user, $order)
    {
        $user_upper_levels = UserBucketVolume::getUsersInReverseTree($user->id);
        foreach ($user_upper_levels as $user) {
            $userBucket = BucketService::getBucketVolumeOfWeek(now(), $user->pid);

            $new_bv_a = $user->pbid == 1 ? $order->orderbv : 0;
            $new_bv_b = $user->pbid == 2 ? $order->orderbv : 0;
            $new_bv_c = $user->pbid == 3 ? $order->orderbv : 0;

            $userBucket->bv_a += $new_bv_a;
            $userBucket->bv_b += $new_bv_b;
            $userBucket->bv_c += $new_bv_c;
            $userBucket->total_bv = $userBucket->bv_a + $userBucket->bv_b + $userBucket->bv_c;

            $userBucket->save();

            VolumeLog::create([
                'user_id' => $user->id,
                'order_id' => $order->id,
                'date_distributed' => now(Util::USER_TIME_ZONE),
                'bv' => $order->orderbv,
                'qv' => $order->orderqv,
                'cv' => $order->ordercv,
                'bucket_id' => $user->pbid,
                'status' => 'PENDING',
                'week_no' => $userBucket->week_no,
                'adjustment' => false
            ]);
        }
    }

    public static function recalculatePEV($user_id)
    {

        $result = UserBucketVolume::getUsersInReverseTree($user_id->id);
        array_shift($result);


        foreach ($result as $user) {

            $users = UserBucketVolume::sponsorshipUsersTree($user->uid);
            $buckets = collect($users)->pluck('uid');

            $availables =  UserBucketVolume::whereIn('user_id', $buckets)->get();

            $total_pv = 0;
            foreach ($availables as $av) {
                $total_pv += $av->pv;
            }

            $volumeLog = VolumeLog::whereDate('date_distributed', date('Y-m-d'))
                                    ->where('user_id', $user->id)
                                    ->first();
                                    
            
            
            $bkt = BucketService::getBucketVolumeOfWeek(now(), $user->uid);

            $pev_before = $bkt->pev == null ? 0 : $bkt->pev;
            
            $volumeLog->pev = $total_pv - $pev_before;              
            $volumeLog->save();

            $bkt->pev = $total_pv;
            $bkt->save();
        }
    }
}

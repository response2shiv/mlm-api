<?php

namespace App\Http\Controllers\Affiliates;

use App\Helpers\Util;
use App\Http\Controllers\Controller;
use App\Jobs\DistributeVolumes;
use Illuminate\Http\Request;

use App\Models\LoungeQueue;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\UserBucketVolume;
use App\Services\BucketService;
use Auth;
use DB;

class BucketPlacementController extends Controller
{
    public function getUsers()
    {
        $user = \Auth::user();

        $users = User::whereHas('loungeQueue', function ($query) {
            $query->where('sponsor_id', \Auth::user()->id)
                ->where('is_assigned', false);
        })->get();

        return response()->json($users, 200);
    }

    public function searchUser(Request $request)
    {
        $usern = $request->usern;
        $userId = \Auth::user()->id;

        $query = DB::select(DB::raw("
            WITH RECURSIVE bucket_tree AS (
                SELECT
                    tid,
                    uid,
                    sid,
                    pid,
                    ptid,
                    stid,
                    pbid,
                    sbid
                FROM
                    bucket_tree_plan
                WHERE
                    uid = $userId
                UNION
                    SELECT
                        bt.tid,
                        bt.uid,
                        bt.sid,
                        bt.pid,
                        bt.ptid,
                        bt.stid,            
                        bt.pbid,
                        bt.sbid
                    FROM
                        bucket_tree_plan bt
                    INNER JOIN bucket_tree t ON t.tid = bt.ptid
            ) SELECT
                u.distid as distid,
                u.firstname,
                u.lastname,
                u.id,
                u.username as username
            FROM
                bucket_tree bt
            JOIN users AS u ON bt.uid = u.id
            ORDER BY bt.tid;
        "));

        $user = collect($query)
            ->filter(function ($user) use ($usern) {
                return $user->username == strtolower($usern) || $user->distid == strtoupper($usern);
            })
            ->first();

        if ($user) {
            $volumes = UserBucketVolume::getUserBucketVolumes($user->id);
        }

        $data = [
            'user' => $user,
            'volumes' => $volumes ?? null
        ];

        return response()->json($data);
    }

    public function setUserOnBucket(Request $request)
    {
        $sponsor_id = \Auth::user()->id;
        $placement_root = $request->placement_root;
        $placement_bucket = $request->bucket;

        try {
            foreach ($request->users as $key => $user) {
                $userId = $user['id'];

                $query = DB::select("
                    SELECT public.isbo_place_in_bp($userId,$sponsor_id,$placement_root,$placement_bucket);");

                $lounge = LoungeQueue::where('user_id', $userId)->first();
                $lounge->is_assigned = true;
                $lounge->placed_at = now(Util::UTC_TIME_ZONE);
                $lounge->save();

                $orders = Order::getNotDistributedOrders($userId);
                $user = User::find($userId);


                foreach ($orders as $order) {

                    DistributeVolumes::dispatch($user, $order);
                }
            }

            return response()->json($query, 200);
        } catch (\Exception $e) {
            return $e;
        }
    }
}

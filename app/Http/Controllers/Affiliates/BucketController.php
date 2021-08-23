<?php

namespace App\Http\Controllers\Affiliates;

use App\Helpers\Util;
use App\Http\Controllers\Controller;
use App\Models\UserBucketVolume;
use App\Services\BucketService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class BucketController extends Controller
{

    public function addVolume(Request $request)
    {
        $rules = [
            'user_id' => "required|exists:users,id",
            'cv' => "required",
            'bv' => "required",
            'qv' => "required"
        ];

        $validator = Validator::make(request()->only(array_keys($rules)), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()->all(),
                'data' => null
            ]);
        }


        $user = User::find($request->user_id);

        BucketService::distributeDirectLineVolumes($user, $request->bv, $request->cv, $request->qv);

        BucketService::recalculatePEV($user);

        return "done.";
    }

    public function getBucketUserVolumes($userId)
    {
        return UserBucketVolume::getUserBucketVolumes($userId);
    }
}

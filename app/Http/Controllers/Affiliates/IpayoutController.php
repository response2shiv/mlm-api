<?php

namespace App\Http\Controllers\Affiliates;

use Log;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\PayOutControl;
use Illuminate\Support\Facades\Auth;

class IpayoutController extends Controller
{
    
    
    //Create new account
    public function createPayout(Request $request){
        $payoutControl = new PayOutControl();

        $user = User::getById(Auth::user()->id);
        $response = $payoutControl->createUser($user);
        
        Log::info("Response",$response);
        if ($response['error'] == 1 && $response['msg'] != 'A user with this UserName already exists') {
            return response()->json(['error' => 1, 'msg' => $response['msg']]);
        } else if ($response['error'] == 1 && $response['msg'] == 'A user with this UserName already exists') {
            $payoutControl->addUser(Auth::user()->id, time());
        }
        
        $payout_user_ref = $payoutControl->getPayoutByUserId(Auth::user()->id);
        if (empty($payout_user_ref)) {
            return response()->json(['error' => 1, 'msg' => 'Payout account not setup to your account.']);
        }
        
        return response()->json(['error' => 0, 'msg' => 'Payout account created.']);
    }
    
    public function checkUsernamePayout(){
        $user = User::getById(Auth::user()->id);
        $payoutControl = new PayOutControl();
        return $this->payoutControl->checkUser($user);
    }
    
}

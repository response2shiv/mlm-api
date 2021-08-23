<?php

namespace App\Http\Controllers\Affiliates;

use Log;
use Storage;
use Authy\AuthyApi;
use App\Models\User;
use App\Models\Helper;
use GuzzleHttp\Client;
use App\Models\Country;
use App\Models\RankTracker;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use App\Models\EwalletTransaction;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\PayOutControlService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;


class EwalletTransactionController extends Controller
{
    public $payoutControl;

    public function index() {
        $d = array();
        
        // @TODO add try
        $payoutControl = $this->getPayoutControl();
        $payoutType = $payoutControl->getType();
        $user = User::getById(Auth::user()->id);
        $response  = $payoutControl->createUser($user);
        
        $d['fee'] = EwalletTransaction::TRANSACTION_FEE;
        $d['error_address'] = false;
        if (empty($payoutType)) {
            $d['payout_type'] = '';
            $d['error_address'] = true;
        } else {
            $d['payout_type'] = $payoutType;
        }
        $d['found1099']     = File::exists(storage_path('2019-1099/1099_'.Auth::user()->distid).'.pdf');
        $d['balance']       = Auth::user()->estimated_balance;
        $d['is_rank_running'] = RankTracker::isRankRunning();
        $d['trans']         = EwalletTransaction::getLatestTen(Auth::user()->id);
        $d['show_2fa_modal']= true;
        $d['error2fa']      = false;        
        session(['resent_2fa_count' => 0]);
        session(['failed_2fa_count' => 0]);

        $this->setResponse($d);
        $this->setResponseCode(200);
        return $this->showResponse();
    }

    public function getTransferHistory() {
        $query = DB::select('select *, (created_at::timestamp::date)AS date from ewallet_transactions WHERE user_id =' . Auth::user()->id . 'order by id desc');
        return DataTables::of($query)->toJson();
    }

    private function getPayoutControl(){
        $payoutControlService = new PayOutControlService;
        
        return $payoutControlService->getPayout();
        
    }

    public function transferToPayOut()
    {
        $payoutControl = $this->getPayoutControl();
        $req = request();
        
        //user country code
        $vali = $this->validateTransfer();
        if ($vali['valid'] == 0) {
            return response()->json(['error' => 1, 'msg' => $vali['msg']]);
        }
        // transfer to payout

        $payout_user_ref = $payoutControl->getPayoutByUserId(Auth::user()->id);
        if (empty($payout_user_ref)) {
            $user = User::getById(Auth::user()->id);
            $response = $payoutControl->createUser($user);
            if ($response['error'] == 1 && $response['msg'] != 'A user with this UserName already exists') {
                return response()->json(['error' => 1, 'msg' => $response['msg']]);
            } else if ($response['error'] == 1 && $response['msg'] == 'A user with this UserName already exists') {
                $this->payoutControl->addUser(Auth::user()->id, time());
            }
        }
        // Log::info("Payout request received ", array($req));
        $payout_user_ref = $payoutControl->getPayoutByUserId(Auth::user()->id);
        if (empty($payout_user_ref)) {
            return response()->json(['error' => 1, 'msg' => 'Account not setup to your account.']);
        }
        // Log::info("Payout request payout_user_ref ".$payout_user_ref);
        $response = $payoutControl->commission(Auth::user()->username, Auth::user()->id, $req->amount);
        if ($response['error'] == 1) {
            return response()->json(['error' => '1', 'msg' => "Error from Payout. " . $response['msg']]);
        } else {
            $tid = $response['TransactionRefID'];
            $remarks = "payout transaction ID : " . $tid;
            EwalletTransaction::addNewWithdraw(Auth::user()->id, $req->amount, Auth::user()->estimated_balance, null, $remarks);
            return response()->json(['error' => '0', 'url' => 'reload']);
        }
    }
    
    private function validateTransfer() {
        $req = request();
        $validator = Validator::make($req->all(), [
            'amount' => 'required|numeric',
        ], [
            'amount.required' => 'Amount to be transferred is required',
            'amount.numeric' => 'Amount must be numeric',
        ]);

        $msg = "";
        if ($validator->fails()) {
            $valid = 0;
            $messages = $validator->messages();
            foreach ($messages->all() as $m) {
                $msg .= "<div> - " . $m . "</div>";
            }
        } else {
            $valid = 1;
            //
            if ($req->amount < 0) {
                $valid = 0;
                $msg = "Invalid amount";
            }
            $balance = Auth::user()->estimated_balance;
            $availableBalace = $balance - EwalletTransaction::TRANSACTION_FEE;
            if ($availableBalace < 0) {
                $valid = 0;
                $msg = "Insufficient available balance";
            } else if ($availableBalace < $req->amount) {
                $valid = 0;
                $msg = "Amount to be transferred cannot be exceeded " . number_format(floor($availableBalace), 2);
            }
        }
        $res = array();
        $res["valid"] = $valid;
        $res["msg"] = $msg;
        return $res;
    }

    public function vitalsSubmit(Request $request)
    {
        $user = Auth::user();
        $authyApi = new AuthyApi(env('AUTHY_API_KEY'));
        $email = false;
        $sms = false;
        $authyUserId = 0;

        $country = Country::getCountryByCode($user->country_code);
        
        $phoneNumber = $this->getUserPhoneNumberFor2FA($user);

        if($request->sendType == 'email'){
            $authyApi = new AuthyApi(env('AUTHY_API_KEY'));

            $email = true;
            $userAuthy = $authyApi->registerUser($request->email, $request->phone, $request->countryCode);
            $authyUserId = $userAuthy->id();

            $response = $this->emailVerificationStart($authyUserId);

            $success = $response['sent'];

        }elseif($request->sendType == 'sms'){
            $response = $authyApi->phoneVerificationStart($request->phone, $request->countryCode, 'sms', 7);
            $sms = true;

            $success = $response->ok();   
        }else{
            $success = true;   
        }

        return [
            'success' => $success,
            'email'   => $email,
            'sms'   => $sms,
            'authyUserId' => $authyUserId,
            ];
    }

    private function getUserPhoneNumberFor2FA($user)
    {
        $phoneNumber = $user->mobilenumber;

        if (!$phoneNumber) {
            $phoneNumber = $user->phonenumber;
        }

        return $phoneNumber;
    }

    public function emailVerificationStart($authyUserId)
    {
        $authy_api = env('AUTHY_API_KEY');

        $sent = false;
        $msg = "";
        session(['authyUserId' => $authyUserId]);
        //testing
        if (!empty($authyUserId)) {
            $client = new Client();
            $response = $client->post('https://api.authy.com/protected/json/email/'.$authyUserId, [
                'headers' => [
                    'X-Authy-API-Key' => $authy_api
                ]
            ]);
            if ($response->getStatusCode() == 200) {
                $sent = true;
            } 
        }
        //
        $res = array();
        $res['sent'] = $sent;
        $res['msg'] = $msg;
        return $res;
    }

    public function submitTFA(Request $request)
    {
        $user = Auth::user();

        $verificationCode = request()->post('verification_code');

        $authyApi = new AuthyApi(env('AUTHY_API_KEY'));

        $phoneNumber = $this->getUserPhoneNumberFor2FA($user);

        $response = $authyApi->phoneVerificationCheck($request->phone, $request->countryCode, $verificationCode);

        $success = $response->ok();
        //To change the path to the pdf, change the url parameter of the next array
        if ($success) {
            return [
                'success' => $success, 
                'url' => '/commission-viewer/found1099/download',                
                'target_blank' => 1
            ];
        }

        return [
            'success' => $success, 
            'msg' => $response->message()
        ];
    }

    public function verifyEmailPDFToken($token, $authy_id) {
        $authy_api = new AuthyApi(env('AUTHY_API_KEY'));

        if (!empty($authy_id)) {
                $verification = $authy_api->verifyToken($authy_id, $token, array("force" => "true"));
                
                if ($verification->ok()) {
                    $data = [
                        'success' => true, 
                        'url' => '/commission-viewer/found1099/download',                
                        'target_blank' => 1
                    ];

                } else {
                    $data = [
                        'success' => false,
                        'msg' => $verification->errors()->message
                    ];
                }
        }

        return $data;
    }


    public function verifyEmailToken($token, $authy_id) {
        $authy_api = new AuthyApi(env('AUTHY_API_KEY'));

        if (!empty($authy_id)) {
                $verification = $authy_api->verifyToken($authy_id, $token, array("force" => "true"));
                
                if ($verification->ok()) {
                    $data = [
                        'success' => true,
                        'msg' => $verification->ok()->message
                    ];

                } else {
                    $data = [
                        'success' => false,
                        'msg' => $verification->errors()->message
                    ];
                }
        }

        return $data;
    }


}

<?php

namespace App\Http\Controllers\Affiliates;

use App\Http\Controllers\Controller;
use Auth;
use Authy\AuthyApi;
use GuzzleHttp\Client;

class UserTransferController extends Controller
{

    public function twoFactorAuthRequest()
    {
        //$user = User::find(21976);
        $user= Auth::user();
        $authyApi = new AuthyApi(env('AUTHY_API_KEY'));
        $response = $authyApi->phoneVerificationStart($user->phonenumber, '+1');
        $success = $response->ok();

        return ['success' => $success];
    }

    public function twoFactorAuthVerify()
    {
        //$user = \App\Models\User::find(21976);
        $user= Auth::user();
        $verificationCode = request()->post('verification_code');
        $authyApi = new AuthyApi(env('AUTHY_API_KEY'));
        $response = $authyApi->phoneVerificationCheck($user->phonenumber, '+1', $verificationCode);
       
        $success = $response->ok();

        return ['success' => $success];
    }


    public function twoFactorEmailAuthRequest()
    {
        $user= Auth::user();

            $authyApi = new AuthyApi(env('AUTHY_API_KEY'));
            $authy_api = env('AUTHY_API_KEY');

            $success = false;
            $userAuthy = $authyApi->registerUser($user->email, $user->phonenumber, $user->phone_country_code);
            $authyUserId = $userAuthy->id();

            if (!empty($authyUserId)) {
                $client = new Client();
                $response = $client->post('https://api.authy.com/protected/json/email/'.$authyUserId, [
                    'headers' => [
                        'X-Authy-API-Key' => $authy_api
                    ]
                ]);
                if ($response->getStatusCode() == 200) {
                    $success = true;
                }else{
                    $success = false;
                }
            }

        return [
            'success' => $success,
            'authyUserId' => $authyUserId
        ];
    }
}

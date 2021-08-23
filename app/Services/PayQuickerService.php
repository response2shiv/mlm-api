<?php

namespace App\Services;

use App\Models\User;
use App\Models\Helper;
use App\Models\Address;
use App\Models\PayQuicker;
use Http\Adapter\Guzzle6\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Database\Eloquent\Model;
use App\Contracts\PayOutControlContract;


class PayQuickerService implements PayOutControlContract{

    public $token;
    public $fundingAccountPublicId="6da98d9bf5f4442895f1e03262cabf1d";
    public $url = "https://identity.mypayquicker.com";
    public function __construct()
    {

        $_token_validation = "OWU4YTM4NDA0MDkwNGJkODk2NDkzMDRiZjdlN2JmYjcwZDNlYzI3MTUwYzY0NmZhYTI2YWMwNGVkNzcxYWQzYToxZWJiN2RkNjgwZjU0ODBmYjQxN2UwMzM0NDA4ZjliM2E3YjdhNGUwZTQwYTRiN2U4N2U2ODE0MmU2ODM2NzVi";
        $curl = curl_init();
        curl_setopt_array($curl, [
        CURLOPT_URL => "{$this->url}/core/connect/token",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => "grant_type=client_credentials&scope=api%20useraccount_balance%20useraccount_debit",
        CURLOPT_HTTPHEADER => [
            "authorization: Basic {$_token_validation}",
            "content-type: application/x-www-form-urlencoded"
        ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);
        
        if ($err) {
            throw new \Exception('Error to connect payquicker. #:' . $err);
        } else {
            $data = json_decode($response, true);
            if(isset($data['error']))
                throw new \Exception('Error to connect payquicker. #:' . $data['error']);

            $this->token = $data['access_token'];
        }
    }

    public function getType():string {
        return 'payquicker';
    }
    
    public function addUser($userId, $transactionRefId): Model
    {
        return PayQuicker::addUser($userId, $transactionRefId);
    }

    public function getPayoutByUserId($id): Model 
    {
        return PayQuicker::getPayoutByUserId($id);
    }

    public function checkUser(User $user): array
    {
        try {
            $payquicker_user = PayQuicker::getPayoutByUserId($user->id);
            if (!$payquicker_user) {
                //exception error
                return ['error' => 1, 'msg' => 'User not found'];
            } else {
                return ['error' => 0, 'data' => '', 'msg' => 'User already exists'];
                
            }
        } catch (\Exception $ex) {
            return ['error' => 1, 'msg' => $ex->getMessage()];
        }

    }

    public function commission(string $userName, int $userId, float $amount): array
    {
        
        $user = User::findOrfail($userId);
        $payquicker_user = PayQuicker::getPayoutByUserId($user->id);
        if(empty($payquicker_user->userCompanyAssignedUniqueKey)){
            $payquicker_user->userCompanyAssignedUniqueKey =  uniqid('payquicker_');
            $payquicker_user->save();
        }

        $payments["payments"][] = [
                "fundingAccountPublicId" => $this->fundingAccountPublicId,
                "monetary" => [
                    "amount" => $amount
                    ],
                "accountingId" => 'Transfer from ncrase wallet - ' . date('Y-m-d h:s:i') . '.',
                "userCompanyAssignedUniqueKey" => $payquicker_user->userCompanyAssignedUniqueKey,
                "userNotificationEmailAddress" => $user->email,
                "recipientUserLanguageCode" => "en-us",
                "issuePlasticCard" => false,
                "scheduleDate" => date("Y-m-d H:i:s")
        ];
        $response = $this->curl($payments, "/api/v1/companies/accounts/payments");

        
        if (isset($response->error)) {
            //exception error
            return ['error' => 1, 'msg' => $response->error];
        } else {
            if (isset($response->IsError) && $response->IsError == 1) {
                //error
                return ['error' => 1, 'response' => $response];
            } else {
                if (isset($response->response) && $response->response->m_Text != 'OK') {
                    //error
                    return ['error' => 1, 'msg' => $response->response->m_Text];
                } else {
                    //success
                    $transactionRefId = $response[0]["payments"][0]["transactionPublicId"] ?? 0;
                    return ['error' => 0, 'msg' => 'Amount successfully transferred', 'TransactionRefID' => $transactionRefId];
                }

            }
        }
    }

    public function checkout(string $userName, int $userId, float $amount): array
    {

        $user = User::findOrfail($userId);
        $payquicker_user = PayQuicker::getPayoutByUserId($user->id);
        if (!empty($payquicker_user)) {
            $this->createUser($user);
            $payquicker_user = PayQuicker::getPayoutByUserId($user->id);
        }
        $params = 
            [
                "revenueAccountPublicId" => $this->fundingAccountPublicId,
                "userNotificationEmailAddress" => $user->email,
                "userCompanyAssignedUniqueKey" => $payquicker_user->userCompanyAssignedUniqueKey,
                "memoComment" => PayQuicker::ITEM_DESCRIPTION,
                "monetary" => [
                    "amount" => $amount
                ],
                "transactionMode" => "TransactionModeType_Committed"
            ];

        $response = $this->curl(
                    $params, 
                    "/api/v1/users/userCompany/{$payquicker_user->userCompanyAssignedUniqueKey}/accounts/action?debit"
                );

        if ($response['error'] == 1) {
            //error
            $logId = Helper::logApiRequests($userId, 'PayQuicker - checkout', '', $params);
            Helper::logApiResponse($logId->id, json_encode($response['response']));
            return ['error' => 1, 'response' => $response['response']];
        } else {
            //success
            return ['error' => 0, 'response' => (!empty($response['response']->arrItemsResponse) ? $response['response']->arrItemsResponse[0] : [])];
        }
    }

    public function createUser(User $user): array
    {
        try {
            $hasRec = PayQuicker::getPayoutByUserId($user->id);
            if (!empty($hasRec)) {
                return ['error' => 1, 'data' => $hasRec, 'msg' => 'PayQuicker account already setup'];
            }
            $primary_address = Address::getRec($user->id, Address::TYPE_BILLING, 1);
            $userUniquePayquickerId = uniqid('payquicker_');
            $params = [
                "fundingAccountPublicId" => $this->fundingAccountPublicId,
                "userNotificationEmailAddress" => $user->email,
                "userCompanyAssignedUniqueKey" => $userUniquePayquickerId,
                "notifyUser" => true,
                "firstName" => $user->firstname,
                "lastName" => $user->lastname,
                "recipientUserLanguageCode" => "en-us",
                "addresses" => [
                    [
                        "type" => "addressType_Residential",
                        "streetAddress1" => $primary_address->address1,
                        "streetAddress2" => $primary_address->address2,
                        "city" => $primary_address->city,
                        "region" => $primary_address->stateprov,
                        "postalCode" => $primary_address->postalcode,
                        "country" => $primary_address->countrycode
                    ]
                ]
            ];
            $response = $this->curl($params, "/api/v1/companies/users/invitations");

            $logId = Helper::logApiRequests($user->id, 'PayQuicker - add user', \Config::get('api_endpoints.eWalletAPIURL'), $params);
            if (isset($response->error)) {
                //exception error
                Helper::logApiResponse($logId->id, $response->msg);
                return ['error' => 1, 'msg' => $response->msg];
            } else {
                if (isset($response->IsError) && $response->IsError == 1) {
                    Helper::logApiResponse($logId->id, (isset($response->response->m_Text) ? $response->response->m_Text : ''));
                    return ['error' => 1, 'data' => [], 'msg' => (isset($response->response->m_Text) ? $response->response->m_Text : '')];
                } else {
                    if (isset($response->response) && $response->response->m_Code == 0) {
                        //success
                        
                        $rec = PayQuicker::addUser($user->id, $userUniquePayquickerId);
                        
                        Helper::logApiResponse($logId->id, 'PayQuicker account setup successfully -> '.$response);
                        return ['error' => 0, 'data' => $rec, 'msg' => 'PayQuicker account setup successfully'];
                    } else {
                        //error
                        Helper::logApiResponse($logId->id, (isset($response) ? $response : ''));
                        return ['error' => 1, 'msg' => $response];
                    }
                }
            }
        } catch (\Exception $ex) {
            if(isset($logId)){
                Helper::logApiResponse($logId->id, $ex->getMessage());
            }            
            return ['error' => 1, 'msg' => $ex->getMessage()];
        }

    }

    private function curl($requestPayload, $endpoint, $method="POST")
    {
        $request = json_encode($requestPayload);
        
        $curl = curl_init();
        curl_setopt_array($curl, [
        CURLOPT_URL => 'https://platform.mypayquicker.com'.$endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_POSTFIELDS => $request,
        CURLOPT_HTTPHEADER => [
            "accept: application/json; charset=utf-8",
            "authorization: Bearer {$this->token}",
            "content-type: application/json",
            "x-mypayquicker-version: 01-15-2018"
        ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);
        
        if ($err) {
            return json_decode(json_encode(['error' => 1, 'msg' => $err, 'test'=> 1]));;
        } else {
            return json_decode($response, true);
        }
        
        
    }
}
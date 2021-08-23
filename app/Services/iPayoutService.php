<?php

namespace App\Services;

use App\Models\User;
use App\Models\Helper;
use App\Models\Address;
use App\Models\IPayOut;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Database\Eloquent\Model;
use App\Contracts\PayOutControlContract;


class iPayoutService implements PayOutControlContract{


    public function getType():string {
        return 'ipayout';
    }
    
    public function addUser($userId, $transactionRefId): Model
    {
        return IPayOut::addUser($userId, $transactionRefId);
    }

    public function getPayoutByUserId($id): Model 
    {
        return IPayOut::getIPayoutByUserId($id);
    }

    public function checkUser(User $user): array
    {
        try {
            $primary_address = Address::getRec($user->id, Address::TYPE_BILLING, 1);
            $params = array(
                'fn' => 'eWallet_CheckIfUserNameExists',
                'UserName' => $user->username
            );
            $response = $this->curl($params);
            $logId = Helper::logApiRequests($user->id, 'IPayout - add user', '', $params);
            if (isset($response->error)) {
                //exception error
                return ['error' => 1, 'msg' => $response->msg];
            } else {
                if($response->response->m_Code === -2){
                    return ['error' => 0, 'data' => $response->response, 'msg' => $response->response->m_Text];
                }else{
                    return ['error' => 0, 'data' => $response->response, 'msg' => 'User already exists'];
                }
            }
        } catch (\Exception $ex) {
            return ['error' => 1, 'msg' => $ex->getMessage()];
        }

    }

    public function commission(string $userName, int $useId, float $amount): array
    {
        $accounts_to_load = array(
            array(
                'UserName' => $userName,
                'Amount' => $amount,
                'Comments' => 'commissions deposit',
                'MerchantReferenceID' => $useId . time(),
            )
        );
        $params = array(
            'fn' => 'eWallet_Load',
            'PartnerBatchID' => 'Transfer from ncrase wallet - ' . date('Y-m-d h:s:i') . '.',
            'PoolID' => '',
            'arrAccounts' => $accounts_to_load,
            'CurrencyCode' => 'USD',
	    'Autoload' => 'true'
        );
        $response = $this->curl($params);

        $logId = Helper::logApiRequests($useId, 'IPayout - commission', Config::get('api_endpoints.eWalletAPIURL'), $params);
        Helper::logApiResponse($logId->id, json_encode($response));
        // Log::info("Ipyout response to ",array($response));
        if (isset($response->error)) {
            //exception error
            return ['error' => 1, 'msg' => $response->msg];
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
                    return ['error' => 0, 'msg' => 'Amount successfully transferred', 'TransactionRefID' => $response->response->TransactionRefID];
                }

            }
        }
    }

    public function checkout(string $userName, int $useId, float $amount): array
    {
        $params = array(
            array(
                // regular payment
                'Amount' => $amount,
                'CurrencyCode' => 'USD',
                'ItemDescription' => IPayOut::ITEM_DESCRIPTION,
                'MerchantReferenceID' => $useId . "#" . time(),
                'UserReturnURL' => \config('api_endpoints.eWalletCheckoutThankYouPageURL'),
                'MustComplete' => 'true',
		//'Autoload' => 'true'
            )
        );
        $params = array(
            'fn' => 'eWallet_AddCheckoutItems',
            'UserName' => $userName,
            'arrItems' => $params,
            'CurrencyCode' => 'USD',
        );
        $response = $this->curl($params);
        if ($response['error'] == 1) {
            //error
            $logId = Helper::logApiRequests($useId, 'IPayout - checkout', '', $params);
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
            $hasRec = IPayOut::getIPayoutByUserId($user->id);
            if (!empty($hasRec)) {
                return ['error' => 1, 'data' => $hasRec, 'msg' => 'iPayout account already setup'];
            }
            $primary_address = Address::getRec($user->id, Address::TYPE_BILLING, 1);
            $params = array(
                'fn' => 'eWallet_RegisterUser',
                'UserName' => $user->username,
                'FirstName' => $user->firstname,
                'LastName' => $user->lastname,
                'CompanyName' => '',
                'Address1' => $primary_address->address1,
                'Address2' => $primary_address->address2,
                'City' => $primary_address->city,
                'State' => $primary_address->stateprov,
                'ZipCode' => $primary_address->postalcode,
                'Country2xFormat' => $primary_address->countrycode,
                'PhoneNumber' => $user->phonenumber,
                'CellPhoneNumber' => '',
                'EmailAddress' => $user->email,
                'SSN' => '',
                'CompanyTaxID' => '',
                'GovernmentID' => '',
                'MilitaryID' => '',
                'PassportNumber' => '',
                'DriversLicense' => '',
                'DateOfBirth' => '',
                'WebsitePassword' => '',
                'DefaultCurrency' => 'USD'
            );
            $response = $this->curl($params);
            $logId = Helper::logApiRequests($user->id, 'IPayout - add user', \Config::get('api_endpoints.eWalletAPIURL'), $params);
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
                        
                        $rec = IPayOut::addUser($user->id, $response->response->TransactionRefID);
                        
                        Helper::logApiResponse($logId->id, 'iPayout account setup successfully -> '.$response->response->TransactionRefID);
                        return ['error' => 0, 'data' => $rec, 'msg' => 'iPayout account setup successfully'];
                    } else {
                        //error
                        Helper::logApiResponse($logId->id, (isset($response->response->m_Text) ? $response->response->m_Text : ''));
                        return ['error' => 1, 'msg' => $response->response->m_Text];
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

    private function curl($requestPayload)
    {
        $requestPayload['MerchantGUID'] = Config::get('api_endpoints.MerchantGUID');
        $requestPayload['MerchantPassword'] = Config::get('api_endpoints.MerchantPassword');
        $request = json_encode($requestPayload);

        Log::info("Ipayout request to ".$requestPayload['MerchantGUID']);

        try {
            $ch = curl_init(Config::get('api_endpoints.eWalletAPIURL'));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $ips_response = curl_exec($ch);
            curl_close($ch);

            Log::info("Ipayout response",array(json_decode($ips_response)));

            return json_decode($ips_response);
        } catch (\Exception $ex) {
            return json_decode(json_encode(['error' => 1, 'msg' => $ex->getMessage(), 'test'=> 1]));
        }
    }
}
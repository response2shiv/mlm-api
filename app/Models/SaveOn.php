<?php

namespace App\Models;

use DB;
use Illuminate\Database\Eloquent\Model;
use App\Models\Helper;
use App\Models\Product;
use App\Models\User;
class SaveOn extends Model {

    public $timestamps = false;
    protected $table = 'sor_tokens';

    const USER_DISABLE_CHANGE_NOTE = 'The user has cancelled';
    const USER_TERMINATED_NOTE = 'The user has cancelled due to terminated account';
    const USER_SUSPENDED_NOTE = 'The user has suspended due to suspended account';
    const USER_DISABLE_FOR_SUBSCRIPTION_FAIL = 'The user fail to pay subscription fees';
    const USER_DISABLE_FOR_REFUND_SUBSCRIPTION = 'The user has cancelled due to refund subscription order';
    const USER_ENABLED_AFTER_SUCCESS_SUBSCRIPTION = 'The user has paid subscription fees';
    const USER_TRANSFER_AFTER_FAIL_SUBSCRIPTION = 'The user transfer to another club due to fail to pay subscriptions';
    const ACTIVE = 1;
    const DEACTIVE = 0;
    const USER_ALREADY_IN_INACTIVE_STATUS = 'User already in InActive status';
    const USER_ALREADY_IN_ACTIVE_STATUS = 'User already in Active status';
    const USER_DEACTIVATED_SUCCESSFULLY = 'User deactivated successfully';
    const USER_NOT_DEACTIVATED_SUCCESSFULLY = 'User not deactivated successfully';
    const USER_ACTIVATED_SUCCESSFULLY = 'User activated successfully';
    const USER_NOT_ACTIVATED_SUCCESSFULLY = 'User not activated successfully';
    const USER_ACCOUNT_NOT_FOUND = 'SOR account not found!';
    const USER_ACCOUNT_REST = 'RESET of SOR accounts';

    public static function getProductIdByClubId($clubId)
    {
        //prod on
        if ($clubId == 12744) {
            //standby
            return 1;
        } else if ($clubId == 12716) {
            //coach
            return 2;
        } else if ($clubId == 12718) {
            //business
            return 3;
        } else if ($clubId == 12719) {
            //first 4 or 16
            return "";
        } else if ($clubId == 12715) {
            //boom
            return "";
        }
        return "";
    }

    public static function searchSorUser($searchList, $currentProductId)
    {
        $endPoint = config('api_endpoints.SORGetMembers');
        $saveOnAPI = new \SOR($currentProductId);
        $postData = array(
            "MemberSearchList" => $searchList
        );
        try {
            $responseBody = $saveOnAPI->_post($endPoint, $postData, false);
        } catch (\Exception $exception) {
            $responseBody = (string)$exception->getResponse()->getBody(true);
        }
        return $responseBody;
    }
    public static function SSOLogin($currentProductId, $distid)
    {
        // get sso
        $endPoint = config('api_endpoints.SORGetLoginToken');
        $saveOnAPI = new \SOR($currentProductId);
        $postData = array(
            "ContractNumber" => $distid
        );
        try {
            $responseBody = $saveOnAPI->_post($endPoint, $postData, false);
        } catch (\Exception $exception) {
            $responseBody = (string)$exception->getResponse()->getBody(true);
        }
        if (strpos($responseBody, "LoginToken") !== false) {
            $token = str_replace("LoginToken:", "", $responseBody);
            $token = str_replace('"', '', $token);
            $url = 'https://members.igo4less.com/vacationclub/logincheck.aspx?token=' . $token;
            return ['error' => '0', 'url' => $url, 'target_blank' => 1];
        } else {
            return ['error' => '1', 'msg' => 'Error on iGo feature. Please contact us.<br/>Error: ' . $responseBody];
        }
    }

    public static function getMembersInformation($contractNumber) {
        $endPoint = config('api_endpoints.SORGetMemberInfo');
        $saveOnAPI = new \SOR(Product::ID_STANDBY_CLASS);
        $postData = array(
            "MemberSearchList" => array(
                array("ContractNumber" => $contractNumber)
            )
        );
        try {
            $responseBody = $saveOnAPI->_post($endPoint, $postData, false);
        } catch (\Exception $exception) {
            $responseBody = (string) $exception->getResponse()->getBody(true);
        }
        if ($responseBody == '"Access Denied"') {
            $msg = str_replace('"', '', $responseBody);
            return array('status' => 'error', 'msg' => $msg);
        } else if (empty(json_decode($responseBody, true))) {
            return array('status' => 'error', 'msg' => 'SOR User account not found');
        } else {
            return array('status' => 'success', 'response' => $responseBody);
        }
    }

    public static function enableUser($productId, $contractNumber, $note) {
        $endPoint = config('api_endpoints.SORActivateUser');
        $saveOnAPI = new \SOR($productId);
        $postData = array(
            "ContractNumber" => $contractNumber,
            "ChangeNote" => $note,
        );
        try {
            $responseBody = $saveOnAPI->_post($endPoint, $postData, false);
        } catch (\Exception $exception) {
            $responseBody = (string) $exception->getResponse()->getBody(true);
        }
        $user = User::getByDistId($contractNumber);
        $logId = Helper::logApiRequests($user->id, 'SOR - enable user', $endPoint, $postData);
        Helper::logApiResponse($logId->id, $responseBody);
        if ($responseBody != '"Member not found."') {
            $status = str_replace('"', '', $responseBody);

            return array('status' => 'success', 'enabled' => $status);
        } else {
            return array('status' => 'error', 'msg' => str_replace('"', '', $responseBody));
        }
    }

    public static function disableUser($productId, $contractNumber, $note) {
        $endPoint = config('api_endpoints.SORDeactivatedUser');
        $saveOnAPI = new \SOR($productId);
        $postData = array(
            "ContractNumber" => $contractNumber,
            "ChangeNote" => $note,
        );
        try {
            $responseBody = $saveOnAPI->_post($endPoint, $postData, false);
        } catch (\Exception $exception) {
            $responseBody = (string) $exception->getResponse()->getBody(true);
        }
        $user = User::getByDistId($contractNumber);
        $logId = Helper::logApiRequests($user->id, 'SOR - disable user', $endPoint, $postData);
        Helper::logApiResponse($logId->id, $responseBody);
        if ($responseBody != '"Unable to find user matching email or contract number or otherid"') {
            $status = str_replace('"', '', $responseBody);
            return array('status' => 'success', 'disabled' => $status);
        } else {
            return array('status' => 'error', 'msg' => str_replace('"', '', $responseBody));
        }
    }

    public static function SORUserToken($userId, $email, $password, $createCustomerResponse, $productId) {
        $endPoint = config('api_endpoints.SORGetLoginToken');
        $saveOnAPI = new \SOR($productId);
        $postData = array(
            "Email" => $email,
            "Password" => $password
        );
        $logId = Helper::logApiRequests($userId, 'SOR - user token', $endPoint, $postData);
        try {
            $responseBody = $saveOnAPI->_post($endPoint, $postData, false);
        } catch (\Exception $exception) {
            $responseBody = (string) $exception->getResponse()->getBody(true);
        }
        Helper::logApiResponse($logId->id, $responseBody);
        if ($responseBody != '"Member not found."') {
            $token = str_replace('"', '', $responseBody);

            return array('status' => 'success', 'token' => $token);
        } else {
            return array('status' => 'error', 'msg' => str_replace('"', '', $responseBody));
        }
    }

    public static function SORCreateUserWithToken($userId, $productId) {
        $user = User::find($userId);
        $endPoint = config('api_endpoints.SORCreateUser');
        $password = Helper::randomPassword();
        $postData = array(
            'Email' => $user->email,
            'Password' => $password,
            'FirstName' => $user->firstname,
            'LastName' => $user->lastname,
            'Address' => $user->userAddress->address1,
            'City' => $user->userAddress->city,
            'TwoLetterCountryCode' => $user->userAddress->countrycode,
            'Phone' => $user->phonenumber,
            'ContractNumber' => $user->distid,
            'UserAccountTypeID' => config('api_endpoints.UserAccountTypeID')
        );
        $logId = Helper::logApiRequests($userId, 'SOR - with token', $endPoint, $postData);
        //create user
        $saveOnAPI = new \SOR($productId);
        try {
            $jsonBody = $saveOnAPI->_post($endPoint, $postData, true);
        } catch (\Exception $exception) {
            $jsonBody = (string) $exception->getResponse()->getBody(true);
        }
        Helper::logApiResponse($logId->id, $jsonBody);
        $response = json_decode($jsonBody);
        if ($response->ResultType != 'error') {
            SaveOn::insert([
                'api_log' => $logId->id,
                'user_id' => $user->id,
                'product_id' => $productId,
                'sor_user_id' => $response->Account->UserId,
                'sor_password' => $password
            ]);
            return self::SORUserToken($user->id, $user->email, $password, $response, $productId);
        } else {
            return array('status' => 'error', 'msg' => $response->Message);
        }
    }

    public static function transfer($userId, $sorUserId, $transferToProductId) {
        $user = User::find($userId);
        $endPoint = config('api_endpoints.SORUserTransfer');
        $postData = array(
            'SORContractNumber' => $user->distid,
            'SORMemberID' => $sorUserId,
        );
        $logId = Helper::logApiRequests($userId, 'SOR - transfer', $endPoint, $postData);
        $saveOnAPI = new \SOR($transferToProductId);
        try {
            $jsonBody = $saveOnAPI->_postTransferRequest($endPoint, $postData, true);
        } catch (\Exception $exception) {
            $jsonBody = (string) $exception->getResponse()->getBody(true);
        }
        $response = json_decode($jsonBody);
        Helper::logApiResponse($logId->id, $jsonBody);

        return array('response' => $response, 'request' => $postData);
    }

    public static function SORCreateUser($userId, $productId, $userAddress) {
        $user = User::find($userId);
        $endPoint = config('api_endpoints.SORCreateUser');
        $password = Helper::randomPassword();
        $postData = array(
            'Email' => $user->email,
            'Password' => $password,
            'FirstName' => $user->firstname,
            'LastName' => $user->lastname,
            'Address' => (isset($userAddress->address1) ? $userAddress->address1 : ''),
            'City' => (isset($userAddress->city) ? $userAddress->city : ''),
            'TwoLetterCountryCode' => (isset($userAddress->countrycode) ? $userAddress->countrycode : ''),
            'Phone' => $user->phonenumber,
            'ContractNumber' => $user->distid,
            'UserAccountTypeID' => config('api_endpoints.UserAccountTypeID')
        );
        $logId = Helper::logApiRequests($user->id, 'SOR - create user', $endPoint, $postData);
        //create user
        $saveOnAPI = new \SOR($productId);
        try {
            $jsonBody = $saveOnAPI->_post($endPoint, $postData, true);
        } catch (\Exception $exception) {
            $jsonBody = (string) $exception->getResponse()->getBody(true);
        }
        Helper::logApiResponse($logId->id, $jsonBody);
        $response = json_decode($jsonBody);
        return array('response' => $response, 'request' => $postData);
    }

    /** FOR CUSTOMERS, START * */
    public static function SORCreateUserWithToken_customers($firstName, $lastName, $email, $mobile, $password) {
        $endPoint = config('api_endpoints.SORCreateUser');
        $postData = array(
            'Email' => $email,
            'Password' => $password,
            'FirstName' => $firstName,
            'LastName' => $lastName,
            'Phone' => $mobile,
            'UserAccountTypeID' => 5
        );
        $logId = Helper::logApiRequests(0, '', $endPoint, $postData);
        //create user
        try {
            $jsonBody = $saveOnAPI->_post($endPoint, $postData, true);
        } catch (\Exception $exception) {
            $jsonBody = (string) $exception->getResponse()->getBody(true);
        }
        Helper::logApiResponse($logId->id, $jsonBody);
        $response = json_decode($jsonBody);
        if (isset($response->Account) && isset($response->Account->UserId)) {
            SaveOn::insert([
                'api_log' => $logId->id,
                'user_id' => 0,
                'product_id' => 0,
                'sor_user_id' => $response->Account->UserId,
                'sor_password' => $password,
                'status' => 1
            ]);

            return array('status' => 'success');
        } else {
            return array('status' => 'error', 'msg' => $response->Message);
        }
    }

    /** FOR CUSTOMERS, END * */
    public static function getSORUserId($userId) {
        $rec = DB::table('sor_tokens')
                ->select('sor_user_id')
                ->where('user_id', $userId)
                ->first();
        if (empty($rec)) {
            return null;
        } else {
            return $rec->sor_user_id;
        }
    }

    public static function getSORUserInfo($userId) {
        $rec = DB::table('sor_tokens')
                ->select('sor_user_id', 'product_id', 'status')
                ->where('user_id', $userId)
                ->first();

        return $rec;
    }

    public static function getPackageName($productId) {
        if ($productId == 1) {
            // standby
            return "iGo4Less0";
        } else if ($productId == 2) {
            // coach
            return "iGo4less1";
        } else if ($productId == 3) {
            // businss
            return "iGo4less2";
        } else if ($productId == 4) {
            // first class
            return "iGo4less3";
        } 
    }

    public static function userExists($userId)
    {
        return static::select('*')->where('sor_user_id', $userId)->count() > 0;
    }

    public static function userExistsDist($distId)
    {
        $user = DB::table('users')
            ->select('id')
            ->where('distid', $distId)
            ->first();

        if (!$user) {
            return false;
        }

        return static::select('*')->where('user_id', $user->id)->count() > 0;
    }

    /**
     * Gets the user id for a user by their sor user id
     *
     * @param $sorUserId
     * @return int|null user id if found, otherwise null
     */
    public static function getInternalUserId($sorUserId)
    {
        $rec = DB::table('sor_tokens')
            ->select('user_id')
            ->where('sor_user_id', $sorUserId)
            ->first();

        return empty($rec) ? null : $rec->user_id;
    }

}

<?php

namespace App\Models;

use DB;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Database\Eloquent\Model;
use App\Helpers\IDecideInteractiveAPI;

use App\Models\UserActivityLog;
use App\Models\UserSettings;
use App\Models\GeoIP;
use Auth;

class IDecide extends Model {

    protected $table = 'idecide_users';
    public $timestamps = false;

    const DEACTIVE = 0;
    const ACTIVE = 1;


    const USER_ALREADY_IN_INACTIVE_STATUS = 'User already in InActive status';
    const USER_ALREADY_IN_ACTIVE_STATUS = 'User already in Active status';
    const USER_DEACTIVATED_SUCCESSFULLY = 'User deactivated successfully';
    const USER_NOT_DEACTIVATED_SUCCESSFULLY = 'User not deactivated successfully';
    const USER_ACTIVATED_SUCCESSFULLY = 'User activated successfully';
    const USER_ACCOUNT_NOT_FOUND = 'IDecide account not found!';
    const USER_NOT_ACTIVATED_SUCCESSFULLY = 'User not activated successfully';

    public static function SSOLogin($idecideUserRec)
    {
        $responseBody = IDecide::createIdecideSSOToken($idecideUserRec->user_id);
        $response = $responseBody['response'];

        if (!empty($response->token)) {
            return ['error' => '0', 'url' => $response->ssoUrl, 'target_blank' => 1];
        } else {
            return ['error' => '1', 'msg' => 'Error from iDecide. Please contact us'];
        }
    }

    public static function createAccount($userId, $logInfo)
    {
        $idecideUserId = IDecide::getIDecideUserId($userId);
        if ($idecideUserId > 0) {
            return ['error' => 1, 'msg' => 'iDecide account is already exist for this user'];
        }
        $idecideRes = IDecide::iDecideCreateUser($userId);
        $lastId = Helper::logApiRequests($userId, $logInfo, config('api_endpoints.iDecideCreateNewUser'), $idecideRes['request']);
        Helper::logApiResponse($lastId->id, json_encode($idecideRes['response']));
        $idecideResponse = $idecideRes['response'];
        if (empty($idecideResponse)) {
            return ['error' => 1, 'msg' => 'iDecide service not created. Please contact us.'];
        } else if (!empty($idecideResponse->errors)) {
            if (is_array($idecideResponse->errors)) {
                $msg = implode('<br>', $idecideResponse->errors);
            } else {
                $msg = $idecideResponse->errors;
            }
            return ['error' => 1, 'msg' => $msg];
        } else {
            $idecideRequest = $idecideRes['request'];
            iDecide::insert(['api_log' => $lastId->id, 'user_id' => $userId, 'idecide_user_id' => $idecideResponse->userId, 'password' => $idecideRequest['password'], 'login_url' => $idecideResponse->loginUrl, 'status' => 1]);
            return ['error' => 0, 'url' => 'reload'];
        }
    }
    public static function enableUser($userId) {
        $endPoint = config('api_endpoints.iDecideUserEnable');
        $idecideinteractiveAPI = new IDecideInteractiveAPI();
        
        try {
            $responseBody = $idecideinteractiveAPI->_post(array(), "users/" . $userId . "/" . $endPoint);
        } catch (\Exception $exception) {
            $responseBody = json_encode([]);
            if (!empty($exception->getResponse())) {
                $responseBody = (string)$exception->getResponse()->getBody(true);
            }
        }
        
        $postData = array(
            "users" => $userId
        );

        $logId = Helper::logApiRequests($userId, 'IDecide - enable user', $endPoint, $postData);
        Helper::logApiResponse($logId->id, $responseBody);

        return array('response' => json_decode($responseBody), 'request' => array());
    }

    public static function disableUser($userId) {
        $endPoint = config('api_endpoints.iDecideUserDisable');
        $idecideinteractiveAPI = new IDecideInteractiveAPI();
        try {
            $responseBody = $idecideinteractiveAPI->_post(array(), "users/" . $userId . "/" . $endPoint);
        } catch (RequestException $exception) {
            $responseBody = '{}';

            if ($exception->hasResponse()) {
                $responseBody = $exception->getResponse()->getBody();
            }
        }
         
        $postData = array(
            "users" => $userId
        );

        $logId = Helper::logApiRequests($userId, 'IDecide - enable user', $endPoint, $postData);
        Helper::logApiResponse($logId->id, $responseBody);

        return array('response' => json_decode($responseBody), 'request' => array());
    }

    public static function createIdecideSSOToken($userId) {
        $endPoint = config('api_endpoints.IDecideGenerateSSOToken');
        $postData = array();
        $idecideinteractiveAPI = new IDecideInteractiveAPI();
        
        $postData = array(
            "users/sso" => $userId
        );
        
        $logId = Helper::logApiRequests($userId, 'IDecide - create user sso token', $endPoint, $postData);
        
        try {
            $responseBody = $idecideinteractiveAPI->_post(array(), "users/sso/" . $userId . "/" . $endPoint);
        } catch (RequestException $exception) {
            $responseBody = json_encode([]);
            if ($exception->hasResponse()) {
                $responseBody = (string)$exception->getResponse()->getBody();
            }
        }
    
        Helper::logApiResponse($logId->id, $responseBody);
        
        return array('response' => json_decode($responseBody), 'request' => array());
    }

    public static function updateUserPassword($userId, $newPassword) {
        $postData = array('password' => $newPassword);
        $endPoint = config('api_endpoints.iDecideUpdatePassword');
        $idecideinteractiveAPI = new IDecideInteractiveAPI();
        //
        $logId = Helper::logApiRequests($userId, 'IDECIDE - change password', $endPoint, $postData);
        //
        try {
            $responseBody = $idecideinteractiveAPI->_post($postData, "users/" . $userId . "/" . $endPoint);
        } catch (\Exception $exception) {
            $responseBody = (string) $exception->getResponse()->getBody(true);
        }
        //
        Helper::logApiResponse($logId->id, $responseBody);

        $userActivityLog = new UserActivityLog;
        $row = UserSettings::getByUserId(Auth::user()->id);
        $response = GeoIP::getInformationFromIP($row->current_ip);
        $userActivityLog->ip_address = $row->current_ip;
        $userActivityLog->user_id = Auth::user()->id;
        $userActivityLog->ip_details = $response;
        $userActivityLog->action = "UPDATE user Email Password"; 
        $userActivityLog->old_data = "";
        $userActivityLog->new_data = json_encode($responseBody);
        $userActivityLog->save();

        //
        return array('response' => json_decode($responseBody), 'request' => $postData);
    }

    public static function updateUserEmailAddress($userId, $newEmailAddress) {
        $postData = array('emailAddress' => $newEmailAddress);
        $endPoint = config('api_endpoints.IDecideUpdateUserEmailAddress');
        $idecideinteractiveAPI = new IDecideInteractiveAPI();
        //
        $logId = Helper::logApiRequests($userId, 'IDECIDE - change email', $endPoint, $postData);
        //
        try {
            $responseBody = $idecideinteractiveAPI->_post($postData, "users/" . $userId . "/" . $endPoint);
        } catch (\Exception $exception) {
            $responseBody = (string) $exception->getResponse()->getBody(true);
        }
        Helper::logApiResponse($logId->id, $responseBody);


        $userActivityLog = new UserActivityLog;
        $row = UserSettings::getByUserId(Auth::user()->id);
        $response = GeoIP::getInformationFromIP($row->current_ip);
        $userActivityLog->ip_address = $row->current_ip;
        $userActivityLog->user_id = Auth::user()->id;
        $userActivityLog->ip_details = $response;
        $userActivityLog->action = "UPDATE user Email Address"; 
        $userActivityLog->old_data = "";
        $userActivityLog->new_data = json_encode($responseBody);
        $userActivityLog->save();
        //
        return array('response' => json_decode($responseBody), 'request' => $postData);
    }

    public static function iDecideCheckExistingUser($userId) {
        $user = User::find($userId);
        $postData = array('emailAddress' => $user->email);
        $endPoint = config('api_endpoints.iDecideCheckExistinUser');
        $logId = Helper::logApiRequests($user->id, 'IDECIDE', $endPoint, $postData);
        $idecideinteractiveAPI = new IDecideInteractiveAPI();
        try {
            $responseBody = $idecideinteractiveAPI->_post($postData, $endPoint);
        } catch (\Exception $exception) {
            $responseBody = (string) $exception->getResponse()->getBody(true);
        }
        Helper::logApiResponse($logId->id, $responseBody);
        $response = json_decode($responseBody);
        if (empty($response->errors)) {
            //error already exists
            return array('status' => 'error', 'msg' => 'User Already exists');
        } else {
            //user not exists
            $password = Helper::randomPassword();
            $postData = array(
                //                'emailAddress' => time() . $user->email,
                //                'emailAddress' => $user->email,
                'password' => $password,
                'firstName' => $user->firstname,
                'lastName' => $user->lastname
            );
            $endPoint = config('api_endpoints.iDecideCreateNewUser');
            $logId = Helper::logApiRequests($user->id, 'IDECIDE', $endPoint, $postData);
            $jsonBody = iDecide::iDecideCreateUser($postData);
            Helper::logApiResponse($logId->id, $jsonBody);
            $response = json_decode($jsonBody);
            if (!empty($response->errors)) {
                return array('status' => 'error', 'msg' => implode('<br>', $response->errors));
            } else {
                iDecide::insert(['api_log' => $logId->id,
                    'user_id' => $userId,
                    'idecide_user_id' => $response->userId,
                    'password' => $password,
                    'login_url' => $response->loginUrl
                ]);

                return array('status' => 'success');
            }
        }
    }

    //business number
    public static function updateIDecideUser($integrationId, $userName, $firstName, $lastName) {
        $postData = array(
            'businessNumber' => $userName,
            'firstName' => $firstName,
            'lastName' => $lastName
        );
        //user update
        $endPoint = "users/$integrationId/update";
        $idecideinteractiveAPI = new IDecideInteractiveAPI();
        try {
            $responseBody = $idecideinteractiveAPI->_post($postData, $endPoint);
        } catch (\Exception $exception) {
            $responseBody = (string) $exception->getResponse()->getBody(true);
        }

        return array('response' => json_decode($responseBody), 'request' => $postData);
    }

    public static function checkUserByEmail($email) {
        $postData = array(
            'emailAddress' => $email
        );
        $endPoint = config('api_endpoints.iDecideCheckExistinUser');
        $idecideinteractiveAPI = new IDecideInteractiveAPI();
        try {
            $responseBody = $idecideinteractiveAPI->_post($postData, $endPoint);
        } catch (\Exception $exception) {
            $responseBody = (string) $exception->getResponse()->getBody(true);
        }

        return array('response' => json_decode($responseBody), 'request' => $postData);
    }

    public static function iDecideCreateUser($userId) {
        $password = Helper::randomPassword();
        $userRec = User::find($userId);
        $postData = array(
            'emailAddress' => $userRec->email,
            'password' => $password,
            'phoneNumber' => $userRec->phonenumber,
            'firstName' => $userRec->firstname,
            'lastName' => $userRec->lastname,
            'integrationId' => $userId,
            'sendWelcomeEmail' => true,
            'businessNumber' => $userRec->username
        );
        //users/create
        $endPoint = config('api_endpoints.iDecideCreateNewUser');
        $idecideinteractiveAPI = new IDecideInteractiveAPI();
        try {
            $responseBody = $idecideinteractiveAPI->_post($postData, $endPoint);
        } catch (\Exception $exception) {
            $responseBody = (string) $exception->getResponse()->getBody(true);
        }

        return array('response' => json_decode($responseBody), 'request' => $postData);
    }

    public static function getIDecideUserId($userId) {
        $rec = DB::table('idecide_users')
            ->select('idecide_user_id', 'status')
                ->where('user_id', $userId)
                ->first();
        if (empty($rec)) {
            return 0;
        } else {
            return $rec->idecide_user_id;
        }
    }

    public static function getIDecideUserInfo($userId) {
        $rec = DB::table('idecide_users')
                ->select('idecide_user_id', 'password', 'status')
                ->where('user_id', $userId)
                ->first();
        return $rec;
    }

}

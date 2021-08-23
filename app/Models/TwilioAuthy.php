<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;

class TwilioAuthy extends Model {
    const API_KEY = "TY0BnRU8WK36Q43LMGp3CwulzesJ6Php";

    public static function register($email, $mobile, $countryCode) {
        $error = 0;
        $msg = "";
        $authyUserId = 0;
        //
        $authy_api = new \Authy\AuthyApi(self::API_KEY);
        $user = $authy_api->registerUser($email, $mobile, $countryCode);
        if ($user->ok()) {
            $authyUserId = $user->id();
        } else {
            $error = 1;
            foreach ($user->errors() as $field => $message) {
                $msg .= $message . "<br/>";
            }
        }
        //
        $res = array();
        $res['error'] = $error;
        $res['msg'] = $msg;
        $res['authy_id'] = $authyUserId;
        //
        return $res;
    }

    public static function sendToken($email = "") {
        $authy_api = new \Authy\AuthyApi(self::API_KEY);
        $sent = 0;
        $msg = "";
        // get authy id
        $authyInfo = User::getAuthyInfo($email);

        if (!empty($authyInfo)) {
            $sms = $authy_api->requestSms($authyInfo->authy_id, array("force" => "true"));
            if ($sms->ok()) {
                $sent = 1;
            } else {
                foreach ($sms->errors() as $field => $message) {
                    $msg .= $message;
                }
            }
        }
        //
        $res = array();
        $res['sent'] = $sent;
        $res['msg'] = $msg;
        return $res;
    }

    public static function verifyToken($email, $token) {
        $msg = "";
        $userId = 0;
        $verified = 0;
        //
        $authy_api = new \Authy\AuthyApi(self::API_KEY);
        // get authy id
        $authyInfo = User::getAuthyInfo($email);
        //
        if (!empty($authyInfo)) {
            try {
                $verification = $authy_api->verifyToken($authyInfo->authy_id, $token, array("force" => "true"));
                if ($verification->ok()) {
                    $verified = 1;
                    $userId = $authyInfo->id;
                } else {
                    $msg = "Invalid token";
                }
            } catch (\Exception $ex) {
                $msg = "Invalid token";
            }
        }
        //
        $res = array();
        $res['verified'] = $verified;
        $res['msg'] = $msg;
        $res['user_id'] = $userId;
        //
        return $res;
    }

}

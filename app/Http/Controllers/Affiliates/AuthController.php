<?php

namespace App\Http\Controllers\Affiliates;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetTokens;
use App\Models\UserRankHistory;
use App\Models\UserType;
use App\Models\User;
use App\Models\Order;
use App\Models\SaveOn;
use App\Models\Helper;
use App\Helpers\MyMail;
use App\Helpers\Util;
use App\Models\UserAuthSsoToken;
use App\Models\UserActivityLog;
use App\Models\GeoIP;
use App\Models\UserSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Auth;
use Log;

class AuthController extends Controller
{
    /**
     * Allow user to login and return the token
     *
     * @return \Illuminate\Http\Response
     */
    public function login()
    {
        $req        = request();
        $username   = $req->username;
        $password   = $req->password;
        $rememberMe = $req->remember == "on" ? true : false;
        $userType   = UserType::TYPE_DISTRIBUTOR;

        if (!Auth::attempt(array('username' => strtolower($username), 'password' => $password, 'usertype' => $userType), $rememberMe)) {
            if (!Auth::attempt(array('distid' => $username, 'password' => $password, 'usertype' => $userType), $rememberMe)) {

                $result = $this->attemptVibeLogin($username, $password, $userType, $rememberMe);

                if (!$result) {
                    // return response()->json(['error' => '1', 'msg' => "Invalid username or distributor ID or password"]);
                    $this->setResponseCode(401);
                    $this->setMessage("Invalid username or distributor ID or password");
                    return $this->showResponse();
                    exit();
                }
            }
        }
        if (Auth::user()->account_status == User::ACC_STATUS_PENDING_APPROVAL) {
            Auth::logout();
            $this->setResponseCode(401);
            $this->setMessage("Welcome to ncrease! Your order is still being processed, please try again later.");
            return $this->showResponse();
            exit();

        }
        if (Auth::user()->account_status == User::ACC_STATUS_PENDING) {
            Auth::logout();
            $this->setResponseCode(401);
            $this->setMessage("This account has restricted access.  Please contact customer service at support@ncrease.com");
            return $this->showResponse();
            exit();

        }
        if (Auth::user()->account_status == User::ACC_STATUS_SUSPENDED) {
//                session()->put(['suspended_user_id' => Auth::user()->id]);
//                Auth::logout();
//                return response()->json(['error' => '1', 'msg' => "Your account is suspended.<br/>Please contact us"]);
//                $v = (string)view('affiliate.user.account.suspend_account_reactivate');
//                return response()->json(['error' => 0, 'v' => $v]);
//                return response()->json(['error' => '1', 'msg' => "Your account is suspended. Please contact customer service at support@ncrease.com"]);
        }
        if (Auth::user()->account_status == User::ACC_STATUS_TERMINATED) {
            Helper::deActivateIdecideUser(Auth::user()->id);
            Helper::deActivateSaveOnUser(Auth::user()->id, Auth::user()->current_product_id, Auth::user()->distid, SaveOn::USER_TERMINATED_NOTE);
            Auth::logout();
            // return response()->json(['error' => '1', 'msg' => "This account has restricted access.  Please contact customer service at support@ncrease.com"]);
            $this->setResponseCode(401);
            $this->setMessage("This account has restricted access.  Please contact customer service at support@ncrease.com");
            return $this->showResponse();
            exit();
        }

        //If script got to this point it means the user is authenticated
        $response = [];
        $response['token'] = Auth::user()->createToken('ibuumerang')->accessToken;
        $response['user'] = Auth::user();

        $pv = Order::getThisMonthOrderQV(Auth::user()->id);
        $response['pv'] = $pv > 100 ? 100 : $pv;

        $current_rank_info = UserRankHistory::getCurrentMonthUserInfo(Auth::user()->id);
        if ($current_rank_info == null) {
            $achieved_rank_desc = strtoupper("Ambassador");
        } else {
            $achieved_rank_desc = strtoupper($current_rank_info->achieved_rank_desc);
        }

        $response['achieved_rank_desc'] = $achieved_rank_desc;

        $row = UserSettings::getByUserId(Auth::user()->id);
        $userActivityLog = new UserActivityLog;

        if (empty($row)){
            $newRow = new UserSettings;
            $newRow->user_id = Auth::user()->id;
            $newRow->current_ip = $req->current_ip;
            $newRow->save();
            $userActivityLog->old_data = "";
        }else{
            UserSettings::where('user_id', Auth::user()->id)->update(['current_ip'=> $req->current_ip]);
            $userActivityLog->old_data = $row->current_ip;
        }


        try{
            $responseIP = GeoIP::getInformationFromIP($req->current_ip);
            $userActivityLog->ip_address = $req->current_ip;
            $userActivityLog->ip_details = $responseIP;
            $userActivityLog->user_id = Auth::user()->id;
            $userActivityLog->action = "Login user";
            $userActivityLog->new_data = $req->current_ip;
            $userActivityLog->save();
        } catch (\Exception $ex) {
            Log::error("Failed to save GeoIP informtion on user login - User ".Auth::user()->id." / ".Auth::user()->distid);
        }

        $this->setResponse($response);
        $this->setResponseCode(200);
        return $this->showResponse();

        //return response()->json(['error' => '0', 'url' => URL('/')]);



        /*if (Auth::attempt(['username' => strtolower($username), 'password' => $password], $rememberMe)) {
            $response = [];
            $response['token'] = Auth::user()->createToken('ibuumerang')->accessToken;
            $response['user'] = Auth::user();

            $pv = Order::getThisMonthOrderQV(Auth::user()->id);
            $response['pv'] = $pv > 100 ? 100 : $pv;

            $current_rank_info = UserRankHistory::getCurrentMonthUserInfo(Auth::user()->id);
            if ($current_rank_info == null) {
                $achieved_rank_desc = strtoupper("Ambassador");
            } else {
                $achieved_rank_desc = strtoupper($current_rank_info->achieved_rank_desc);
            }

            $response['achieved_rank_desc'] = $achieved_rank_desc;

            $this->setResponse($response);
            $this->setResponseCode(200);
        }else{
            $this->setMessage("Access Denied - Unauthorized");
            $this->setResponseCode(401);
        }

        return $this->showResponse();*/
    }

    private function attemptVibeLogin($username, $password, $userType, $rememberMe = false)
    {
        // 51 is the product ID for Vibe import users
        $user = User::where(['username' => $username, 'password' => md5($password), 'current_product_id' => 51])->first();

        if ($user) {
            Auth::login($user, $rememberMe);
            return true;
        }

        return false;
    }

    public function sendPasswordResettingEmail(Request $req) {

        $q = $req->distid;
        if (Util::isNullOrEmpty($q))
            return response()->json(['error' => '1', 'msg' => 'Please enter your distributor ID or Username']);
        else {
            $user = User::getByDistIdOrUsername($q);
            if (empty($user)) {
                return response()->json(['error' => '1', 'msg' => 'Invalid distributor ID or Username']);
            } else {
                if (Util::isNullOrEmpty($user->email)) {
                    return response()->json(['error' => '1', 'msg' => 'Email not found,<br/>please contact us at mail.countdown4freedom.com']);
                } else {
                    $token = PasswordResetTokens::createNew($user->email);

                    $resettingUrl = \Config::get('app.ambassador_url').'/reset-password/' . $token;

                    MyMail::sendResettingPassword($user->email, $user->firstname . " " . $user->lastname, $resettingUrl);
                    return response()->json(['error' => '0', 'msg' => 'Please check your inbox for password resetting email']);
                }
            }
        }
    }

    public function resetPassword(Request $req) {

        $token = $req->token;
        $email = PasswordResetTokens::getEmailByToken($token);
        if ($email == null)
            return response()->json(['error' => 1, 'msg' => "Invalid password resetting token"]);
        //
        $vali = $this->validateNewPassword();
        if ($vali['valid'] == 0) {
            return response()->json(['error' => 1, 'msg' => $vali['msg']]);
        } else {
            DB::table('users')
                ->where('email', $email)
                ->update([
                    'password' => password_hash($req->pass_1, PASSWORD_BCRYPT),
                    'default_password' => '',
                    'email_verified' => 1
                ]);
            return response()->json(['error' => '0', 'msg' => 'Password changed successfully']);
        }
    }

    private function validateNewPassword() {
        $req = request();
        $validator = Validator::make($req->all(), [
            'pass_1' => 'required|min:6',
            'pass_2' => 'same:pass_1',
        ], [
            'pass_1.required' => 'New password is required',
            'pass_1.min' => 'New password must be at least 6 charactors',
            'pass_2.same' => 'Passwords do not match',
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
        }
        $res = array();
        $res["valid"] = $valid;
        $res["msg"] = $msg;
        return $res;
    }

    /*
    * Get Admin SSO token, verify is token is still valid
    */
    public function getAdminSSO($distid, $token){
        //This was preventing the users to SSO between midnight and 1am
        // $this->clearExpiredTokens(); //clear expired tokens

        $user   = User::getByDistId($distid);
        $token  = UserAuthSsoToken::where('user_id', $user->id)
        ->where('token', $token)
        ->first();
        if($token) {
            //Authenticate the use by the id
            Auth::loginUsingId($user->id);

            //If script got to this point it means the user is authenticated
            $response           = [];
            $response['token']  = Auth::user()->createToken('ncrease')->accessToken;
            $response['user']   = Auth::user();

            $pv = Order::getThisMonthOrderQV(Auth::user()->id);
            $response['pv'] = $pv > 100 ? 100 : $pv;

            $current_rank_info = UserRankHistory::getCurrentMonthUserInfo(Auth::user()->id);
            if ($current_rank_info == null) {
                $achieved_rank_desc = strtoupper("Ambassador");
            } else {
                $achieved_rank_desc = strtoupper($current_rank_info->achieved_rank_desc);
            }

            $response['achieved_rank_desc'] = $achieved_rank_desc;

            $this->setResponse($response);
            $this->setResponseCode(200);
            return $this->showResponse();

        } else {
            $this->setMessage('Invalid token');
            $this->setResponseCode(401);
            return $this->showResponse();
        }
    }

    /*
    * Clear expired tokens for the SSO function
    */
    private function clearExpiredTokens(){
        $tk = new UserAuthSsoToken();
        $deletes = UserAuthSsoToken::where('expiration_date', '<', Carbon::now()->format('Y-m-d h:i:s'))->delete();
    }
}

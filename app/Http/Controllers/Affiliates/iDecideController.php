<?php

namespace App\Http\Controllers\Affiliates;

use App\Http\Controllers\Controller;
use App\Models\ProductTermsAgreement;
use Illuminate\Http\Request;
use Validator;
use DB;
use App\Models\IDecide;
use App\Helpers\Util;
use Auth;

class iDecideController extends Controller
{
    public function resetPassword() {
        $req = request();
        $userId = Auth::user()->id;
        $newPassword = $req->idecide_new_pass;
        if (Util::isNullOrEmpty($newPassword)) {
            return response()->json(['error' => 1, 'msg' => "Please enter new iDecide password"]);
        }
        //
        $idecideUserRec = DB::table('idecide_users')
                ->where('user_id', $userId)
                ->first();
        if (empty($idecideUserRec)) {
            return response()->json(['error' => 1, 'msg' => "Idecide account not found"]);
        }
        //
        if ($idecideUserRec->generated_integration_id > 0) {
            $responseBody = IDecide::updateUserPassword($idecideUserRec->generated_integration_id, $newPassword);
        } else {
            $responseBody = IDecide::updateUserPassword($userId, $newPassword);
        }
        //
        $response = $responseBody['response'];
        $request = $responseBody['request'];
        if (!empty($response->success) && $response->success == 1) {
            //password reset success
            IDecide::where('user_id', $userId)->update(['password' => $newPassword]);
            return response()->json(['error' => 0, 'msg' => 'iDecide password changed']);
        } else {
            //password reset failure
            $errors = implode('<br>', $response->errors);
            return response()->json(['error' => 1, 'msg' => $errors]);
        }
    }

    public function resetEmail() {
        $req = request();
        //
        $vali = $this->validateResetEmail();
        if ($vali['valid'] == 0) {
            return response()->json(['error' => 1, 'msg' => $vali['msg']]);
        }

        $userId = Auth::user()->id;
        $newEmail = $req->idecide_email;
        //
        $idecideUserRec = DB::table('idecide_users')
                ->where('user_id', $userId)
                ->first();
        if (empty($idecideUserRec)) {
            return response()->json(['error' => 1, 'msg' => "Idecide account not found"]);
        }
        //
        if ($idecideUserRec->generated_integration_id > 0) {
            $responseBody = IDecide::updateUserEmailAddress($idecideUserRec->generated_integration_id, $newEmail);
        } else {
            $responseBody = IDecide::updateUserEmailAddress($userId, $newEmail);
        }
        //
        $response = $responseBody['response'];
        $request = $responseBody['request'];
        if (!empty($response->success) && $response->success == 1) {
            return response()->json(['error' => 0, 'msg' => 'iDecide email changed']);
        } else {
            //password reset failure
            $errors = implode('<br>', $response->errors);
            return response()->json(['error' => 1, 'msg' => $errors]);
        }
    }

    private function validateResetEmail() {
        $req = request();
        $validator = Validator::make($req->all(), [
                    'idecide_email' => 'required|email',
                        ], [
                    'idecide_email.required' => 'Email is required',
                    'idecide_email.email' => 'Invalid email format',
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

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function createNewAccountByUser()
    {
        $hasAgree = DB::table('product_terms_agreement')->select('*')->where('user_id', Auth::user()->id)->first();
        if (empty($hasAgree)) {
            ProductTermsAgreement::addAgreement('idecide', Auth::user()->id);
        } else if ($hasAgree->agree_idecide != 1) {
            DB::table('product_terms_agreement')->where('user_id', Auth::user()->id)->update([
                'agree_idecide' => 1,
                'agreed_idecide_at' => date('Y-m-d h:i:s'),
            ]);
        }
        $idecide = IDecide::where('user_id', Auth::user()->id)->first();
        if (!empty($idecide)) {
            $response = IDecide::SSOLogin($idecide);
            return response()->json($response);
        }
        $response = IDecide::createAccount(Auth::user()->id, 'IDECIDE - createNewAccount');
        if ($response['error'] == 0) {
            $idecideUserRec = DB::table('idecide_users')
                ->where('user_id', Auth::user()->id)
                ->first();
            $response = IDecide::SSOLogin($idecideUserRec);
            return response()->json($response);
        } else {
            return response()->json($response);
        }
    }
}

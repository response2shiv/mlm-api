<?php

namespace App\Http\Controllers\Affiliates;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Helper;
use App\Models\Product;
use App\Models\ProductTermsAgreement;
use App\Models\SaveOn;
use App\Models\User;
use Auth;
use DB;

class SaveOnController extends Controller
{
    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function createNewAccountByUser()
    {
        $hasAgree = DB::table('product_terms_agreement')->select('*')->where('user_id', Auth::user()->id)->first();

        if (empty($hasAgree)) {
            ProductTermsAgreement::addAgreement('sor', Auth::user()->id);
        } else if ($hasAgree->agree_sor != 1) {
            DB::table('product_terms_agreement')->where('user_id', Auth::user()->id)->update([
                'agree_sor' => 1,
                'agreed_sor_at' => date('Y-m-d h:i:s'),
            ]);
        }

        //check existing account
        $sor = SaveOn::where('user_id', Auth::user()->id)->first();

        $product = Product::getProduct(Auth::user()->current_product_id);

        if (!empty($sor)) {
            if ($sor->status == SaveOn::DEACTIVE && Auth::user()->account_status == User::ACC_STATUS_APPROVED) {
                //enable sor
                $disabledUserRsponse = SaveOn::enableUser($product->id, Auth::user()->distid, SaveOn::USER_ACCOUNT_REST);
                if ($disabledUserRsponse['status'] == 'success' && $disabledUserRsponse['enabled'] == 'true') {
                    SaveOn::where('sor_user_id', $sor->sor_user_id)->update(['status' => SaveOn::ACTIVE]);
                }
            }
            $response = SaveOn::SSOLogin($product->id, Auth::user()->distid);
            return response()->json($response);
        }

        $userAddress = Address::getRec(Auth::user()->id, Address::TYPE_REGISTRATION);

        if (empty($userAddress)) {
            $userAddress = Address::getRec(Auth::user()->id, Address::TYPE_BILLING);
            if (empty($userAddress)) {
                return response()->json(['error' => '1', 'msg' => 'Address information is missing']);
            }
        }

        $sorRes = SaveOn::SORCreateUser(Auth::user()->id, $product->id, $userAddress);

        $lastId = Helper::logApiRequests(Auth::user()->id, 'SOR - createNewSORAccount', config('api_endpoints.SORCreateUser'), $sorRes['request']);

        Helper::logApiResponse($lastId->id, json_encode($sorRes['response']));

        $sorResponse = $sorRes['response'];

        if (isset($sorResponse->Account) && isset($sorResponse->Account->UserId)) {
            $request = $sorRes['request'];
            SaveOn::insert(['api_log' => $lastId->id, 'user_id' => Auth::user()->id, 'product_id' => $product->id, 'sor_user_id' => $sorResponse->Account->UserId, 'sor_password' => $request['Password'], 'status' => 1]);
            $response = SaveOn::SSOLogin($product->id, Auth::user()->distid);
            return response()->json($response);
        } else {
            return response()->json(['error' => '1', 'msg' => 'Error when create new SOR<br/>Error: ' . $sorResponse->Message]);
        }
    }
}

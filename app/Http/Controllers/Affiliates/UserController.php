<?php

namespace App\Http\Controllers\Affiliates;

use DB;
use Log;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Facades\BinaryPlanManager;
use Validator;
use App\Models\UserActivityLog;
use App\Models\UserSettings;
use App\Models\GeoIP;
use Auth;
use Artisan;

use App\Jobs\BinaryTreePlacement;
use App\Models\Address;
use App\Models\Country;
use App\Models\Helper;
use App\Models\PaymentMethod;
use App\Models\PaymentMethodType;
use App\Models\ReplicatedPreferences;
use App\Models\State;
use App\Models\Order;
use App\Models\User;
use App\Models\UserPaymentMethod;
use App\Models\UserRankHistory;
use App\Services\VGSService;
use App\Services\BillingService;
use GuzzleHttp\Client;

class UserController extends Controller
{
    /**
     * Allow the user logged in to change the password
     *
     */
    public function changePassword(request $request)
    {
        $vali = $this->validateChangePassword($request);
        if ($vali['valid'] == 0) {
            $this->setResponseCode(400);
            $this->setMessage($vali['msg']);
            return $this->showResponse();
        } else {
            $loginUser = Auth::user();
            $loginUser->password = password_hash($request->pass_1, PASSWORD_BCRYPT);
            $loginUser->default_password = "";
            $loginUser->save();
            $this->setResponseCode(200);
            $this->setMessage('Password changed successfully');
            return $this->showResponse();
            // return response()->json(['error' => '0', 'msg' => 'Password changed successfully']);
        }
    }

    /*
    * Check if request is valid for the user to reset the password
    */
    private function validateChangePassword($req)
    {
        $validator = Validator::make($req->all(), [
            'current_pass' => 'required',
            'pass_1' => 'required|min:6',
            'pass_2' => 'same:pass_1',
        ], [
            'current_pass.required' => 'Current password is required',
            'pass_1.required' => 'New password is required',
            'pass_1.min' => 'New password must be at least 6 charactors',
            'pass_2.same' => 'Passwords do not match',
        ]);

        $msg = "";
        if ($validator->fails()) {
            $valid = 0;
            $messages = $validator->messages();
            foreach ($messages->all() as $m) {
                $msg = $m;
            }
        } else {
            $valid = 1;
            //
            if (!Hash::check($req->current_pass, Auth::user()->password)) {
                $valid = 0;
                $msg = "Invalid current password";
            }
        }
        $res = array();
        $res["valid"] = $valid;
        $res["msg"] = $msg;
        return $res;
    }

    public function showMyProfile($type = null)
    {
        if ($type == null)
            return $this->showMyProfile_basic();
        else if ($type == "primary-credit-card")
            return $this->showMyProfile_primary_cc();
        else if ($type == "primary-address")
            return $this->showMyProfile_primary_address();
        else if ($type == "shipping-address")
            return $this->getShippingAddresses();
        else if ($type == "billing-address")
            return $this->showMyProfile_billing_address();
        else if ($type == "replicated")
            return $this->showMyProfile_replicated();
        else if ($type == "binary-placement")
            return $this->placementPreference();
        else if ($type == "idecide")
            return $this->showMyProfile_idecide();
        else if ($type == "billing")
            return $this->showMyProfile_billing();
        else
            return redirect('/');
    }

    public function showMyProfile_basic()
    {
        $d['rec'] = Auth::user();
        $d['tab'] = "basic";

        $this->setResponseCode(200);
        $this->setResponse($d);

        return $this->showResponse();
    }

    public function showMyProfile_primary_cc()
    {
        $d['rec'] = Auth::user();
        $d['tab'] = "primary_card";
        $paymentMethod = PaymentMethod::getRec(Auth::user()->id, 1, PaymentMethodType::TYPE_CREDIT_CARD);
        $d['payment_method'] = $paymentMethod;
        //
        $expiryDate = "";
        if (!empty($paymentMethod)) {
            $expiryDate = $paymentMethod->expMonth . "/" . $paymentMethod->expYear;
        }
        $d['expiry_date'] = $expiryDate;

        $this->setResponseCode(200);
        $this->setResponse($d);

        return $this->showResponse();
    }

    public function placementPreference()
    {
        $loginUser = Auth::user();
        $d = array();
        $placement = DB::select("select binary_placement from users where id = '$loginUser->id'");
        $d['binary_placement'] = $placement[0]->binary_placement;

        $this->setResponse($d);
        $this->setResponseCode(200);

        return $this->showResponse();
    }

    public function showMyProfile_billing_address()
    {
        $d['rec'] = Auth::user();
        $d['tab'] = "billing-address";
        $d['countries'] = Country::getAll();
        //new enrollment users
        $d['billing_address'] = Address::getRec(Auth::user()->id, Address::TYPE_BILLING, 1);

        $this->setResponseCode(200);
        $this->setResponse($d);

        return $this->showResponse();
    }

    public function showMyProfile_primary_address()
    {
        $d['rec'] = Auth::user();
        $d['tab'] = "address";
        $d['countries'] = Country::getAll();
        //new enrollment users
        $d['primary_address'] = Address::getRec(Auth::user()->id, Address::TYPE_REGISTRATION, 1);

        $this->setResponseCode(200);
        $this->setResponse($d);

        return $this->showResponse();
    }

    public function getShippingAddresses()
    {
        $d['rec'] = Auth::user();
        $d['countries'] = Country::getAll();

        $d['shipping_addresses'] = Address::where('userid', Auth::user()->id)
            ->where('addrtype', Address::TYPE_SHIPPING)
            ->where('is_deleted', false)
            ->orderBy('primary', 'DESC')
            ->get();

        $this->setResponseCode(200);
        $this->setResponse($d);

        return $this->showResponse();
    }

    public function showMyProfile_shipping_address($addressId)
    {
        $shipping_address = Address::getShippingAddress(Auth::user()->id, $addressId);


        $this->setResponseCode(200);
        $this->setResponse($shipping_address);

        return $this->showResponse();
    }

    public function showMyProfile_idecide()
    {
        $d = array();
        $d['tab'] = "idecide";

        $this->setResponseCode(200);
        $this->setResponse($d);

        return $this->showResponse();
    }

    public function showMyProfile_billing()
    {
        $d = array();
        $d['tab'] = "billing";
        $d['cards'] = PaymentMethod::getUserPaymentMethods(Auth::user()->id, 1);
        $d['addresses'] = Address::getFilteredBillingAddresses(Auth::user()->id);
        $d['countries'] = Country::getAll();

        $d['vibeUserWithoutBillingInfo'] = User::isVibeImportUser() && Auth::user()->paymentMethods()->count() == 0;

        $this->setResponseCode(200);
        $this->setResponse($d);

        return $this->showResponse();
    }

    public function showMyProfile_replicated()
    {
        $user = Auth::user();
        $preferences = $user->replicatedPreferences;

        $d['tab'] = "replicated";

        $d['preferences'] = [
            'business_name' => $preferences && $preferences->business_name ? $preferences->business_name : $user->business_name,
            'displayed_name' => $preferences && $preferences->displayed_name ? $preferences->displayed_name : $user->firstname . ' ' . $user->lastname,
            'name' => $user->firstname . ' ' . $user->lastname,
            'co_name' => $user->co_applicant_name,
            'co_display_name' => $preferences && $preferences->co_name ? $preferences->co_name : $user->co_applicant_name,
            'phone' => $preferences && $preferences->phone ? $preferences->phone : $user->phonenumber,
            'email' => $preferences && $preferences->email ? $preferences->email : $user->email,
            'show_email' => $preferences ? $preferences->show_email : 1,
            'show_phone' => $preferences ? $preferences->show_phone : 1,
            'show_name' => $preferences ? $preferences->show_name : 1,
            'disable_co_app' => !$user->co_applicant_name,
        ];

        $this->setResponseCode(200);
        $this->setResponse($d);

        return $this->showResponse();
    }

    public function saveProfile()
    {
        $req = request();
        $loginUser = Auth::user();
        $vali = $this->validateSaveProfile($loginUser->id);
        if ($vali['valid'] == 0) {

            $this->setResponseCode(400);
            $this->setResponse(['error' => 1, 'msg' => $vali['msg']]);
            return $this->showResponse();
        } else {
            User::updateRec($loginUser->id, $req, false);

            $this->setResponse(['error' => '0', 'msg' => 'Saved']);
            $this->setResponseCode(200);
            return $this->showResponse();
        }
    }

    public function savePrimaryAddress()
    {
        $req = request();

        $vali = $this->validatePrimaryAddress();
        if ($vali['valid'] == 0) {
            $this->setResponseCode(400);
            $this->setResponse(['error' => 1, 'msg' => $vali['msg']]);

            return $this->showResponse();
        }
        Address::updateRec(Auth::user()->id, Address::TYPE_REGISTRATION, 1, $req);
        $this->setResponse(['error' => '0', 'msg' => 'Saved']);
        $this->setResponseCode(200);

        return $this->showResponse();
    }

    public function saveBillingAddress()
    {
        $req = request();
        $vali = $this->validatePrimaryAddress();
        if ($vali['valid'] == 0) {
            $this->setResponseCode(400);
            $this->setResponse(['error' => 1, 'msg' => $vali['msg']]);

            return $this->showResponse();
        }
        Address::updateRec(Auth::user()->id, Address::TYPE_BILLING, 1, $req);
        $this->setResponse(['error' => '0', 'msg' => 'Saved']);
        $this->setResponseCode(200);

        return $this->showResponse();
    }

    public function saveShippingAddress()
    {
        $req = request();

        $vali = $this->validatePrimaryAddress();
        if ($vali['valid'] == 0) {
            $this->setResponseCode(400);
            $this->setResponse(['error' => 1, 'msg' => $vali['msg']]);

            return $this->showResponse();
        }

        Address::where('addrtype', Address::TYPE_SHIPPING)
            ->where('userid', \Auth::user()->id)
            ->update([
                'primary' => 0
            ]);

        Address::updateRec(Auth::user()->id, Address::TYPE_SHIPPING, 1, $req);

        $this->setResponse(['error' => 0, 'msg' => 'Saved']);
        $this->setResponseCode(200);

        return $this->showResponse();
    }

    public function updateShippingAddress($addressId)
    {
        $req = request();

        $vali = $this->validatePrimaryAddress();
        if ($vali['valid'] == 0) {
            $this->setResponseCode(400);
            $this->setResponse(['error' => 1, 'msg' => $vali['msg']]);

            return $this->showResponse();
        }

        Address::updateShipping(Auth::user()->id, $addressId, $req);

        $this->setResponse(['error' => 0, 'msg' => 'Saved']);
        $this->setResponseCode(200);

        return $this->showResponse();
    }

    public function showIpServer()
    {
        $client = new \GuzzleHttp\Client();
        $response = $client->request('GET', 'https://api.ipify.org/?format=json');
        $response = $response->getBody()->getContents();
        echo '<pre>';
        print_r($response);
    }

    public function setPrimaryShippingAddress($addressId)
    {
        try {
            Address::setPrimaryShipping(Auth::user()->id, $addressId);

            Address::where('addrtype', Address::TYPE_SHIPPING)
                ->where('userid', \Auth::user()->id)
                ->where('id', '!=', $addressId)
                ->update([
                    'primary' => 0
                ]);

            $this->setResponse(['error' => 0, 'msg' => 'Saved']);
            $this->setResponseCode(200);
        } catch (\Exception $e) {

            $this->setResponse(['error' => 1, 'msg' => 'Dont Saved']);
            $this->setResponseCode(200);
        }


        return $this->showResponse();
    }

    public function savePlacements()
    {
        $req = request();
        $loginUser = Auth::user();
        $vali = $this->validateSavePlacements();
        if ($vali['valid'] == 0) {
            $this->setResponseCode(400);
            $this->setResponse(['error' => 1, 'msg' => $vali['msg']]);

            return $this->showResponse();
        } else {
            User::updatePlacements($loginUser->id, $req);
            $this->setResponse(['error' => '0', 'msg' => 'Saved']);
            $this->setResponseCode(200);

            return $this->showResponse();
        }
    }


    public function savePreferences()
    {
        $request = request();
        $user = Auth::user();
        $preferences = $user->replicatedPreferences;
        if (!$preferences) {
            $preferences = new ReplicatedPreferences();
        }
        $preferences_old = clone $preferences;

        

        $preferences->user_id = $user->id;
        $preferences->displayed_name = $request->display_name;
        $preferences->business_name = $request->business_name ?? $user->business_name;
        $preferences->phone = $request->phone;
        $preferences->email = $request->email;
        $preferences->show_email = $request->show_email ? 1 : 0;
        $preferences->show_phone = $request->show_phone ? 1 : 0;
        $preferences->show_name = $request->show_name ?: 1;

        $preferences->save();

        $userActivityLog = new UserActivityLog;
        $row = UserSettings::getByUserId(Auth::user()->id);
        $ip = $row->current_ip;
        if(!$ip)
            $ip = "127.0.0.1";

        //$response = GeoIP::getInformationFromIP($row->current_ip);
        $userActivityLog->ip_address = $ip;
        $userActivityLog->user_id = Auth::user()->id;
        $userActivityLog->ip_details = $ip;//$response;
        $userActivityLog->action = "UPDATE user Replicated Site Preferences";
        $userActivityLog->old_data = json_encode($preferences_old);
        $userActivityLog->new_data = json_encode($preferences);
        $userActivityLog->save();

        $this->setResponse(['error' => '0', 'msg' => 'Saved']);
        $this->setResponseCode(200);

        return $this->showResponse();
    }

    private function validatePrimaryAddress()
    {
        $req = request();
        $validator = Validator::make($req->all(), [
            'address1' => 'required|max:255',
            'city' => 'required|max:255',
            'stateprov' => 'required|max:50',
            'postalcode' => 'required|max:10',
            'countrycode' => 'required|max:10',
        ], [
            'address1.required' => 'Address is required',
            'address1.max' => 'Address exceed the limit',
            'countrycode.required' => 'Country is required',
            'countrycode.max' => 'Country exceed the limit',
            'city.required' => 'City / Town is required',
            'city.max' => 'City / Town exceed the limit',
            'stateprov.required' => 'State / Province is required',
            'stateprov.max' => 'State / Province exceed the limit',
            'postalcode.required' => 'Postal code is required',
            'postalcode.max' => 'Postal code exceed the limit'
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

    private function validateSaveProfile($userId)
    {
        $req = request();
        $validator = Validator::make($req->all(), [
            'firstname' => 'required',
            'lastname' => 'required',
        ], [
            'firstname.required' => 'First name is required',
            'lastname.required' => 'Last name is required',
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

    private function validateSavePlacements()
    {
        $req = request();
        $validator = Validator::make($req->all(), [
            'binary_placement' => 'required'
        ], [
            'binary_placement.required' => 'You need to check atleast one option'
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

    public function billingAddNewCard(Request $request)
    {
        $user = Auth::user();
        $validator = $this->createBillingNewCardValidator($request);

        $errorMessage = $this->generateErrorMessageFromValidator($validator);


        if (!empty($errorMessage)) {

            $this->setResponseCode(400);
            $this->setResponse(['error' => 1, 'msg' => $errorMessage]);

            return $this->showResponse();
        }

        if (!$request->address['usePrimaryAddress']) {
            $address = User::getUserPrimaryAddress($user);
        } else {
            $address = $request->address;
        }


        $response = VGSService::sendData([
            'credit_card' => [
                'number' => $request->credit_card['number']
            ]
        ], "/api/v1/vgs/print");

        Log::info("VGS data:" . json_encode($response));


        $res['credit_card'] = $request->credit_card;
        $res['credit_card']['card_number'] = $response->credit_card->number;
        $res['credit_card']['is_primary'] = 0;
        $res['credit_card']['name'] = $request->first_name . " " . $request->last_name;
        $res['credit_card']['billingAddress'] = $address;

        $userActivityLog = new UserActivityLog;
        $row = UserSettings::getByUserId(Auth::user()->id);
        $response = GeoIP::getInformationFromIP($row->current_ip);
        $userActivityLog->ip_address = $row->current_ip;
        $userActivityLog->user_id = Auth::user()->id;
        $userActivityLog->ip_details = $response;
        $userActivityLog->action = "UPDATE user billing Add New Card";
        $userActivityLog->old_data = json_encode($response);
        $userActivityLog->new_data = json_encode($response);
        $userActivityLog->save();

        $this->setResponseCode(200);
        $this->setResponse(['data' => $res, 'error' => '0', 'msg' => 'Saved']);

        return $this->showResponse();
    }

    private function createBillingNewCardValidator($request)
    {

        list($rules, $messages) = $this->getAddNewBillingCardRulesAndMessages();

        if (!$request->address['usePrimaryAddress']) {
            list($addressRules, $addressMessages) = $this->getNewAddressValidator();

            $rules = array_merge($rules, $addressRules);
            $messages = array_merge($messages, $addressMessages);
        }

        $validator = Validator::make($request->all(), $rules, $messages);

        return $validator;
    }

    public function deletePaymentMethod()
    {
        $request = request();

        $paymentMethod = UserPaymentMethod::getById($request->payment_method_id, Auth::user()->id);

        if (!$paymentMethod) {

            $this->setResponseCode(400);
            $this->setResponse(['error' => '1', 'data' => 'Access denied']);

            return $this->showResponse();
        }

        UserPaymentMethod::markAsDeleted($request->payment_method_id);

        if (Auth::user()->subscription_payment_method_id == $request->payment_method_id) {
            User::where('id', Auth::user()->id)->update(['subscription_payment_method_id' => null]);
        }

        $this->setResponseCode(200);
        $this->setResponse(['error' => '0', 'data' => 'Payment method deleted', 'url']);

        return $this->showResponse();
    }

    public function deleteUserShippingAddress($addressId)
    {

        $shippingAddress = User::deleteUserShippingAddress(Auth::user(), $addressId);

        if (!$shippingAddress) {

            $this->setResponseCode(400);
            $this->setResponse(['error' => '1', 'data' => 'Access denied']);

            return $this->showResponse();
        }

        Address::markAsDeleted($addressId);

        $this->setResponseCode(200);
        $this->setResponse(['error' => '0', 'data' => 'Shipping Address deleted']);

        return $this->showResponse();
    }

    private function getAddNewBillingCardRulesAndMessages()
    {
        // ccd, ccn, and cvc are from composer package
        $rules = [
            'credit_card.first_name' => 'required|max:50',
            'credit_card.last_name' => 'required|max:50',
            'credit_card.expiry_date' => 'required|size:7',
            'credit_card.number' => 'required|max:20',
            'credit_card.cvv' => 'required|max:4|min:3'
        ];

        $messages = [
            'credit_card.first_name.required' => 'First name on card is required',
            'credit_card.first_name.max' => 'First name cannot exceed 50 characters',
            'credit_card.last_name.required' => 'Last name on card is required',
            'credit_card.last_name.max' => 'Last name cannot exceed 50 characters',
            'credit_card.number.required' => 'Card number is required',
            'credit_card.number.min' => "Card number is invalid",
            // 'number.ccn' => 'Card number is invalid',
            'credit_card.cvv.required' => 'CVV is required',
            // 'cvv.cvc' => 'CVV is invalid',
            'credit_card.cvv.max' => 'CVV cannot exceed 4 characters',
            'credit_card.expiry_date.required' => 'Expiration date is required',
            'credit_card.expiry_date.size' => 'Invalid expiration date format',
            // 'expiry_date.ccd' => 'Invalid expiration date'
        ];

        return array($rules, $messages);
    }

    private function getNewAddressValidator()
    {
        $rules = [
            'address.address1' => 'required|max:255',
            'address.city' => 'required|max:255',
            'address.stateprov' => 'required|max:50',
            'address.postalcode' => 'required|max:10',
            'address.countrycode' => 'required|regex:/[a-z][a-z]/i'
        ];

        $messages = [
            'address.address1.required' => 'Address is required',
            'address.address1.max' => 'Address is above the maximum size',
            'address.countrycode.required' => 'Country is required',
            'address.city.required' => 'City / Town is required',
            'address.city.max' => 'City / Town is above the maximum size',
            'address.stateprov.required' => 'State / Province is required',
            'address.stateprov.max' => 'State / Province is above the maximum size',
            'address.postalcode.required' => 'Postal code is required',
            'address.postalcode.max' => 'Postal code is above the maximum size'
        ];

        return array($rules, $messages);
    }

    /*
    * Save profile picture
    */
    public function saveProfilePicture(request $request)
    {
        $loginUser = Auth::user();
        $validator = Validator::make($request->all(), [
            'profile_image_url' => 'required'
        ]);

        $msg = "";
        if ($validator->fails()) {
            $valid = 0;
            $messages = $validator->messages();
            foreach ($messages->all() as $m) {
                $msg = $m;
            }

            $this->setResponseCode(400);
            $this->setMessage($msg);
            return $this->showResponse();
            exit();
        }

        $user = User::setProfilePicture($loginUser->id, $request->profile_image_url);

        $this->setResponseCode(200);
        $this->setMessage('Profile Picture Saved');
        return $this->showResponse();
    }

    /*
    * Load user information
    */
    public function getUserInfo()
    {
        $user = User::getById(Auth::user()->id);

        $current_rank_info = UserRankHistory::getCurrentMonthUserInfo(Auth::user()->id);
        if ($current_rank_info == null) {
            $rank = 10;
            $achieved_rank_desc = strtoupper("Ambassador");
            $monthly_rank_desc = strtoupper("Ambassador");
            $monthly_qv = 0;
            $monthly_tsa = 0;
            $monthly_qc = 0;
        } else {
            $rank = $current_rank_info->monthly_rank;
            $achieved_rank_desc = strtoupper($current_rank_info->achieved_rank_desc);
            $monthly_rank_desc = strtoupper($current_rank_info->monthly_rank_desc);
            $monthly_qv = number_format($current_rank_info->monthly_qv);
            $monthly_tsa = number_format($current_rank_info->monthly_tsa);
            $monthly_qc = number_format($current_rank_info->monthly_qc);
        }
        // $d['achieved_rank_desc'] = $achieved_rank_desc;
        $user->achieved_rank_desc = $achieved_rank_desc;
        $user->qv = $current_rank_info ? $current_rank_info->qualified_qv : 0;
        $pv = Order::getThisMonthOrderQV(Auth::user()->id);
        $user->pv = $pv > 100 ? 100 : $pv;
        $this->setResponseCode(200);
        $this->setResponse($user);
        return $this->showResponse();
    }

    public function getPaymentMethods()
    {
        $user = Auth::user();

        $pm = $user->userPaymentMethods()->where('pay_method_type', '!=', UserPaymentMethod::TYPE_EWALLET)
            ->where('is_deleted', '!=', true)
            ->with('billingAddress')
            ->get();
        $this->setResponse([
            'payment_methods' => $pm
        ]);

        return $this->showResponse();
    }

    public function billingRemoveCard(Request $request)
    {
        $user = Auth::user();
        $pm   = PaymentMethod::getById($request->payment_method_id, $user->id);

        $pm->delete();

        return $pm;
    }

    public function getUserPrimaryAddress()
    {
        $address = User::getUserPrimaryAddress(Auth::user());
        $this->setResponse($address);
        $this->setResponseCode(200);
        return $this->showResponse();
    }

    public function getUserShippingAddress()
    {
        $address = User::getUserShippingAddress(Auth::user());

        if ($address->countrycode == "US") {
            $states = State::whereCountryCode($address->countrycode)->get();
            $userState = State::whereName($address->stateprov)->first();

            $address->states = $states;
            $address->user_state = $userState->code;
        }

        $this->setResponse($address);
        $this->setResponseCode(200);
        return $this->showResponse();
    }

    public function setPaymentMethodAsPrimary(Request $request)
    {
        $user = Auth::user();

        if (UserPaymentMethod::setPaymentMethodAsPrimary($request->payment_method, $user)) {

            $this->setResponseCode(200);
            $this->setResponse("Payments Updated");

            return $this->showResponse();
        };


        $this->setResponseCode(400);
        $this->setResponse("Payments update process has failed!");

        return $this->showResponse();
    }

    public function getPaymentMethod($id)
    {
        $paymentMethod = UserPaymentMethod::getById($id, Auth::user()->id);

        if (!$paymentMethod) {

            $this->setResponseCode(400);
            $this->setResponse(['error' => '1', 'data' => 'Access denied']);

            return $this->showResponse();
        }

        $data = [
            'first_name' => $paymentMethod->first_name,
            'last_name' => $paymentMethod->last_name,
            'card_number' => PaymentMethod::getFormatedCardNo($paymentMethod->card_token),
            'expiry_date' => $paymentMethod->expiration_month . '/' . $paymentMethod->expiration_year,
            'country_code' => $paymentMethod->billingAddress->country_code,
            'state' => $paymentMethod->billingAddress->state,
            'zipcode' => $paymentMethod->billingAddress->zipcode,
            'address1' => $paymentMethod->billingAddress->address1,
            'city' => $paymentMethod->billingAddress->city
        ];

        $this->setResponseCode(200);
        $this->setResponse($data);

        return $this->showResponse();
    }

    public function addPaymentMethod(Request $request)
    {
        $data = $request->all();

        $addressInfo = $request->address;
        $cardInfo = $request->credit_card;

        if ($cardInfo['update_payment_method_id'] == null) {

            $response = BillingService::addPaymentMethod($addressInfo, $cardInfo);
            $data = (object)$response;

            if ($data->success) {
                UserPaymentMethod::convertAndSave($data->data['credit_card'], $data->data['billing_address']);
            } else {
                return response()->json($data, 200);
            }
        } else {

            # Just for edit credit card on user's profile.
            $rules = [
                'credit_card.first_name' => "required|string|min:3",
                'credit_card.last_name' => "required|string|min:3",
                'credit_card.expiry_date' => 'required|date_format:m/Y|after_or_equal:'.date('m/Y'),
                'address.address1' => 'required',
                'address.city' => 'required',
                'address.stateprov' => 'required',
                'address.countrycode' => 'required',
            ];

            $attributes = [
                'credit_card.first_name' => 'First Name',
                'credit_card.last_name' => 'Last Name',
                'credit_card.expiry_date' => 'Expiration Date',
                'address.address1' => 'Address',
                'address.city' => 'City/Town',
                'address.stateprov' => 'State/Province',
                'address.countrycode' => 'Country'
            ];

            $validator = Validator::make($request->only(array_keys($rules)), $rules, [], $attributes);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()->all(),
                    'data' => null
                ]);
            }

            # If all data is validated, then update the existing payment.
            UserPaymentMethod::convertAndUpdate($cardInfo, $addressInfo, $cardInfo['update_payment_method_id']);
        }

        return response()->json([
            'success' => true,
            'data' => $data
        ], 200);
    }

    public function getMonthPQV($userid, $month, $year)
    {
        try {
            $response['pqv'] = User::getMonthPQV($userid, $month, $year);
        } catch (\Exception $exception) {
            $response['pqv'] = 0;
        }

        $this->setResponseCode(200);
        $this->setResponse($response);
        return $this->showResponse();
    }

    public function verifyEmail($email)
    {
        $client = new Client();
        $response = $client->get('https://api.hunter.io/v2/email-verifier?email=' . $email . '&api_key=' . config('api_endpoints.HunterioAPIKey'));

        $this->setResponse(json_decode($response->getBody()));
        $this->setResponseCode(200);
        $this->setMessage('Profile Picture Saved');
        return $this->showResponse();
    }

    public function purgeUser(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|numeric|exists:App\Models\User,id'
        ]);

        $msg = "";
        if ($validator->fails()) {
            $valid = 0;
            $messages = $validator->messages();
            foreach ($messages->all() as $m) {
                $msg = $m;
            }
            return response()->json(['error' => 1, 'msg' => $msg]);
            exit();
        }
        // return User::purgeUser($request->user_id);

        $user = User::find($request->user_id);
        if (!$user) {
            return response()->json(['error' => 1, 'msg' => 'User not found']);
        }

        // user node check
        $agentNode = BinaryPlanManager::getNodeByAgentTsa($user->distid);
        if (!$agentNode) {
            return response()->json(['error' => 1, 'msg' => 'TSA is not found in the tree']);
            exit();
        }
        // BinaryPlanManager::deleteNode($agentNode);
        //Call queue to delete the user from the tree
        if (!BinaryPlanManager::checkIfNodeHasEnrolledDistributors($agentNode)) {
            BinaryTreePlacement::dispatch($request->user_id, 'delete')->onQueue('binarytree');
        }
        return response()->json(['error' => 0, 'msg' => 'User has been purged from the system.']);
    }

    public function purgePendingsers()
    {
        // Artisan::call('purge:users');
        return response()->json(['error' => 0, 'msg' => 'Command triggered - waiting for queue to execute.']);
    }
}

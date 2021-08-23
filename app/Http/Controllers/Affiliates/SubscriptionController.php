<?php

namespace App\Http\Controllers\Affiliates;

use DB;
use Log;
use Auth;
use Session;
use Validator;
use Carbon\Carbon;
use App\Models\User;
use App\Helpers\Util;
use App\Models\GeoIP;
use App\Models\Order;
use App\Helpers\Kount;
use App\Models\Helper;
use App\Models\SaveOn;
use App\Models\Address;
use App\Models\IDecide;
use App\Models\Product;
use App\Models\OrderItem;
use App\Models\NMIGateway;
use App\Helpers\tokenexAPI;
use Illuminate\Support\Str;
use App\Models\Subscription;
use App\Models\UserSettings;
use App\Models\PaymentMethod;
use App\Models\DiscountCoupon;
use App\Models\OrderConversion;
use App\Models\UserActivityLog;

use App\Models\PaymentMethodType;
use App\Models\UserPaymentMethod;
use App\Helpers\CurrencyConverter;
use App\Models\EwalletTransaction;
use App\Models\SubscriptionHistory;
use App\Http\Controllers\Controller;



class SubscriptionController extends Controller
{

    public function index($country = null, $locale = null)
    {
        $d = array();

        $user = User::getById(Auth::user()->id);
        $subscriptionProduct = Subscription::getSubscriptionProduct(Auth::user()->id);

        //
        $septemberSubscription = array();
        $septemberSubscription['subscription_amount'] = '';
        $septemberSubscription['subscription_product_id'] = '';            
        $septemberSubscription['conversion'] = ["display_amount"=>''];
        $septemberSubscription['subscription_fee'] = '';
        $septemberSubscription['total'] = '';            
        $septemberSubscription['total_display'] = '';
        
        $septemberOrdersSubscription = Order::septemberOrdersSubscription();
        if (empty($septemberOrdersSubscription)){
            switch($subscriptionProduct->id){
                case Product::MONTHLY_MEMBERSHIP:
                    $septemberSubscriptionProductId = 80;
                    break;
                case Product::ID_MONTHLY_MEMBERSHIP:
                    $septemberSubscriptionProductId = 81;
                break;
                case Product::ID_TIER3_COACH:
                    $septemberSubscriptionProductId = 82;
                    break;
                case Product::MONTHLY_MEMBERSHIP_STAND_BY_USER:
                    $septemberSubscriptionProductId = 83;
                    break;           

            }                
            $septemberSubscriptionProduct = Product::getByIdAndEnable($septemberSubscriptionProductId);
            if ($septemberSubscriptionProduct) {
                $septemberSubscription['subscription_amount'] = $septemberSubscriptionProduct->price;
                $septemberSubscription['subscription_product_id'] = $septemberSubscriptionProduct->id;
                $convertObject = CurrencyConverter::convertCurrency(number_format($septemberSubscriptionProduct->price,2,'',''), $country, $locale);
                $septemberSubscription['conversion'] = $convertObject;
                $septemberSubscription['subscription_fee'] = $septemberSubscriptionProduct;
                $septemberSubscription['total'] = $septemberSubscriptionProduct->price + Helper::getReactivationFee();
                $convertObject = CurrencyConverter::convertCurrency(number_format( $septemberSubscription['total'],2,'',''), $country, $locale);
                $septemberSubscription['total_display'] = $convertObject["display_amount"];
            }
        }
        $d['september_subscription'] = $septemberSubscription; 
        
        $d['subscription_amount'] = $subscriptionProduct->price;
        $d['subscription_product_id'] = $subscriptionProduct->id;
        $convertObject = CurrencyConverter::convertCurrency(number_format($subscriptionProduct->price,2,'',''), $country, $locale);
        $d['conversion'] = $convertObject;
        $subscriptionPlan = Subscription::getCurrentSubscriptionPlan(Auth::user()->id, $convertObject);
        $d['current_plan'] = $subscriptionPlan;
        $d['next_subscription_date'] = $subscriptionPlan ? (string)$user->next_subscription_date : '';
        $paymentMethodId = $user->subscription_payment_method_id;
        $d['gflag'] = $user->gflag;
        $pMDrop = $this->getPaymentMethods();
        $d['payment_method'] = $pMDrop;
        $d['subscription_fee'] = Product::getById(Product::ID_REACTIVATION_PRODUCT);
        $convertObject = CurrencyConverter::convertCurrency(number_format($d['subscription_fee']->price,2,'',''), $country, $locale);
        $d['subscription_fee']->price = $convertObject["display_amount"];
        $d['total'] = $subscriptionProduct->price + Helper::getReactivationFee();
        $convertObject = CurrencyConverter::convertCurrency(number_format($d['total'],2,'',''), $country, $locale);
        $d['total_display'] = $convertObject["display_amount"];



        $subscriptionCardAdded = PaymentMethod::checkSubscriptionCardAdded(Auth::user()->id);
        $d['subscription_card_added'] = $subscriptionCardAdded;
        $d['is_sites_deactivate'] = $user->is_sites_deactivate;
        $d['subscription_attempts'] = $user->subscription_attempts;
        $d['current_product_id'] = $user->current_product_id;

        $orderConversion = new OrderConversion();
        
        $orderConversion->fill([
            'session_id' => session_id(),
            'original_amount' => number_format($d['total'],2,'',''),
            'original_currency' => "USD",
            'converted_amount' => $convertObject["amount"],
            'converted_currency' => $convertObject['currency'],
            'exchange_rate' => $convertObject['exchange_rate'],
            'expires_at' => now()->addMinutes(30),
            'display_amount' => $convertObject['display_amount']
        ]);

        $orderConversion->save();

        $d['order_conversion_id'] = $orderConversion->id;
        $d['expiration'] = $orderConversion->expires_at->timestamp;

        $this->setResponse($d);
        $this->setResponseCode(200);

        return $this->showResponse();
    }
    public function dlgSubscriptionReactivateSuspendedUser($country = null, $locale = null)
    {
        session_start();
        $d['sessionId'] = session_id();
        $d['payment_method'] = $this->getPaymentMethods();
        $subscriptionProduct = Subscription::getSubscriptionProduct(Auth::user()->id);
        $d['subscription_amount'] = $subscriptionProduct->price;
        $convertObject = CurrencyConverter::convertCurrency(number_format($subscriptionProduct->price,2,'',''), $country, $locale);
        $d['conversion'] = $convertObject;
        $d['subscription_fee'] = Product::getById(Product::ID_REACTIVATION_PRODUCT);
        $convertObject = CurrencyConverter::convertCurrency(number_format($d['subscription_fee']->price,2,'',''), $country, $locale);
        $d['subscription_fee']->price = $convertObject["display_amount"];
        $d['total'] = $subscriptionProduct->price + Helper::getReactivationFee();
        $convertObject = CurrencyConverter::convertCurrency(number_format($d['total'],2,'',''), $country, $locale);
        $d['total_display'] = $convertObject["display_amount"];

        $orderConversion = new OrderConversion();


        $orderConversion->fill([
            'session_id' => session_id(),
            'original_amount' => number_format($d['total'],2,'',''),
            'original_currency' => "USD",
            'converted_amount' => $convertObject["amount"],
            'converted_currency' => $convertObject['currency'],
            'exchange_rate' => $convertObject['exchange_rate'],
            'expires_at' => now()->addMinutes(30),
            'display_amount' => $convertObject['display_amount']
        ]);

        $orderConversion->save();

        $d['order_conversion_id'] = $orderConversion->id;
        $d['expiration'] = $orderConversion->expires_at->timestamp;

        $this->setResponse($d);
        $this->setResponseCode(200);

        return $this->showResponse();
    }

    public function reactivateSubscriptionSuspendedUser()
    {
        $request = request();
        $userId = Auth::user()->id;
        $subscriptionPaymentMethodId = $request['subscription_payment_method_type_id'];
        $discountCode = $request['discount_code'];
        $amount = $request['amount'];
        $orderConversionId = isset($request['order_conversion_id']) ? $request['order_conversion_id'] : null;

        if (is_numeric($subscriptionPaymentMethodId)) {
            $paymentMethod = PaymentMethod::find($subscriptionPaymentMethodId);
            if (!$paymentMethod) {
                $this->setResponse(['error' => 0, 'msg' => 'Invalid Payment Method']);
                return $this->showResponse();
            }
            $paymethodType = $paymentMethod->pay_method_type;
            if ($paymethodType == PaymentMethodType::TYPE_E_WALET) {
                return $this->reactivateByEwalletForSuspendedUser($subscriptionPaymentMethodId, $discountCode, $amount, $userId);
            } else if ($paymethodType == PaymentMethodType::TYPE_SECONDARY_CC || $paymethodType == PaymentMethodType::TYPE_CREDIT_CARD || $paymethodType == PaymentMethodType::TYPE_T1_PAYMENTS || $paymethodType == PaymentMethodType::TYPE_T1_PAYMENTS_SECONDARY_CC || $paymethodType == PaymentMethodType::TYPE_PAYARC) {
                return $this->reactivateSuspendedUserByCard($subscriptionPaymentMethodId, $discountCode, $amount, $userId, $orderConversionId);
            }
        } else {
            $this->setResponse(['error' => 0, 'msg' => 'Invalid Payment Method']);
            return $this->showResponse();
        }
    }

    private function reactivateByEwalletForSuspendedUser($subscriptionPaymentMethodId, $discountCode, $amount, $userId)
    {
        $user = User::select('*')->where('id', $userId)->first();
        $subscriptionProduct = Subscription::getSubscriptionProduct($user->id);
        $subscriptionAmount = $subscriptionProduct->price + Helper::getReactivationFee();

        $discountCode =  $discountCode ? $discountCode : '';
        $amount = $amount ? $amount : $subscriptionAmount;
        $discount = 0;

        if (!\utill::isNullOrEmpty($discountCode)) {
            $discount = DiscountCoupon::getDiscountAmount($discountCode);
            if ($discount == 0) {
                $this->setResponse(['error' => 1, 'msg' => "Invalid discount code", 'discountCode' => $discountCode]);
                return $this->showResponse();
            }
        }
        $amount = $amount - $discount;

        $checkEwalletBalance = User::select('*')->where('id', $user->id)->first();

        if ($checkEwalletBalance->estimated_balance < ($amount - $discount)) {
            $this->setResponse(['error' => 1, 'msg' => "Not enough e-wallet balance"]);
            return $this->showResponse();
        }

        $subscriptionProduct = Subscription::getSubscriptionProduct($user->id);

        $orderSubtotal = $subscriptionProduct->price + Helper::getReactivationFee();
        $orderTotal = (string)$amount;

        $orderBV = $subscriptionProduct->bv;
        $orderQV = $subscriptionProduct->qv;
        $orderCV = $subscriptionProduct->cv;

        // create new order
        $orderId = Order::addNew($userId, $orderSubtotal, $orderTotal, $orderBV, $orderQV, $orderCV, null, $subscriptionPaymentMethodId, null, null, null, $discountCode);

        // create new order item
        OrderItem::addNew($orderId, $subscriptionProduct->id, 1, $orderTotal, $orderBV, $orderQV, $orderCV);
        //

        EwalletTransaction::addPurchase($user->id, EwalletTransaction::REACTIVATE_SUBSCRIPTION, -$amount, $orderId);

        if (!empty($discountCode)) {
            DiscountCoupon::markAsUsed($user->id, $discountCode, "code", $orderId);
        }

        SaveOn::enableUser($user->current_product_id, $user->distid, $user->id);
        IDecide::enableUser($user->id);
        User::updateUserSitesStatus($user->id, 0, 0, 0);
        $userId = $user->id;
        $attemptDate = date('Y-m-d');
        $attemptCount = 1;
        $status = '1';
        $productId = $subscriptionProduct->id;
        $nextSubscriptionDate = SubscriptionHistory::getNextSubscriptionDate();
        $response = 'Reactivate subscription';

        SubscriptionHistory::UpdateSubscriptionHistoryOnly($userId, $attemptDate, $attemptCount, $status, $productId, $subscriptionPaymentMethodId, $nextSubscriptionDate, $response, 1);
        User::updateNextSubscriptionDate($userId, $nextSubscriptionDate);
        User::updateAccountStatusByUserId($userId, User::ACC_STATUS_APPROVED);

        $this->setResponse(['error' => 0, 'act' => 'subscription_reactive_success']);
        return $this->showResponse();
    }

    private function reactivateSuspendedUserByCard($subscriptionPaymentMethodId, $discountCode, $amount, $userId, $orderConversionId = null)
    {
        $user = User::getById($userId);
        $subscriptionProduct = Subscription::getSubscriptionProduct($user->id);
        $subscriptionAmount = $subscriptionProduct->price + Helper::getReactivationFee();

        $discountCode =  $discountCode ? $discountCode : '';
        $amount = $amount ? $amount : $subscriptionAmount;

        $discount = 0;

        if (!\utill::isNullOrEmpty($discountCode)) {
            $discount = DiscountCoupon::getDiscountAmount($discountCode);
            if ($discount == 0) {
                $this->setResponse(['error' => 1, 'msg' => "Invalid discount code", 'discountCode' => $discountCode]);
                return $this->showResponse();
            }
        }
        $amount = $amount - $discount;

        $paymentMethod = PaymentMethod::select('*')
            ->where('id', $subscriptionPaymentMethodId)
            ->where('userID', $user->id)
            ->first();

        /*echo $subscriptionPaymentMethodId;*/

        if (empty($paymentMethod)) {
            $this->setResponse(['error' => 1, 'msg' => "Invalid payment methods"]);
            return $this->showResponse();
        }

        $billingAddress = Address::find($paymentMethod->bill_addr_id);
        if (empty($billingAddress)) {
            $this->setResponse(['error' => 1, 'msg' => "Invalid billing address"]);
            return $this->showResponse();
        }

        //detokenize
        $tokenEx = new tokenexAPI();
        $tokenRes = $tokenEx->detokenizeLog(config('api_endpoints.TOKENEXDetokenize'), $paymentMethod->token);
        $tokenRes = $tokenRes['response'];
        if (!$tokenRes->Success) {
            $this->setResponse(['error' => 1, 'msg' => "TokenEx Error : " . $tokenRes->Error]);
            return $this->showResponse();
        }

        $orderSubtotal = $subscriptionProduct->price + Helper::getReactivationFee();
        $orderTotal = (string)$amount;
        $orderBV = $subscriptionProduct->bv;
        $orderQV = $subscriptionProduct->qv;
        $orderCV = $subscriptionProduct->cv;


        //Only use PayArc or T1
        if($paymentMethod->pay_method_type == 11){
            $nmiResult = NMIGateway::processPayment($tokenRes->Value, $paymentMethod->firstname, $paymentMethod->lastname, $paymentMethod->expMonth, $paymentMethod->expYear, $paymentMethod->cvv, $orderTotal, $billingAddress->address1, $billingAddress->city, $billingAddress->stateprov, $billingAddress->postalcode, $billingAddress->countrycode, 11, $orderConversionId);
        } else {
            //force T1
            //$nmiResult = \App\NMIGateway::processPayment($tokenRes->Value, $paymentMethod->firstname, $paymentMethod->lastname, $paymentMethod->expMonth, $paymentMethod->expYear, $paymentMethod->cvv, $orderTotal, $billingAddress->address1, $billingAddress->city, $billingAddress->stateprov, $billingAddress->postalcode, $billingAddress->countrycode, $paymentMethod->pay_method_type);
            $nmiResult = NMIGateway::processPayment($tokenRes->Value, $paymentMethod->firstname, $paymentMethod->lastname, $paymentMethod->expMonth, $paymentMethod->expYear, $paymentMethod->cvv, $orderTotal, $billingAddress->address1, $billingAddress->city, $billingAddress->stateprov, $billingAddress->postalcode, $billingAddress->countrycode, 9, $orderConversionId);
        }

        if ($nmiResult['error'] == 1) {
            $this->setResponse(['error' => 1, 'msg' => "Payment Failed:<br/>" . $nmiResult['msg']]);
            return $this->showResponse();
        } else {
            // place order
            $authorization = $nmiResult['authorization'];

            // create new order
            $orderId = Order::addNew($user->id, $orderSubtotal, $orderTotal, $orderBV, $orderQV, $orderCV, $authorization, $paymentMethod->id, null, null, null, $discountCode);

            if ($orderConversionId) {
                OrderConversion::setOrderId($orderConversionId, $orderId);
            }

            // create new order item
            OrderItem::addNew($orderId, $subscriptionProduct->id, 1, $orderTotal, $orderBV, $orderQV, $orderCV);
            //
            if (!empty($discountCode)) {
                DiscountCoupon::markAsUsed($user->id, $discountCode, "code", $orderId);
            }
            //

            SaveOn::enableUser($user->current_product_id, $user->distid, $user->id);
            IDecide::enableUser($user->id);

            User::updateUserSitesStatus($user->id, 0, 0, 0);

            $userId = $user->id;
            $attemptDate = date('Y-m-d');
            $attemptCount = 1;
            $status = '1';
            $productId = $subscriptionProduct->id;
            $nextSubscriptionDate = SubscriptionHistory::getNextSubscriptionDate();
            $response = 'Reactivate subscription';

            SubscriptionHistory::UpdateSubscriptionHistoryOnly($userId, $attemptDate, $attemptCount, $status, $productId, $subscriptionPaymentMethodId, $nextSubscriptionDate, $response, 1);
            User::updateNextSubscriptionDate($userId, $nextSubscriptionDate);
            User::updateAccountStatusByUserId($userId, User::ACC_STATUS_APPROVED);

            $this->setResponse(['error' => 0, 'act' => 'subscription_reactive_success']);
            return $this->showResponse();
        }
    }

    public function addNewCardSubscriptionSuspendedUserReactivate()
    {
        $user = User::getById(Auth::user()->id);
        $vali = $this->validateAddCard();
        if ($vali['valid'] == 0) {
            $this->setResponse(['error' => 1, 'msg' => $vali['msg']]);
            return $this->showResponse();
        }
        $user = User::getById(Auth::user()->id);
        if (empty($user)) {
            $this->setResponse(['error' => 1, 'msg' => "User couldn't found"]);
            return $this->showResponse();
        }
        $subscriptionProduct = Subscription::getSubscriptionProduct($user->id);
        $subscriptionAmount = $subscriptionProduct->price + Helper::getReactivationFee();

        $req = request();

        $discountCode = $req->discount_code ? $req->discount_code : '';
        $amount = $req->amount ? $req->amount : $subscriptionAmount;

        $discount = 0;

        if (!\utill::isNullOrEmpty($discountCode)) {
            $discount = DiscountCoupon::getDiscountAmount($discountCode);
            if ($discount == 0) {
                $this->setResponse(['error' => 1, 'msg' => "Invalid discount code", 'discountCode' => $discountCode]);
                return $this->showResponse();
            }
        }
        $amount = $amount - $discount;

        $paymentMethodType = PaymentMethodType::TYPE_CREDIT_CARD;
        if (Helper::checkTMTAllowPayment($req->countrycode,$user->id) > 0) {
            $paymentMethodType = PaymentMethodType::TYPE_T1_PAYMENTS;
        }
        if ($paymentMethodType != PaymentMethodType::TYPE_T1_PAYMENTS) {
            // kount
            $kount = new Kount();
            $uniqueId = md5($user->email . time());
            $kountResponse = $kount->RequestInquiry($req, $amount, $user->email, $user->phonenumber, $subscriptionProduct, $uniqueId, $req->sessionId);
            //
            if (!$kountResponse['success']) {
                $this->setResponse(['error' => 1, 'msg' => "Payment Failed:<br/>" . $kountResponse['message']]);
                return $this->showResponse();
            }
        }
        $tokenExResult = PaymentMethod::generateTokenEx($req->number);
        if ($tokenExResult['error'] == 1) {
            $this->setResponse(['error' => 1, 'msg' => "Invalid card number<br/>" . $tokenExResult['msg']]);
            return $this->showResponse();
        }
        $tokenEx = $tokenExResult['token'];
        $cardAlreadyExists = PaymentMethod::checkCardAlreadyExists($user->id, $tokenEx);

        if ($cardAlreadyExists) {
            $this->setResponse(['error' => 1, 'msg' => "Card already exists"]);
            return $this->showResponse();
        }

        $expiry_date = $req->expiry_date;
        $temp = explode("/", $expiry_date);

        $orderConversionId = isset($req['order_conversion_id']) ? $req['order_conversion_id'] : null;
        $nmiResult = NMIGateway::processPayment($req->number, $req->first_name, $req->last_name, $temp[0], $temp[1], $req->cvv, $amount, $req->address1, $req->city, $req->stateprov, $req->postalcode, $req->countrycode, $paymentMethodType, $orderConversionId);
        $sessionId = $req->sessionId;
        if ($nmiResult['error'] == 1) {
            if ($paymentMethodType != PaymentMethodType::TYPE_T1_PAYMENTS) {
                $kount->RequestUpdate($sessionId, $kountResponse['transaction_id'], 'D');
            }
            $this->setResponse(['error' => 1, 'msg' => "Payment Failed:<br/>" . $nmiResult['msg']]);
            return $this->showResponse();
        }
        if ($paymentMethodType != PaymentMethodType::TYPE_T1_PAYMENTS) {
            $kount->RequestUpdate($sessionId, $kountResponse['transaction_id'], 'A');
        }
        $authorization = $nmiResult['authorization'];
        //check address already exists
        $hasPrimaryAddress = Address::getRec($user->id, Address::TYPE_BILLING, 1);
        if (empty($hasPrimaryAddress)) {
            $addressId = Address::addNewRecSecondaryAddress($user->id, Address::TYPE_BILLING, 1, $req);
        } else {
            $addressId = Address::addNewRecSecondaryAddress($user->id, Address::TYPE_BILLING, 0, $req);
        }

        $hasPaymentMethod = PaymentMethod::getAllRec($user->id, $paymentMethodType);
        if (empty($hasPaymentMethod)) {
            $paymentMethodId = PaymentMethod::addSecondaryCard($user->id, 1, $tokenEx, $addressId, $paymentMethodType, $req);
        } else {
            $paymentMethodId = PaymentMethod::addSecondaryCard($user->id, 0, $tokenEx, $addressId, $paymentMethodType, $req);
        }

        // create new order
        $orderSubtotal = $subscriptionProduct->price + Helper::getReactivationFee();
        $orderTotal = (string)$amount;
        $orderBV = $subscriptionProduct->bv;
        $orderQV = $subscriptionProduct->qv;
        $orderCV = $subscriptionProduct->cv;

        $orderId = Order::addNew($user->id, $orderSubtotal, $orderTotal, $orderBV, $orderQV, $orderCV, $authorization, $paymentMethodId, null, null, null, $discountCode);
        $OrderItem = OrderItem::addNew($orderId, $subscriptionProduct->id, 1, $orderTotal, $orderBV, $orderQV, $orderCV);
        if (!empty($discountCode)) {
            DiscountCoupon::markAsUsed($user, $discountCode, "code", $orderId);
        }
        SaveOn::enableUser($user->current_product_id, $user->distid, $user->id);
        IDecide::enableUser($user->id);

        User::updateUserSitesStatus($user->id, 0, 1, 0);

        $userId = $user->id;
        $attemptDate = date('Y-m-d');
        $attemptCount = 1;
        $status = 1;
        $productId = $subscriptionProduct->id;
        $nextSubscriptionDate = SubscriptionHistory::getNextSubscriptionDate();
        $response = 'Reactivate subscription';

        SubscriptionHistory::UpdateSubscriptionHistoryOnly($userId, $attemptDate, $attemptCount, $status, $productId, $paymentMethodId, $nextSubscriptionDate, $response, 1);
        User::updateNextSubscriptionDate($userId, $nextSubscriptionDate);
        User::updateAccountStatusByUserId($userId, User::ACC_STATUS_APPROVED);

        $this->setResponse(['error' => 0, 'act' => 'subscription_reactive_success']);
        return $this->showResponse();
    }

    public function reactivateSubscription()
    {
        $request = request();
        session_start();
        $subscriptionPaymentMethodId = $request['payment_method_id'];
        $discountCode = $request['discount_code'];
        $amount = $request['amount'];

        // check discount code
        $sesData = $this->validateReactivationData($request);
        if ($sesData['error'] == 1) {
            $this->setResponse(['error' => 0, 'msg' => 'Invalid data', 'response' => $sesData]);
            return $this->showResponse();
        }

        $product = Subscription::getSubscriptionProduct(Auth::user()->id);
        $amount = $product->price - $sesData['discount'];
        if ($amount <= 0) {
            // return Helper::paymentUsingCouponCode($sesData, $product, 'UPGRADE_PACKAGE', $req['order_conversion_id']);
        }

        //Remove these 2 lines to make it work
        // $this->setResponse(['error' => 1, 'msg' => "NOT AN ISSUE< JUST PREVENT TO PROCESS FOR NOW."]);
        // return $this-subscription>showResponse();
        $paymentMethod = PaymentMethod::find($subscriptionPaymentMethodId);
        if (!$paymentMethod) {
            $this->setResponse(['error' => 0, 'msg' => 'Invalid Payment Method']);
            return $this->showResponse();
        }
        $paymethodType = $paymentMethod->pay_method_type;
        $orderConversionId = isset($request['order_conversion_id']) ? $request['order_conversion_id'] : null;
        if ($paymethodType == PaymentMethodType::TYPE_E_WALET) {
            return $this->reactivateByEwallet($subscriptionPaymentMethodId, $discountCode, $amount, $orderConversionId);
        } else if ( $paymethodType == PaymentMethodType::TYPE_METROPOLITAN || $paymethodType == PaymentMethodType::TYPE_SECONDARY_CC || $paymethodType == PaymentMethodType::TYPE_CREDIT_CARD || $paymethodType == PaymentMethodType::TYPE_T1_PAYMENTS || $paymethodType == PaymentMethodType::TYPE_T1_PAYMENTS_SECONDARY_CC || $paymethodType == PaymentMethodType::TYPE_PAYARC) {
            return $this->reactivateByCard($subscriptionPaymentMethodId, $discountCode, $amount, $orderConversionId);
        }else{
            $this->setResponse(['error' => 0, 'msg' => 'Invalid Payment Method']);
            return $this->showResponse();
        }

    }

    private function validateReactivationData($request) {
        $data = $request->all();

        $req = request();
        $validator = Validator::make($request->all(), [
            'payment_method_id' => 'required|numeric',
            'order_conversion_id' => 'required|numeric',
            'amount' => 'required|numeric'
        ]);

        $msg = "";
        if ($validator->fails()) {
            $messages = $validator->messages();
            foreach ($messages->all() as $m) {
                $msg = $m;
            }

            return ['error' => 1, 'msg' => $msg];
        }
        $discountCode = $data['discount_code'];
        $discount = 0;

        if (!Util::isNullOrEmpty($discountCode)) {
            $discount = DiscountCoupon::getDiscountAmount($discountCode);
            if ($discount == 0) {
                return ['error' => 1, 'msg' => "Invalid discount code"];
            }
        }

        if(!OrderConversion::find($data['order_conversion_id'])){
            return ['error' => 1, 'msg' => "Order Conversion ID not found or expired. Please refresh the page and try again"];
        }
        $orderConversionId = isset($request->order_conversion_id) ? $request->order_conversion_id : null;
        return [
            'error' => 0,
            'discountCode' => $discountCode,
            'discount' => $discount,
            'sessionId' => Str::random(32),
            'orderConversionId' => $orderConversionId
        ];
    }

    private function reactivateByEwallet($subscriptionPaymentMethodId, $discountCode, $amount, $orderConversionId = null)
    {
        $subscriptionProduct = Subscription::getSubscriptionProduct(Auth::user()->id);
        $subscriptionAmount = $subscriptionProduct->price + Helper::getReactivationFee();

        $discountCode = $discountCode ? $discountCode : '';
        $amount = $amount ? $amount : $subscriptionAmount;

        $discount = 0;

        if (!\utill::isNullOrEmpty($discountCode)) {
            $discount = DiscountCoupon::getDiscountAmount($discountCode);
            if ($discount == 0) {
                $this->setResponse(['error' => 1, 'msg' => "Invalid discount code", 'discountCode' => $discountCode]);
                return $this->showResponse();
            }
        }

        $checkEwalletBalance = User::select('*')->where('id', Auth::user()->id)->first();

        if ($checkEwalletBalance->estimated_balance < ($amount - $discount)) {
            $this->setResponse(['error' => 1, 'msg' => "Not enough e-wallet balance"]);
            return $this->showResponse();
        }

        $amount = $amount - $discount;

        $subscriptionProduct = Subscription::getSubscriptionProduct(Auth::user()->id);

        if($amount <= 0){
            $amount = 0;
        }

        $orderSubtotal = $subscriptionProduct->price + Helper::getReactivationFee();
        $orderTotal = (string)$amount;
        $orderBV = $subscriptionProduct->bv;
        $orderQV = $subscriptionProduct->qv;
        $orderCV = $subscriptionProduct->cv;

        // create new order
        $orderId = Order::addNew(Auth::user()->id, $orderSubtotal, $orderTotal, $orderBV, $orderQV, $orderCV, null, $subscriptionPaymentMethodId, null, null, null, $discountCode);
        // create new order item
        OrderItem::addNew($orderId, $subscriptionProduct->id, 1, $orderTotal, $orderBV, $orderQV, $orderCV);

        //Creating conversion record
        if ($orderConversionId && isset($orderId)) {
            OrderConversion::setOrderId($orderConversionId, $orderId);
        }

        if($amount > 0){
            EwalletTransaction::addPurchase(Auth::user()->id, EwalletTransaction::REACTIVATE_SUBSCRIPTION, -$amount, $orderId);
        }


        if (!empty($discountCode)) {
            DiscountCoupon::markAsUsed(Auth::user()->id, $discountCode, "code", $orderId);
        }

        SaveOn::enableUser(Auth::user()->current_product_id, Auth::user()->distid, Auth::user()->id);
        IDecide::enableUser(Auth::user()->id);

        User::updateUserSitesStatus(Auth::user()->id, 0, 0, 0);

        $userId = Auth::user()->id;
        $attemptDate = date('Y-m-d');
        $attemptCount = 1;
        $status = '1';
        $productId = $subscriptionProduct->id;
        $nextSubscriptionDate = SubscriptionHistory::getNextSubscriptionDate();
        $response = 'Reactivate subscription';

        SubscriptionHistory::UpdateSubscriptionHistoryOnly($userId, $attemptDate, $attemptCount, $status, $productId, $subscriptionPaymentMethodId, $nextSubscriptionDate, $response, 1);
        User::updateNextSubscriptionDate($userId, $nextSubscriptionDate);

        $this->setResponse(['error' => 0, 'msg' => 'Account reactivated', 'act' => 'ewallet', 'nd' => $nextSubscriptionDate]);
        return $this->showResponse();
    }

    private function reactivateByCard($subscriptionPaymentMethodId, $discountCode, $amount, $orderConversionId)
    {

        $subscriptionProduct = Subscription::getSubscriptionProduct(Auth::user()->id);
        $subscriptionAmount = $subscriptionProduct->price + Helper::getReactivationFee();

        $discountCode = $discountCode ? $discountCode : '';
        $amount = $amount ? $amount : $subscriptionAmount;

        $discount = 0;

        if (!\utill::isNullOrEmpty($discountCode)) {
            $discount = DiscountCoupon::getDiscountAmount($discountCode);
            if ($discount == 0) {
                $this->setResponse(['error' => 1, 'msg' => "Invalid discount code", 'discountCode' => $discountCode]);
                return $this->showResponse();
            }
        }

        $amount = $amount - $discount;

        $paymentMethod = PaymentMethod::select('*')
            ->where('id', $subscriptionPaymentMethodId)
            ->where('userID', Auth::user()->id)
            ->first();

        /*echo $subscriptionPaymentMethodId;*/

        if (empty($paymentMethod)) {
            $this->setResponse(['error' => 1, 'msg' => "Invalid payment methods"]);
            return $this->showResponse();
        }

        $billingAddress = Address::find($paymentMethod->bill_addr_id);
        if (empty($billingAddress)) {
            $this->setResponse(['error' => 1, 'msg' => "Invalid billing address"]);
            return $this->showResponse();
        }

        //detokenize
        $tokenEx = new tokenexAPI();
        $tokenRes = $tokenEx->detokenizeLog(config('api_endpoints.TOKENEXDetokenize'), $paymentMethod->token);
        $tokenRes = $tokenRes['response'];
        if (!$tokenRes->Success) {
            $this->setResponse(['error' => 1, 'msg' => "TokenEx Error : " . $tokenRes->Error]);
            return $this->showResponse();

        }
        $orderSubtotal = $subscriptionProduct->price + Helper::getReactivationFee();
        $orderTotal = (string)$amount;

        $orderBV = $subscriptionProduct->bv;
        $orderQV = $subscriptionProduct->qv;
        $orderCV = $subscriptionProduct->cv;

        //Only use PayArc or T1
        if($paymentMethod->pay_method_type == 11){
            $nmiResult = NMIGateway::processPayment($tokenRes->Value, $paymentMethod->firstname, $paymentMethod->lastname, $paymentMethod->expMonth, $paymentMethod->expYear, $paymentMethod->cvv, $orderTotal, $billingAddress->address1, $billingAddress->city, $billingAddress->stateprov, $billingAddress->postalcode, $billingAddress->countrycode, 11, $orderConversionId);
        } else {
            //force T1
            //$nmiResult = \App\NMIGateway::processPayment($tokenRes->Value, $paymentMethod->firstname, $paymentMethod->lastname, $paymentMethod->expMonth, $paymentMethod->expYear, $paymentMethod->cvv, $orderTotal, $billingAddress->address1, $billingAddress->city, $billingAddress->stateprov, $billingAddress->postalcode, $billingAddress->countrycode, $paymentMethod->pay_method_type);
            $nmiResult = NMIGateway::processPayment($tokenRes->Value, $paymentMethod->firstname, $paymentMethod->lastname, $paymentMethod->expMonth, $paymentMethod->expYear, $paymentMethod->cvv, $orderTotal, $billingAddress->address1, $billingAddress->city, $billingAddress->stateprov, $billingAddress->postalcode, $billingAddress->countrycode, 9, $orderConversionId);
        }

        if ($nmiResult['error'] == 1) {
            $this->setResponse(['error' => 1, 'msg' => "Payment Failed:<br/>" . $nmiResult['msg'], 'object' => $nmiResult ]);
            return $this->showResponse();
        } else {
            // place order
            $authorization = $nmiResult['authorization'];
            // create new order
            $orderId = Order::addNew(Auth::user()->id, $orderSubtotal, $orderTotal, $orderBV, $orderQV, $orderCV, $authorization, $paymentMethod->id, null, null, null, $discountCode);
            // create new order item
            OrderItem::addNew($orderId, $subscriptionProduct->id, 1, $orderTotal, $orderBV, $orderQV, $orderCV);
            //
            if (!empty($discountCode)) {
                DiscountCoupon::markAsUsed(Auth::user()->id, $discountCode, "code", $orderId);
            }

            //Creating conversion record
            if ($orderConversionId && isset($orderId)) {
                OrderConversion::setOrderId($orderConversionId, $orderId);
            }

            SaveOn::enableUser(Auth::user()->current_product_id, Auth::user()->distid, Auth::user()->id);
            IDecide::enableUser(Auth::user()->id);

            User::updateUserSitesStatus(Auth::user()->id, 0, 0, 0);

            $userId = Auth::user()->id;
            $attemptDate = date('Y-m-d');
            $attemptCount = 1;
            $status = '1';
            $productId = $subscriptionProduct->id;
            $nextSubscriptionDate = SubscriptionHistory::getNextSubscriptionDate();
            $response = 'Reactivate subscription';

            SubscriptionHistory::UpdateSubscriptionHistoryOnly($userId, $attemptDate, $attemptCount, $status, $productId, $subscriptionPaymentMethodId, $nextSubscriptionDate, $response, 1);
            User::updateNextSubscriptionDate($userId, $nextSubscriptionDate);

            $this->setResponse(['error' => 0, 'msg' => 'Account reactivated', 'act' => 'card', 'nd' => $nextSubscriptionDate]);
            return $this->showResponse();
        }
    }

    public function dlgSubscriptionReactivate()
    {
        session_start();

        //$d['countries'] = \App\Country::getAll();
        $d['sessionId'] = session_id();
        $d['payment_method'] = $this->getPaymentMethods();

        $subscriptionProduct = Subscription::getSubscriptionProduct(Auth::user()->id);
        $d['subscription_amount'] = $subscriptionProduct->price;
        $d['total'] = $subscriptionProduct->price + Helper::getReactivationFee();
        $d['subscription_fee'] = Product::getById(Product::ID_REACTIVATION_PRODUCT);
        $d['coupon_code'] = session()->has('reactivateSubscriptionCouponCode') ? session('reactivateSubscriptionCouponCode') : '';

        $this->setResponse($d);
        $this->setResponseCode(200);

        return $this->showResponse();
    }

    private function getPaymentMethods()
    {
        $userId = Auth::id();
        $this->createEwalletIfNotPresent($userId);
        $user = User::find($userId);
        $paymentMethods = PaymentMethod::getAllRec($userId);

        $pMDrop = array();

        $ignoredPaymentMethods = [
            PaymentMethodType::TYPE_ADMIN,
            PaymentMethodType::TYPE_BITPAY,
            PaymentMethodType::TYPE_SKRILL,
            PaymentMethodType::TYPE_COUPON_CODE
        ];

        foreach ($paymentMethods as $p) {
            $selected = '';
            if ($p->is_deleted == true || in_array($p->pay_method_type, $ignoredPaymentMethods)) {
                continue;
            }

            if ($p->pay_method_type == PaymentMethodType::TYPE_E_WALET) {
                $paymentMethodName = 'E-WALLET';
            } else {
                if (empty($p->token)) {
                    continue;
                }

                $paymentMethodName = 'Credit Card - ' . PaymentMethod::getFormatedCardNo($p->token);
            }

            if ( $user->subscription_payment_method_id == $p->id ) {
                $selected = ' selected ';
            }

            if(!empty($paymentMethodName)){
                $pMDrop[] = ['id' => $p->id, 'paymentMethodName'=> $paymentMethodName, 'selected' => $selected];
            }
        }
        return $pMDrop;
    }

    private function getUserPaymentMethods()
    {
        $userId = Auth::id();
        //$this->createEwalletIfNotPresent($userId);
        $user = User::find($userId);
        $paymentMethods = UserPaymentMethod::whereUserId($userId);

        //dd($paymentMethods);

        $pMDrop = array();

        $ignoredPaymentMethods = [
            PaymentMethodType::TYPE_ADMIN,
            PaymentMethodType::TYPE_BITPAY,
            PaymentMethodType::TYPE_SKRILL,
            PaymentMethodType::TYPE_COUPON_CODE
        ];

        foreach ($paymentMethods as $p) {
            $selected = '';
            if ($p->is_deleted == true || in_array($p->pay_method_type, $ignoredPaymentMethods)) {
                continue;
            }

            if ($p->pay_method_type == PaymentMethodType::TYPE_E_WALET) {
                $paymentMethodName = 'E-WALLET';
            } else {
                if (empty($p->token)) {
                    continue;
                }

                $paymentMethodName = 'Credit Card - ' . PaymentMethod::getFormatedCardNo($p->token);
            }

            if ($user->subscription_payment_method_id == $p->id) {
                $selected = ' selected ';
            }

            if (!empty($paymentMethodName)) {
                $pMDrop[] = ['id' => $p->id, 'paymentMethodName' => $paymentMethodName, 'selected' => $selected];
            }
        }
        return $pMDrop;
    }

    private function createEwalletIfNotPresent($userId)
    {
        $ewalletExists = PaymentMethod::where('userID', '=', $userId)
            ->where('pay_method_type', '=', PaymentMethodType::TYPE_E_WALET)
            ->where(function ($q) {
                $q->where('is_deleted', '=', 0)->orWhereNull('is_deleted');
            })
            ->exists();

        if (!$ewalletExists) {
            PaymentMethod::addNewCustomPaymentMethod([
                'userID' => $userId,
                'created_at' => \utill::getCurrentDateTime(),
                'updated_at' => \utill::getCurrentDateTime(),
                'pay_method_type' => PaymentMethodType::TYPE_E_WALET
            ]);
        }
    }

    public function saveSubscription()
    {
        $vali = $this->validateRec();
        if ($vali['valid'] == 0) {
            $this->setResponseCode(400);
            $this->setResponse(['error' => 1, 'msg' => $vali['msg']]);
            return $this->showResponse();
        }

        $subscriptionPlan = Subscription::getCurrentSubscriptionPlan(Auth::user()->id);
        if (!$subscriptionPlan) {
            $this->setResponse(['error' => 1, 'msg' => $vali['msg']]);
            return $this->showResponse();
        }

        $req = request();
        $nextSubscriptionDate = Carbon::parse($req['next_subscription_date']);
        $date = date('d', strtotime($req['next_subscription_date']));
        if ($date > 25) {
            $this->setResponse(['error' => 1, 'msg' =>'Date should less than or equal to 25']);
            return $this->showResponse();
        }

        $nextSubscriptionDate = date("Y-m-d", strtotime($req['next_subscription_date']));
        if ($nextSubscriptionDate < date("Y-m-d")) {
            $this->setResponse(['error' => 1, 'msg' => 'Next subscription date cannot be less than current date']);
            return $this->showResponse();
        }

        $original_subscription_date = date("Y-m-d", strtotime(Auth::user()->original_subscription_date));
        if ($original_subscription_date > $nextSubscriptionDate) {
            $this->setResponse(['error' => 1, 'msg' => 'Invalid Date']);
            return $this->showResponse();
        }

        $now = Carbon::now();
        $diff = $now->diffInMonths($nextSubscriptionDate, false);
        if ($diff >= 2) {
            $this->setResponse(['error' => 1, 'msg' => 'Invalid Date']);
            return $this->showResponse();
        }

        Subscription::updateSubscription(Auth::user()->id, $req->except('_token'));

        $this->setResponseCode(200);
        $this->setResponse(['error' => 0, 'msg' => 'Updated']);
        return $this->showResponse();

    }

    public function checkCouponCode()
    {
        $request = request();
        $userId = Auth::user()->id;
        $discountCode = $request['coupon'];

        $d['coupon_code'] = $discountCode;
        $d['payment_method'] = $this->getPaymentMethods();

        $subscriptionProduct = Subscription::getSubscriptionProduct($userId);
        $d['subscription_amount'] = $subscriptionProduct->price;
        $convertObject = CurrencyConverter::convertCurrency(number_format($subscriptionProduct->price,2,'',''), $request['country'], $request['locale']);

        $d['subscription_amount_display'] = $convertObject["display_amount"];
        $d['subscription_amount'] = $subscriptionProduct->price;
        $d['subscription_fee'] = Product::getById(Product::ID_REACTIVATION_PRODUCT);
        $convertObject = CurrencyConverter::convertCurrency(number_format($d['subscription_fee']->price,2,'',''), $request['country'], $request['locale']);
        $d['subscription_fee']->price = $convertObject["display_amount"];

        $discount = 0;
        if (!\utill::isNullOrEmpty($discountCode)) {
            $discount = DiscountCoupon::getDiscountAmount($discountCode);
            if ($discount == 0) {
                $this->setResponse(['error' => 1, 'msg' => "Invalid discount code", 'd' => $d]);
                return $this->showResponse();
            }
        } else {
            $this->setResponse(['error' => 1, 'msg' => "Invalid discount code", 'd' => $d]);
            return $this->showResponse();
        }

        $total = ($subscriptionProduct->price + Helper::getReactivationFee()) - $discount;

        if ($total <= 0) {
            $total = 0;
        }

        if($total==0){
            $convertObject = CurrencyConverter::convertCurrency(0, $request['country'], $request['locale']);
        }else{
            $convertObject = CurrencyConverter::convertCurrency(number_format($total,2,'',''), $request['country'], $request['locale']);
        }

        $total_display = $convertObject["display_amount"];

        $d['total'] = $total;
        $d['total_display'] = $total_display;

        $orderConversion = new OrderConversion();

        if(!isset($request['country'])){
            $country = "US";
        }else{
            $country = $request['country'];
        }

        $orderConversion->fill([
            'session_id' => session_id(),
            'original_amount' => number_format($subscriptionProduct->price,2,'',''),
            'original_currency' => "USD",
            'converted_amount' => $convertObject["amount"],
            'converted_currency' => $convertObject['currency'],
            'exchange_rate' => $convertObject['exchange_rate'],
            'expires_at' => now()->addMinutes(30),
            'display_amount' => $convertObject['display_amount']
        ]);

        $orderConversion->save();

        $d['order_conversion_id'] = $orderConversion->id;
        $d['expiration'] = $orderConversion->expires_at->timestamp;

        $this->setResponseCode(200);
        $this->setResponse(['error' => 0, 'msg' => 'Valid discount code', 'd' => $d]);
        return $this->showResponse();
    }

    public function getGracePeriod()
    {
        $req = request();

        $nextSubscriptionDate = Carbon::parse($req['next_subscription_date']);
        $originalSubscriptionDate = Carbon::parse(Auth::user()->original_subscription_date);

        $diff = $originalSubscriptionDate->diffInDays($nextSubscriptionDate, false);

        if ($diff > 7) {
            $this->setResponse([
                'original_subscription_date' => Auth::user()->original_subscription_date,
                '$diff' => $diff,
                'alert' => 1,
                'title' => 'Grace period passed',
                'text' => 'The date you have selected is outside of the current pay period or month. This could potentially affect your active status. Please confirm your selection or choose another date.',
                'type' => 'warning'
            ]);
            return $this->showResponse();
        }

        $this->setResponseCode(200);
        $this->setResponse(['alert' => 0, '$diff' => $diff]);
        return $this->showResponse();
    }

    private function validateRec()
    {
        $req = request();
        if ($req['subscription_payment_method_id'] == 0) {
            $req['subscription_payment_method_id'] = '';
        }

        $validator = Validator::make($req->all(), [
            'next_subscription_date' => 'required|date',
            'subscription_payment_method_id' => 'required'
        ], [
            'next_subscription_date.required' => 'Subscription date is required',
            'subscription_payment_method_id.required' => 'Payment method required'
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

    public function addNewCard()
    {
        $vali = $this->validateAddCard();
        if ($vali['valid'] == 0) {
            $this->setResponse(['error' => 1, 'msg' => $vali['msg']]);
            return $this->showResponse();
        }
        $subscriptionProduct = Subscription::getSubscriptionProduct(Auth::user()->id);
        if (!$subscriptionProduct) {
            $this->setResponse(['error' => 1, 'msg' => "Subscription product not found"]);
            return $this->showResponse();
        }
        $payInCard = $subscriptionProduct->price;
        $req = request();

        $paymentMethodType = PaymentMethodType::TYPE_T1_PAYMENTS;

        $tokenExResult = PaymentMethod::generateTokenEx($req->number);
        if ($tokenExResult['error'] == 1) {
            $this->setResponse(['error' => 1, 'msg' => "Invalid card number<br/>" . $tokenExResult['msg']]);
            return $this->showResponse();
        }

        $tokenEx = $tokenExResult['token'];
        $cardAlreadyExists = PaymentMethod::checkCardAlreadyExists(Auth::user()->id, $tokenEx);
        if ($cardAlreadyExists) {
            $this->setResponse(['error' => 1, 'msg' => "Card already exists"]);
            return $this->showResponse();

        }
        $addressId = Address::addSecondaryAddress(Auth::user()->id, Address::TYPE_BILLING, $req);
        $paymentMethodId = PaymentMethod::addNewRec(Auth::user()->id, 1, $tokenEx, $addressId, $paymentMethodType, $req);

        $this->setResponse([
            'error' => 0,
            'msg' => 'New card added',
            'payment_method_id' => $paymentMethodId,
            'card_name' => 'Credit Card - ' . PaymentMethod::getFormatedCardNo($tokenEx)
        ]);
        return $this->showResponse();
    }

    public function addNewCardSubscriptionReactivate()
    {
        $vali = $this->validateAddCard();
        if ($vali['valid'] == 0) {
            $this->setResponse(['error' => 1, 'msg' => $vali['msg']]);
            return $this->showResponse();
        }
        $subscriptionProduct = Subscription::getSubscriptionProduct(Auth::user()->id);
        $subscriptionAmount = $subscriptionProduct->price + Helper::getReactivationFee();

        $req = request();

        $discountCode = $req->discount_code ? $req->discount_code : '';
        $amount = $req->amount ? $req->amount : $subscriptionAmount;

        $paymentMethodType = PaymentMethodType::TYPE_CREDIT_CARD;
        if (Helper::checkTMTAllowPayment($req->countrycode,Auth::user()->id) > 0) {
            $paymentMethodType = PaymentMethodType::TYPE_T1_PAYMENTS;
        }
        if ($paymentMethodType != PaymentMethodType::TYPE_T1_PAYMENTS) {
            // kount
            $kount = new Kount();
            $uniqueId = md5(Auth::user()->email . time());
            $kountResponse = $kount->RequestInquiry($req, $amount, Auth::user()->email, Auth::user()->phonenumber, $subscriptionProduct, $uniqueId, $req->sessionId);
            //
            if (!$kountResponse['success']) {
                $this->setResponse(['error' => 1, 'msg' => "Payment Failed:<br/>" . $kountResponse['message']]);
                return $this->showResponse();
            }
        }
        $tokenExResult = PaymentMethod::generateTokenEx($req->number);
        if ($tokenExResult['error'] == 1) {
            $this->setResponse(['error' => 1, 'msg' => "Invalid card number<br/>" . $tokenExResult['msg']]);
            return $this->showResponse();
        }

        $tokenEx = $tokenExResult['token'];
        $cardAlreadyExists = PaymentMethod::checkCardAlreadyExists(Auth::user()->id, $tokenEx);

        if ($cardAlreadyExists) {
            $this->setResponse(['error' => 1, 'msg' => "Card already exists"]);
            return $this->showResponse();
        }

        $expiry_date = $req->expiry_date;
        $temp = explode("/", $expiry_date);
        $orderConversionId = isset($req->order_conversion_id) ? $req->order_conversion_id : null;
        $nmiResult = NMIGateway::processPayment($req->number, $req->first_name, $req->last_name, $temp[0], $temp[1], $req->cvv, $amount, $req->address1, $req->city, $req->stateprov, $req->postalcode, $req->countrycode, $paymentMethodType, $orderConversionId);
        $sessionId = $req->sessionId;
        if ($nmiResult['error'] == 1) {
            if ($paymentMethodType != PaymentMethodType::TYPE_T1_PAYMENTS) {
                $kount->RequestUpdate($sessionId, $kountResponse['transaction_id'], 'D');
            }

            $this->setResponse(['error' => 1, 'msg' => "Payment Failed:<br/>" . $nmiResult['msg']]);
            return $this->showResponse();
        }
        if ($paymentMethodType != PaymentMethodType::TYPE_T1_PAYMENTS) {
            $kount->RequestUpdate($sessionId, $kountResponse['transaction_id'], 'A');
        }
        $authorization = $nmiResult['authorization'];
        //check address already exists
        $hasPrimaryAddress = Address::getRec(Auth::user()->id, Address::TYPE_BILLING, 1);
        if (empty($hasPrimaryAddress)) {
            $addressId = Address::addNewRecSecondaryAddress(Auth::user()->id, Address::TYPE_BILLING, 1, $req);
        } else {
            $addressId = Address::addNewRecSecondaryAddress(Auth::user()->id, Address::TYPE_BILLING, 0, $req);
        }

        $hasPaymentMethod = PaymentMethod::getAllRec(Auth::user()->id, $paymentMethodType);
        if (empty($hasPaymentMethod)) {
            $paymentMethodId = PaymentMethod::addSecondaryCard(Auth::user()->id, 1, $tokenEx, $addressId, $paymentMethodType, $req);
        } else {
            $paymentMethodId = PaymentMethod::addSecondaryCard(Auth::user()->id, 0, $tokenEx, $addressId, $paymentMethodType, $req);
        }
        // create new order
        $orderSubtotal = $subscriptionProduct->price + Helper::getReactivationFee();
        $orderTotal = (string)$amount;
        $orderBV = $subscriptionProduct->bv;
        $orderQV = $subscriptionProduct->qv;
        $orderCV = $subscriptionProduct->cv;

        $orderId = Order::addNew(Auth::user()->id, $orderSubtotal, $orderTotal, $orderBV, $orderQV, $orderCV, $authorization, $paymentMethodId, null, null, null, $discountCode);
        // create new order item
        $OrderItem = OrderItem::addNew($orderId, $subscriptionProduct->id, 1, $orderTotal, $orderBV, $orderQV, $orderCV);
        //
        //Creating conversion record

        if ($orderConversionId && isset($orderId)) {
            OrderConversion::setOrderId($orderConversionId, $orderId);
        }

        if (!empty($discountCode)) {
            DiscountCoupon::markAsUsed(Auth::user()->id, $discountCode, "code", $orderId);
        }

        SaveOn::enableUser(Auth::user()->current_product_id, Auth::user()->distid, Auth::user()->id);
        IDecide::enableUser(Auth::user()->id);

        User::updateUserSitesStatus(Auth::user()->id, 0, 1, 0);

        $userId = Auth::user()->id;
        $attemptDate = date('Y-m-d');
        $attemptCount = 1;
        $status = 1;
        $productId = $subscriptionProduct->id;
        $nextSubscriptionDate = SubscriptionHistory::getNextSubscriptionDate();
        $response = 'Reactivate subscription';

        SubscriptionHistory::UpdateSubscriptionHistoryOnly($userId, $attemptDate, $attemptCount, $status, $productId, $paymentMethodId, $nextSubscriptionDate, $response, 1);
        User::updateNextSubscriptionDate($userId, $nextSubscriptionDate);

        $this->setResponse(['error' => 0, 'msg' => 'Account reactivated', 'nd' => $nextSubscriptionDate]);
        return $this->showResponse();
    }

    private function validateAddCard()
    {
        $req = request();
        $validator = Validator::make($req->all(), [
            'first_name' => 'required|max:50',
            'last_name' => 'required|max:50',
            'number' => 'required',
            'cvv' => 'required|max:4',
            'expiry_date' => 'required|size:7',
            'address1' => 'required|max:255',
            'countrycode' => 'required|max:10',
            'city'=> 'required|max:255',
            //'stateprov' => 'required|max:50',
            'stateprov' => 'max:50',
            'postalcode' => 'required|max:10',
            'order_conversion_id' => 'required|numeric',
            'terms' => 'required',
        ], [
            'first_name.required' => 'First name on card is required',
            'first_name.max' => 'First name cannot exceed 50 charactors',
            'last_name.required' => 'Last name on card is required',
            'last_name.max' => 'Last name cannot exceed 50 charactors',
            'number.required' => 'Card number is required',
            'cvv.required' => 'CVV is required',
            'cvv.max' => 'CVV cannot exceed 4 charactors',
            'expiry_date.required' => 'Expiration date is required',
            'expiry_date.size' => 'Invalid expiration date format',
            'address1.required' => 'Address is required',
            'address1.max' => 'Address exceed the limit',
            'countrycode.required' => 'Country is required',
            'countrycode.max' => 'Country exceed the limit',
            'city.required' => 'City / Town is required',
            'city.max' => 'City / Town exceed the limit',
            //'stateprov.required' => 'State / Province is required',
            'stateprov.max' => 'State / Province exceed the limit',
            'postalcode.required' => 'Postal code is required',
            'postalcode.max' => 'Postal code exceed the limit',
            'order_conversion_id.required' => 'Order Conversion ID is required',
            'order_conversion_id.numeric' => 'Invalid Order Conversion ID',
            'terms.required' => 'Agree to terms and conditions',
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
            // validate expiry date
            $expiryDate = trim(str_replace(' ', '', $req->input('expiry_date')));
            $expireDateParts = explode('/', $expiryDate);

            if (!isset($expireDateParts[0]) || !isset($expireDateParts[1]) || strlen($expireDateParts[0]) != 2 || strlen($expireDateParts[1]) != 4) {
                $valid = 0;
                $msg = 'Invalid Expiry date';
            } else if (!preg_match('/^\d+$/', $expireDateParts[0]) || (!preg_match('/^\d+$/', $expireDateParts[1]))) {
                $valid = 0;
                $msg = 'Invalid Expiry date';
            }
        }
        $res = array();
        $res["valid"] = $valid;
        $res["msg"] = $msg;
        return $res;
    }
}

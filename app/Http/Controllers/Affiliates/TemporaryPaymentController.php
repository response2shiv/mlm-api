<?php

namespace App\Http\Controllers\Affiliates;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\DiscountCoupon;
use App\Models\Helper;
use App\Models\Address;
use App\Models\PaymentMethod;
use App\Models\PaymentMethodType;
use App\Models\EwalletTransaction;
use App\Models\User;
use App\Models\UserType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderConversion;
use App\Helpers\Util;
use Config;
use Log;
use Auth;
use Validator;
use DB;

/**
 * THIS CLASS IS A TEMPORARY CLASS SO WE CAN IMPLEMENT THE FRONTEND V2
 * WE HAVE TO REVIEW THIS WHOLE STRUCTURE TO IMPLEMENT A BETTER PAYMENT SYSTEM
 */
class TemporaryPaymentController extends Controller
{
    
    public function checkoutFoundation()
    {
        $req = request();
        $validator = Validator::make($req->all(), [
            'amount' => 'required|numeric',
            'payment_method_id' => 'required',
            'order_conversion_id' => 'required',
        ], [
            'amount.required' => 'Amount to be transferred is required',
            'amount.numeric' => 'Amount must be numeric',
            'payment_method_id.required' => 'Payment method cannot be empty',
            'order_conversion_id.required' => 'Order Conversion id cannot be empty',
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
            if ($req->amount < 0) {
                $valid = 0;
                $msg = "Invalid amount";
            }
        }
        $res = array();
        $res["valid"] = $valid;
        $res["msg"] = $msg;

        if (!$valid) {
            return response()->json(['error' => 1, 'msg' => $msg],400);
        }

        $pm = PaymentMethod::getById($req->payment_method_id, Auth::user()->id);
        if ($pm->pay_method_type == PaymentMethodType::TYPE_E_WALET) {
            $balance = Auth::user()->estimated_balance;
            $availableBalace = $balance - EwalletTransaction::TRANSACTION_FEE;
            if ($availableBalace < 0) {
                return response()->json(['error' => 1, 'msg' => 'Insufficient available balance'],400);
            } else if ($availableBalace < $req->amount) {
                return response()->json(['error' => 1, 'msg' => 'Insufficient available balance'],400);
                // return response()->json(['error' => 1, 'msg' => "Amount to be transferred cannot be exceeded " . number_format(floor($availableBalace), 2)],400);
            } else {
                $this->doPaymentByEwallet(Product::ID_FOUNDATION, $req);
                return response()->json(['error' => 0]);
            }
        } else if ($req->payment_method_id == 'new_card') {
            session_start();
            $d = [];
            $d['amount'] = $req->amount;
            $d['product'] = Product::find(Product::ID_FOUNDATION);
            $d['sessionId'] = session_id();

            return response()->json(['error' => 0, 'd' => $d]);
        } else {
            //existing card
            session_start();
            $paymentMethodId = $req->payment_method_id;
            $res = Helper::checkExistingCardAndBillAddress(Auth::user()->id, $paymentMethodId);
            if ($res['error'] == 1) {
                return response()->json($res);
            }
            $product = Product::getById(Product::ID_FOUNDATION);
            $product->price = $req->amount;
            $sessionId = session_id();
            $orderConversionId = isset($req->order_conversion_id) ? $req->order_conversion_id : null;
            return Helper::NMIPaymentProcessUsingExistingCard(Auth::user()->id, $res['billingAddress'], $product, $sesData = ['discount' => 0, 'sessionId' => $sessionId], $res['paymentMethod'], Auth::user()->email, Auth::user()->phonenumber, Auth::user()->firstname, Auth::user()->lastname, 'FOUNDATION', $orderConversionId);
        }
    }

    private function doPaymentByEwallet($productId, $req)
    {
        $product = Product::getById($productId);
        $checkEwalletBalance = User::select('*')->where('id', Auth::user()->id)->first();
        if ($checkEwalletBalance->estimated_balance < $req->amount) {
            return response()->json(['error' => 1, 'msg' => "Not enough e-wallet balance"]);
        }
        $paymentMethodId = Helper::createSecondoryPaymentMethodIfNotAvlPrimaryPaymentMethod(Auth::user()->id, null, null, Helper::createEmptyPaymentRequest(Auth::user()->firstname, Auth::user()->lastname, null), PaymentMethodType::TYPE_E_WALET);
        $orderSubtotal = $req->amount;
        $orderTotal = $req->amount;
        $orderId = Helper::createNewOrderAfterPayment(Auth::user()->id, $orderSubtotal, $orderTotal, $paymentMethodId, $sesData = ['discountCode' => ''], $product, null, $orderFor = "FOUNDATION", $req->order_conversion_id);
        EwalletTransaction::addPurchase(Auth::user()->id, EwalletTransaction::TYPE_FOUNDATION, -$req->amount, $orderId);
        //$v = (string)view('affiliate.foundation.dlg_foundation_checkout_success');
        return response()->json(['error' => 0]);
    }


    /*
    * Add new payment method to the account when making ncrease foundation donation
    */
    public function checkoutCardFoundation(request $request)
    {
        $req = $request;

        $validator = Validator::make($req->all(), [
            'number' => 'required',
            'cvv' => 'required|max:4',
            'expiry_date' => 'required|size:7',
        ], [
            'number.required' => 'Card number is required',
            'cvv.required' => 'CVV is required',
            'cvv.max' => 'CVV cannot exceed 4 charactors',
            'expiry_date.required' => 'Expiration date is required',
            'expiry_date.size' => 'Invalid expiration date format',
        ]);

        $msg = "";
        if ($validator->fails()) {
            $valid = 1;
            $messages = $validator->messages();
            foreach ($messages->all() as $m) {
                $msg = $m;
            }
            return response()->json(['error' => $valid, 'msg' => $msg]);
        } else {
            $valid = 0;
            // validate expiry date
            $expiryDate = trim(str_replace(' ', '', $req->input('expiry_date')));
            $expireDateParts = explode('/', $expiryDate);
            if (!isset($expireDateParts[0]) || !isset($expireDateParts[1]) || strlen($expireDateParts[0]) != 2 || strlen($expireDateParts[1]) != 4) {
                $valid = 1;
                $msg = 'Invalid Expiry date';
            } else if (!preg_match('/^\d+$/', $expireDateParts[0]) || (!preg_match('/^\d+$/', $expireDateParts[1]))) {
                $valid = 1;
                $msg = 'Invalid Expiry date';
            }
            if ($valid == 1) {
                return response()->json(['error' => 1, 'msg' => $msg]);
            }
        }

        $product = Product::getById(Product::ID_FOUNDATION);

        $res = Helper::checkExsitingCardAfterTokenize($req);
        if ($res['error'] == 1) {
            return response()->json($res);
        }
        $orderSubtotal = $req->amount;
        $orderTotal = $req->amount;
        $paymentMethodType = PaymentMethodType::TYPE_CREDIT_CARD;
        if (Helper::checkTMTAllowPayment(Auth::user()->countrycode, Auth::user()->id) > 0) {
            $paymentMethodType = PaymentMethodType::TYPE_T1_PAYMENTS;
        }
        $userAddress = Address::where('userid', Auth::user()->id)
            ->where('addrtype', Address::TYPE_REGISTRATION)
            ->first();
        if (empty($userAddress)) {
            $userAddress = Address::getBillingAddress(Auth::user()->id);
        }
        $cReq = new \stdClass();
        $cReq->first_name = Auth::user()->firstname;
        $cReq->last_name = Auth::user()->lastname;
        $cReq->session_id = $req->session_id;
        $cReq->number = $req->number;
        $cReq->cvv = $req->cvv;
        $cReq->expiry_date = $req->expiry_date;
        $cReq->address1 = (isset($userAddress->address1) ? $userAddress->address1 : '');
        $cReq->address2 = (isset($userAddress->address2) ? $userAddress->address2 : '');
        $cReq->city = (isset($userAddress->city) ? $userAddress->city : '');
        $cReq->stateprov = (isset($userAddress->stateprov) ? $userAddress->stateprov : '');
        $cReq->postalcode = (isset($userAddress->postalcode) ? $userAddress->postalcode : '');
        $cReq->countrycode = (isset($userAddress->countrycode) ? $userAddress->countrycode : '');
        $cReq->apt = (isset($userAddress->apt) ? $userAddress->apt : '');
        $orderConversionId = isset($req->order_conversion_id) ? $req->order_conversion_id : null;
        $nmiResult = Helper::NMIPaymentProcessUsingNewCard($req, $orderTotal, $product, $req->session_id, Auth::user()->email, Auth::user()->phonenumber, $paymentMethodType, $orderConversionId);
        // $nmiResult = Helper::NMIPaymentProcessUsingNewCard($request, $orderTotal, $product, $sesData['sessionId'], Auth::user()->email, Auth::user()->phonenumber, $paymentMethodType);
        //Allow the script to continue if not production.
        if(Config::get("app.env") == "prod"){
            if ($nmiResult['error'] == 1) {
                return response()->json($nmiResult);
            }
            $authorization = $nmiResult['authorization'];
        }else{
            $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyz';
            $authorization = substr(str_shuffle($permitted_chars), 0, 18);
        }
        // $tokenEx = $nmiResult['response']->Token;
        // $addressId = $userAddress->id;
        // $paymentMethodId = PaymentMethod::addSecondaryCard(Auth::user()->id, 0, $tokenEx, $addressId, PaymentMethodType::TYPE_CREDIT_CARD, $cReq);
        // Helper::createNewOrderAfterPayment(Auth::user()->id, $orderSubtotal, $orderTotal, $paymentMethodId, $sesData = ['discountCode' => ''], $product, $authorization, 'FOUNDATION');

        $addressId = Helper::createSecondoryAddressIfNotAvlPrimaryAddress(Auth::user()->id, $req,PaymentMethodType::TYPE_CREDIT_CARD);
        $paymentMethodId = Helper::createSecondoryPaymentMethodIfNotAvlPrimaryPaymentMethod(Auth::user()->id, $res['token'], $addressId, $req, $paymentMethodType);
        Helper::createNewOrderAfterPayment(Auth::user()->id, $orderSubtotal, $orderTotal, $paymentMethodId, $sesData, $product, $authorization, 'FOUNDATION', $orderConversionId);
        //$v = (string)view('affiliate.foundation.dlg_foundation_checkout_success');
        return response()->json(['error' => 0]);
    }

    public function genericCheckout(request $request){
        // payment_method: 141080
        // discount_coupon:
        // _token: f6Skhd7ya7qyLLRFkgK1U5vDUySAmqUlLHCbMGU3

        $req = $request;
        $sesData = $this->genericCheckOutSessionDataValidate($req, true);

        if ($sesData['error'] == 1) {
            return response()->json($sesData);
        }

        $product = Product::getProduct($sesData['productId']);

        //discount will come
        $amount = ($sesData['CheckOutQuantity'] * $product->price) - (float)$sesData['discount'];
        if ($amount <= 0) {
            return Helper::paymentUsingCouponCode($sesData, $product, 'PURCHASE_SHOP_ITEM', $request->order_conversion_id);
        }

        //$paymentType = $req->payment_method;
        $pm = PaymentMethod::getById($req->payment_method_id, Auth::user()->id);
        if ($pm->pay_method_type == PaymentMethodType::TYPE_E_WALET) {
            return $this->doPaymentForBuyShopProductByEwallet($req);
        } else {
            return $this->doPaymentForBuyShopProductByExistingCard($req);
        }
    }

    private function doPaymentForBuyShopProductByEwallet($request)
    {
        $sesData = $this->genericCheckOutSessionDataValidate($request);
        if ($sesData['error'] == 1) {
            return response()->json($sesData);
        }
        $product = Product::getProduct($sesData['productId']);

        $amount = ($sesData['CheckOutQuantity'] * $product->price) - $sesData['discount'];
        $checkEwalletBalance = User::select('*')->where('id', Auth::user()->id)->first();
        if ($checkEwalletBalance->estimated_balance < $amount) {
            return response()->json(['error' => 1, 'msg' => "Not enough e-wallet balance"]);
        }

        $paymentMethodId = Helper::createSecondoryPaymentMethodIfNotAvlPrimaryPaymentMethod(Auth::user()->id, null, null, Helper::createEmptyPaymentRequest(Auth::user()->firstname, Auth::user()->lastname, null), PaymentMethodType::TYPE_E_WALET);
        $orderSubtotal = ($sesData['CheckOutQuantity'] * $product->price);
        $orderTotal = ($sesData['CheckOutQuantity'] * $product->price) - $sesData['discount'];
        $orderConversionId = isset($request->order_conversion_id) ? $request->order_conversion_id : null;

        /* Add E-wallet rows to DB */
        $orderId = Helper::createNewOrderAfterPayment(Auth::user()->id, $orderSubtotal, $orderTotal, $paymentMethodId, $sesData, $product, null, 'PURCHASE_SHOP_ITEM', $orderConversionId);
        $EwalletTransactionId = EwalletTransaction::addPurchase(Auth::user()->id, EwalletTransaction::TYPE_CHECKOUT_SHOP, -$orderTotal, $orderId);

        return response()->json(['error' => 0, 'msg' => 'Your purchase was successful.', 'product' => $product]);
    }

    private function doPaymentForBuyShopProductByExistingCard($req)
    {
        // check discount code
        $sesData = $this->genericCheckOutSessionDataValidate($req);
        if ($sesData['error'] == 1) {
            return response()->json($sesData);
        }
        $paymentMethodId = $req->payment_method_id;
        $res = Helper::checkExistingCardAndBillAddress(Auth::user()->id, $paymentMethodId);
        if ($res['error'] == 1) {
            return response()->json($res);
        }
        $product = Product::getProduct($sesData['productId']);

        $orderConversionId = isset($req->order_conversion_id) ? $req->order_conversion_id : null;
        return Helper::NMIPaymentProcessUsingExistingCard(Auth::user()->id,
            $res['billingAddress'],
            $product,
            $sesData,
            $res['paymentMethod'],
            Auth::user()->email,
            Auth::user()->phonenumber,
            Auth::user()->firstname,
            Auth::user()->lastname,
            'PURCHASE_SHOP_ITEM',
            $orderConversionId
        );
    }

    private function genericCheckOutSessionDataValidate($request, $validate_cc=false)
    {
        if($validate_cc){
            $validator = Validator::make($request->all(), [
                'product_id' => 'required|numeric',
                'quantity' => 'required|numeric',
                'payment_method_id' => 'required|numeric'],
            [
                'product_id.required' => 'Product is required',
                'product_id.numeric' => 'Invalid Product',
                'quantity.required' => 'Quantity is required',
                'quantity.numeric' => 'Invalid quantity',
                'payment_method_id.required' => 'Payment Method is required',
                'payment_method_id.numeric' => 'Invalid Payment Method',
                    //'amount.numeric' => 'Amount must be numeric',
            ]);

            if ($validator->fails()) {
                $valid = 0;
                $messages = $validator->messages();
                foreach ($messages->all() as $m) {
                    $msg = $m;
                }

                return ['error' => 1, 'msg' => $msg];
            }
        }
        // get discount code
        $discountCode = $request->discount_code;
        $discount     = 0;
        if (!empty($discountCode) && !$discount = DiscountCoupon::getDiscountAmount($discountCode)) {
            return ['error' => 1, 'msg' => "Invalid discount code"];
        }

        if (empty($request->product_id)) {
            return ['error' => 1, 'msg' => "Invalid product id"];
        }

        if (empty($request->quantity)) {
            return ['error' => 1, 'msg' => "Invalid checkout quantity"];
        }

        return [
            'error' => 0,
            'discountCode' => $discountCode,
            'discount' => $discount,
            'productId' => $request->product_id,
            'CheckOutQuantity' => $request->quantity,
            'sessionId' => 'sg7a6df982f7afba223fbad637378365'
        ];
    }

    public function genericCheckOutNewCard(request $request)
    {
        $req = $request;

        $vali = Helper::validatePaymentPage($req);
        if ($vali['valid'] == 0) {
            return response()->json(['error' => 1, 'msg' => $vali['msg']]);
        }
        return $this->doPaymentForNewCardGeneric($req);
    }

    private function doPaymentForNewCardGeneric($req)
    {
        $sesData = $this->genericCheckOutSessionDataValidate($req);
        if ($sesData['error'] == 1) {
            return response()->json($sesData);
        }
        $product = Product::getProduct($sesData['productId']);
        $res = Helper::checkExsitingCardAfterTokenize($req);
        if ($res['error'] == 1) {
            return response()->json($res);
        }
        
        $orderSubtotal = ($sesData['CheckOutQuantity'] * $product->price);
        $orderTotal = ($sesData['CheckOutQuantity'] * $product->price) - $sesData['discount'];

        $paymentMethodType = PaymentMethodType::TYPE_CREDIT_CARD;

        if (Helper::checkTMTAllowPayment($req->countrycode,Auth::user()->id) > 0) {
            if (Helper::checkTMTAllowPayment($req->countrycode,Auth::user()->id) > 0) {
                //  $paymentMethodType = \App\PaymentMethodType::TYPE_T1_PAYMENTS;
                // ONLY ON US CUSTOMERS
                if($req->countrycode == "US"){
                    $paymentMethodType = PaymentMethodType::TYPE_PAYARC;
                }else{
                    $paymentMethodType = PaymentMethodType::TYPE_T1_PAYMENTS;
                }
            }
        }

        $orderConversionId = isset($req->order_conversion_id) ? $req->order_conversion_id : null;

        $nmiResult = Helper::NMIPaymentProcessUsingNewCard($req, $orderTotal, $product, $sesData['sessionId'], Auth::user()->email, Auth::user()->phonenumber, $paymentMethodType, $orderConversionId);
        if ($nmiResult['error'] == 1) {
            return response()->json($nmiResult);
        }
        $authorization = $nmiResult['authorization'];
        $addressId = Helper::createSecondoryAddressIfNotAvlPrimaryAddress(Auth::user()->id, $req,PaymentMethodType::TYPE_CREDIT_CARD);
        $paymentMethodId = Helper::createSecondoryPaymentMethodIfNotAvlPrimaryPaymentMethod(Auth::user()->id, $res['token'], $addressId, $req, $paymentMethodType);
        Helper::createNewOrderAfterPayment(Auth::user()->id, $orderSubtotal, $orderTotal, $paymentMethodId, $sesData, $product, $authorization, 'PURCHASE_SHOP_ITEM', $orderConversionId);

        $d['product'] = $product;
        return response()->json(['error' => 0, 'v' => $d]);
    }

    public function generateNewDiscountCouponCode() {
        $d = array();
        $d['code'] = DiscountCoupon::getNewCode();
        $d['prepaid_products'] = Product::getByTypeId(ProductType::TYPE_PRE_PAID_CODES);
        // return view('affiliate.discount.add-discount')->with($d);
        return response()->json(['error' => 0, 'response' => $d]);
    }

    public function createNewDiscountCoupon(request $request) {
        $req = $request;
        $vali = $this->validateDiscountCouponData($req);
        if ($vali['valid'] == 0) {
            return response()->json(['error' => 1, 'msg' => $vali['msg']]);
        }
        $product = Product::getById($req->product_id);
        $amount = (float) $product->price;
        $req['product_id_ref'] = $req->product_id;

        $req->merge(['amount' => $amount]);
        $cEBRes['tsaPurchase'] = false;
        if (!empty($req->sponsorid)) {
            $cEBRes = $this->checkEwalletBalance($req);
        } else {
            if (Auth::user()->usertype == UserType::TYPE_DISTRIBUTOR && !empty(Auth::user()->distid)) {
                $req['sponsorid'] = Auth::user()->distid;
                $cEBRes = $this->checkEwalletBalance($req);
            }
        }
        if (!empty($cEBRes['error']) && $cEBRes['error'] == 1) {
            return response()->json(['error' => 1, 'msg' => $cEBRes['msg']]);
        }
        $couponCodeId = DiscountCoupon::addNew($req);
        if ($cEBRes['tsaPurchase']) {
            $distributor = $cEBRes['distributor'];
            $note = $product->productname . " - " . $req->discount_code;
            $paymentMethodId = $this->getEwalletPaymentMethodId(Auth::user()->id);
            $orderId = Order::addNew(
                $distributor->id, (float) $product->price, (float) $product->price, $product->bv, $product->qv, $product->cv, null, $paymentMethodId, null, null
            );
            OrderItem::addNew(
                $orderId, $req->product_id, 1, (float) $product->price, $product->bv, $product->qv, $product->cv, false, $couponCodeId
            );
            EwalletTransaction::addPurchase($distributor->id, EwalletTransaction::TYPE_CODE_PURCHASE, -$amount, $orderId, $note);
        }
        //$newCode = \App\DiscountCoupon::getNewCode();
        //return response()->json(['error' => 0, 'code' => $newCode]);
        return response()->json(['error' => 0, 'msg' => 'Voucher code created successfully']);
    }

    private function validateDiscountCouponData($req) {
        $req = request();
        $validator = Validator::make($req->all(), [
                    'discount_code' => 'required|alpha_num|unique:discount_coupon,code',
                    'product_id' => 'required',
                        ], [
                    'discount_code.required' => 'Code is required',
                    'discount_code.alpha_num' => 'Invalid code',
                    'discount_code.unique' => 'Code already used',
                    'product_id.required' => 'Discount Amount is required',
                        //'amount.numeric' => 'Amount must be numeric',
        ]);

        $msg = "";
        if ($validator->fails()) {
            $valid = 0;
            $messages = $validator->messages();
            foreach ($messages->all() as $m) {
                $msg .= $m;
            }
        } else {
            $valid = 1;
        }
        $res = array();
        $res["valid"] = $valid;
        $res["msg"] = $msg;
        return $res;
    }

    private function getEwalletPaymentMethodId($userId) {
        $paymentMethod = PaymentMethod::getByUserPayMethodType($userId, PaymentMethodType::TYPE_E_WALET);
        if (empty($paymentMethod)) {
            $paymentMethod = PaymentMethod::addNewCustomPaymentMethod([
                        'userID' => Auth::user()->id,
                        'created_at' => Util::getCurrentDateTime(),
                        'updated_at' => Util::getCurrentDateTime(),
                        'pay_method_type' => PaymentMethodType::TYPE_E_WALET
            ]);
            return $paymentMethod->id;
        } else {
            return $paymentMethod->id;
        }
    }

    private function checkEwalletBalance($req) {
        $distributor = User::where('distid', $req->sponsorid)->first();
        if (!$distributor) {
            return ['error' => 1, 'msg' => 'Distributor not found'];
        }
        $balance = 0;
        $estimatedBalance = $distributor->estimated_balance;
        if ($estimatedBalance < abs($req->amount)) {
            $balance = $estimatedBalance > 0 ? $estimatedBalance : 0;
            return ['error' => 1, 'msg' => 'You have $' . number_format($balance, 2) . ' in your ewallet'];
        }

        $req->merge(['generated_for' => $distributor->id]);
        $amount = -1 * abs($req->amount);
        $tsaPurchase = true;
        return ['success' => 1, 'tsaPurchase' => $tsaPurchase, 'distributor' => $distributor, 'amount' => $amount, 'balance' => $balance];
    }

    public function getCountries(){
        $countries = DB::table('country')->orderBy('country', 'asc')->get();
        $this->setResponse($countries);
        $this->setResponseCode(200);
        return $this->showResponse();
    }

    public function getStates($country_code) {
        $states = DB::table('states')
            ->select('*')
            ->where('country_code', $country_code)
            ->orderBy('name', 'asc')
            ->get()->toArray();

        $this->setResponse($states);
        $this->setResponseCode(200);
        return $this->showResponse();
    }
    
    public function print(Request $request) {        
        $this->setResponse($request->all());
        $this->setResponseCode(200);
        return $this->showResponse();
    }
}

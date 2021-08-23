<?php

namespace App\Http\Controllers\Affiliates;

use Illuminate\Http\Request;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Product;
use App\Models\PaymentMethod;
use App\Models\DiscountCoupon;
use App\Models\Country;
use App\Models\Helper;
use App\Models\Address;
use App\Models\EwalletTransaction;
use App\Models\PaymentMethodType;
use App\Helpers\CurrencyConverter;
use App\Models\OrderConversion;
use Auth;
use App\Helpers\Util;
use Session;
use Illuminate\Support\Str;

class UpgradeController extends Controller {

    public function upgradeProductCheckOut() {
        $req = request();
        //dd($req->all());
        $userCountry = Address::where('userid', Auth::user()->id)
            ->where('addrtype', Address::TYPE_BILLING)
            ->where('primary', 1)
            ->whereNotNull('countrycode')
            ->first();
        if (empty($userCountry)) {
            return response()->json(['error' => 1, 'msg' => 'We don\'t have your country in the Primary Address section of your profile. Please update your info to proceed.']);
        }
        // check discount code
        $sesData = $this->validateUpgradeData($req);

        if ($sesData['error'] == 1) {
            return response()->json($sesData);
        }


        $product = Product::getById($sesData['upgradeProductId']);
        $amount = $product->price - $sesData['discount'];
        if ($amount <= 0) {
            return Helper::paymentUsingCouponCode($sesData, $product, 'UPGRADE_PACKAGE', $req['order_conversion_id']);
        }
        $vali = Helper::validateCheckOutPaymentType($req);
        if ($vali['valid'] == 0) {
            return response()->json(['error' => 1, 'msg' => $vali['msg']]);
        }

        $pm = PaymentMethod::getById($req->payment_method_id, Auth::user()->id);

        if (!empty($pm) && $pm == "new_card") {

            $d['product_id'] = $req['new_product_id'];
            $d['countries'] = Country::getAll();
            // $v = (string) view('affiliate.upgrades.dlg_upgrade_check_out_add_payment_method')->with($d);
            return response()->json(['error' => 0, 'd' => $d]);
        } else if (!empty($pm) && $pm->pay_method_type == PaymentMethodType::TYPE_E_WALET) {
            return $this->doPaymentForUpgradePackageByEwallet($req);
        } else if (!empty($pm) && $pm->pay_method_type == PaymentMethodType::TYPE_BITPAY) {
            // return $this->bitpayInvoiceGenerate($req);
            return $this->doPaymentForUpgradePackageByExistingCard($req);
        } else if (!empty($pm)) {
            return $this->doPaymentForUpgradePackageByExistingCard($req);
        }
    }

    private function doPaymentForUpgradePackageByExistingCard($req) {
        $sesData = $this->validateUpgradeData($req);
        if ($sesData['error'] == 1) {
            return response()->json($sesData);
        }
        $paymentMethodId = $req->payment_method_id;
        $res = Helper::checkExistingCardAndBillAddress(Auth::user()->id, $paymentMethodId);
        if ($res['error'] == 1) {
            return response()->json($res);
        }
        $product = Product::getById($sesData['upgradeProductId']);
        $orderConversionId = isset($req['order_conversion_id']) ? $req['order_conversion_id'] : null;
        return Helper::NMIPaymentProcessUsingExistingCard(Auth::user()->id, $res['billingAddress'], $product, $sesData, $res['paymentMethod'], Auth::user()->email, Auth::user()->phonenumber, Auth::user()->firstname, Auth::user()->lastname, 'UPGRADE_PACKAGE', $orderConversionId);
    }

    public function bitpayInvoiceGenerate($data) {
        $sesData = $this->validateUpgradeData($data);
        if ($sesData['error'] == 1) {
            return response()->json($sesData);
        }
        $response = Helper::bitPayPaymentRequest(Auth::user(), $sesData, 'UPGRADE_PACKAGE');
        return response()->json($response);
    }

    private function doPaymentForUpgradePackageByEwallet($data) {
        $sesData = $this->validateUpgradeData($data);

        if ($sesData['error'] == 1) {
            return response()->json($sesData);
        }

        $product = Product::getById($sesData['upgradeProductId']);
        $amount = $product->price - $sesData['discount'];
        $checkEwalletBalance = User::select('*')->where('id', Auth::user()->id)->first();
        if ($checkEwalletBalance->estimated_balance < $amount) {
            return response()->json(['error' => 1, 'msg' => "Not enough e-wallet balance"]);
        }
        $paymentMethodId = Helper::createSecondoryPaymentMethodIfNotAvlPrimaryPaymentMethod(Auth::user()->id, null, null, Helper::createEmptyPaymentRequest(Auth::user()->firstname, Auth::user()->lastname, null), PaymentMethodType::TYPE_E_WALET);
        $orderSubtotal = $product->price;
        $orderTotal = $product->price - $sesData['discount'];
        
        $orderId = Helper::createNewOrderAfterPayment(Auth::user()->id, $orderSubtotal, $orderTotal, $paymentMethodId, $sesData, $product, null, $orderFor = "UPGRADE_PACKAGE", $sesData['orderConversionId']);
        EwalletTransaction::addPurchase(Auth::user()->id, EwalletTransaction::TYPE_UPGRADE_PACKAGE, -$amount, $orderId);

        return response()->json(['error' => 0,'msg'=>'Upgrade Package Successfuly', "orderId" => $orderId, 'userId' => Auth::user()->id]);
    }

    public function getUpgradeCountdown() {
        $res = User::canUpgrade();
        return response()->json(['error' => 0, 'date' => $res['end_date']]);
    }

    public function dlgUpgradePackage($package, $country=null, $locale=null) {

        $d = array();
        $name = "";

        if (empty($package)) {
            return response()->json(['error' => 1, 'msg' => 'Upgrade product not found. Please contact supports.'],400);
        }

        if ($package == Product::ID_NCREASE_NSBO) {
            $name = "Ncrease ISBO";
        } else if ($package == Product::ID_VISIONARY_PACK) {
            $name = "Visionary Pack";
        } else if ($package == Product::ID_BASIC_PACK) {
            $name = "Basic Pack Enrollment Voucher";
        }
        
        $upgradeTime = $this->getUpgradeCountdown();
        $upgradeTime = json_decode($upgradeTime->getContent());
        $currentProductId = User::getCurrentProductId(Auth::user()->id);

        $upgProductId = $package;       

        $upgProduct = Product::getById($package);
        $d['cvv'] = PaymentMethod::getUserPaymentRecords(Auth::user()->id);
        $d['name'] = $name;
        $d['product'] = $upgProduct;

        $d['new_product_id'] = $package;
        $d['current_product_id'] = $currentProductId;
        $d['upgrade_product_id'] = $upgProductId;
        
        
        $baseUrl = env('BILLING_BASE_URL') ;
        $apiToken = env('BILLING_API_TOKEN');
        $amount = number_format($upgProduct->price,2,'','');
        $response = CurrencyConverter::convert($baseUrl, $apiToken, $amount, $country, $locale);
        // JSON is null or issue with
        if (!$response) {
            return response()->json([
                'success' => false,
                'errors' => [
                    'server' => 'An internal error has occurred'
                ],
                'amount' => $amount,
                'display_amount' => 'N/A'
            ])->setStatusCode(500);
        }

        if ($response['success'] !== 1) {
            $errors = $response['errors'];

            return response()->json([
                'success' => 0,
                'errors' => $errors,
                'amount' => -1,
                'display_amount' => 'N/A'
            ])->setStatusCode(400);
        }

        $orderConversion = new OrderConversion();
        
        $orderConversion->fill([
            'session_id' => session_id(),
            'original_amount' => $amount,
            'original_currency' => 'USD',
            'converted_amount' => $response['amount'],
            'converted_currency' => $response['currency'],
            'exchange_rate' => $response['exchange_rate'],
            'expires_at' => now()->addMinutes(30),
            'display_amount' => $response['display_amount']
        ]);

        $orderConversion->save();
        
        $d['order_conversion_id'] = $orderConversion->id;
        $d['expiration'] = $orderConversion->expires_at->timestamp;
        $d['conversion'] = $response;
        
      
        $this->setResponse($d);
        $this->setResponseCode(200);

        return $this->showResponse();
    }

    private function validateUpgradeData($request) {
        $data = $request->all();

        $discountCode = $data['discount_code'];
        $discount = 0;

        if (!Util::isNullOrEmpty($discountCode)) {
            $discount = DiscountCoupon::getDiscountAmount($discountCode);
            if ($discount == 0) {
                return ['error' => 1, 'msg' => "Invalid discount code"];
            }
        }
        if (!empty($data['upgrade_product_id'])) {
            $upgradePackageId   = $data['upgrade_product_id'];
            $newProductId       = $data['new_product_id'];
            $currentProductId   = $data['current_product_id'];
        }

        if (empty($upgradePackageId)) {
            return ['error' => 1, 'msg' => "Invalid upgrade package1"];
        }
        if (empty($newProductId)) {
            return ['error' => 1, 'msg' => "Invalid upgrade package3"];
        }
        if (empty($currentProductId)) {
            return ['error' => 1, 'msg' => "User exists in invalid package4"];
        }
        $orderConversionId = isset($request->order_conversion_id) ? $request->order_conversion_id : null;
        return [
            'error' => 0,
            'discountCode' => $discountCode,
            'upgradeProductId' => $upgradePackageId,
            'discount' => $discount,
            'newProductId' => $newProductId,
            'sessionId' => Str::random(32),
            'orderConversionId' => $orderConversionId,
            'currentProductId' => $currentProductId
        ];
    }

    public function checkCouponCodeUpgrade(request $request) {
        //$req = request();
        //session(['checkOutUpgradePackageDiscountCode' => ""]);
        //$upgrade_package = Session::get('upgrade_package');
        $upgradePackageId   = $request->product_id;
        $product            = Product::getProduct($upgradePackageId);
        $subTotal           = $product->price;
        $total              = $subTotal;

        $d['total']     = number_format($total, 2, '.', '');
        $d['sub_total'] = number_format($subTotal, 2, '.', '');
        $d['product']   = $product;

        //$v = (string) view('affiliate.upgrades.dlg_upgrade_product_coupon')->with($d);
        $discountCode = $request->discount_code;
        $discount = 0;
        if (!Util::isNullOrEmpty($discountCode)) {
            $discount = DiscountCoupon::getDiscountAmount($discountCode);
            if ($discount == 0) {
                return response()->json(['error' => 1, 'msg' => "Invalid discount code", 'details' => $d, 'total' => $total]);
            }
        } else {
            return response()->json(['error' => 1, 'msg' => "Invalid discount code", 'details' => $d, 'total' => $total]);
        }
        //session(['checkOutUpgradePackageDiscountCode' => $discountCode]);
        $subTotal = $product->price;
        $total = $subTotal - $discount;
        if ($total <= 0) {
            $total = 0;
            $totalConvert = CurrencyConverter::convertCurrency(0, $request['country'], $request['locale']);
        }else{
            $totalConvert = CurrencyConverter::convertCurrency(number_format($total, 2, '', ''), $request['country'], $request['locale']);
        }
        
        $orderConversion = new OrderConversion();
        $orderConversion->fill([
            'session_id' => session_id(),
            'original_amount' => number_format($total,2,'',''),
            'original_currency' => "USD",
            'converted_amount' => $totalConvert["amount"],
            'converted_currency' => $totalConvert['currency'],
            'exchange_rate' => $totalConvert['exchange_rate'],
            'expires_at' => now()->addMinutes(30),
            'display_amount' => $totalConvert['display_amount']
        ]);
        
        $orderConversion->save();
        
        $d['order_conversion_id'] = $orderConversion->id;
        $d['expiration'] = $orderConversion->expires_at->timestamp;
        $d['total']     = number_format($total, 2, '.', '');
        $d['sub_total'] = number_format($subTotal, 2, '.', '');
        $d['product']   = $product;
        $d['total_display'] = $totalConvert["display_amount"];    
      
        $subtotalConvert = CurrencyConverter::convertCurrency(number_format($subTotal,2,'',''), $request['country'], $request['locale']);
        $d['subtotal_display'] = $subtotalConvert["display_amount"];
        $d['currency_display'] = $subtotalConvert["currency"];
        $d['exchange_display'] = $subtotalConvert["exchange_rate"];
        
        //$v = (string) view('affiliate.upgrades.dlg_upgrade_product_coupon')->with($d);
        return response()->json(['error' => 0, 'msg' => 'Valid discount code', 'details' => $d, 'total' => number_format($total, 2, '.', '')]);
    }

    public function upgradeProductsCheckOutNewCard() {
        $req = request();
        $vali = Helper::validatePaymentPage($req);
        if ($vali['valid'] == 0) {
            return response()->json(['error' => 1, 'msg' => $vali['msg']]);
        }
        return $this->doPaymentForNewCardUpgradeProducts($req);
    }

    private function upgraderPackagesCheckOutSessionDataValidate($req) {
        $discountCode = $req['discount_code'];
        $discount = 0;
        if (!\utill::isNullOrEmpty($discountCode)) {
            $discount = DiscountCoupon::getDiscountAmount($discountCode);
            if ($discount == 0) {
                return ['error' => 1, 'msg' => "Invalid discount code"];
            }
        }
        if (!empty($req)) {
            $upgradePackageId = $req['upgrade_product_id'];
            $newProductId = $req['new_product_id'];
            $currentProductId = $req['current_product_id'];
        }else{
            return ['error' => 1, 'msg' => "Invalid upgrade package"];
        }

        if (empty($upgradePackageId)) {
            return ['error' => 1, 'msg' => "Invalid upgrade package"];
        }
        if (empty($newProductId)) {
            return ['error' => 1, 'msg' => "Invalid upgrade package"];
        }
        if (empty($currentProductId)) {
            return ['error' => 1, 'msg' => "User exists in invalid package"];
        }

        return [
            'error' => 0,
            'discountCode' => $discountCode,
            'upgradeProductId' => $upgradePackageId,
            'discount' => $discount,
            'newProductId' => $newProductId,
            'currentProductId' => $currentProductId,
            'sessionId' => 'sg7a6df982f7afba223fbad637378365'
        ];
    }

    private function doPaymentForNewCardUpgradeProducts($req) {
        $sesData = $this->upgraderPackagesCheckOutSessionDataValidate($req);
        if ($sesData['error'] == 1) {
            return response()->json($sesData);
        }
        $product = Product::getById($sesData['upgradeProductId']);
        $res = Helper::checkExsitingCardAfterTokenize($req);
        if ($res['error'] == 1) {
            return response()->json($res);
        }
        $orderSubtotal = $product->price;
        $orderTotal = $product->price - $sesData['discount'];
        $paymentMethodType = PaymentMethodType::TYPE_CREDIT_CARD;

        if (Helper::checkTMTAllowPayment($req->countrycode,Auth::user()->id) > 0) {
            //  $paymentMethodType = \App\PaymentMethodType::TYPE_T1_PAYMENTS;
            // ONLY ON US CUSTOMERS
            if($req->countrycode == "US"){
                $paymentMethodType = PaymentMethodType::TYPE_PAYARC;
            }else{
                $paymentMethodType = PaymentMethodType::TYPE_T1_PAYMENTS;
            }
        }
        
        $orderConversionId = isset($req->order_conversion_id) ? $req->order_conversion_id : null;
        
        $nmiResult = Helper::NMIPaymentProcessUsingNewCard($req, $orderTotal, $product, $sesData['sessionId'], Auth::user()->email, Auth::user()->phonenumber, $paymentMethodType, $orderConversionId);

        if ($nmiResult['error'] == 1) {
            return response()->json($nmiResult);
        }
        $authorization = $nmiResult['authorization'];
        $addressId = Helper::createSecondoryAddressIfNotAvlPrimaryAddress(Auth::user()->id, $req, $paymentMethodType);
        $paymentMethodId = Helper::createSecondoryPaymentMethodIfNotAvlPrimaryPaymentMethod(Auth::user()->id, $res['token'], $addressId, $req, $paymentMethodType);
        $orderId = Helper::createNewOrderAfterPayment(Auth::user()->id, $orderSubtotal, $orderTotal, $paymentMethodId, $sesData, $product, $authorization, 'UPGRADE_PACKAGE');

        //$v = (string) view('affiliate.upgrades.dlg_check_out_package_upgrade_success');
        return response()->json(['error' => 0,'msg'=>'Upgrade Package Successfuly', "orderId" => $orderId, 'userId' => Auth::user()->id]);
    }
}

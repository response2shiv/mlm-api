<?php

namespace App\Models;

use App\Library\TMTService\TMTPayment;
use Auth;
use DB;
use File;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use App\Models\NMIGateway;
use App\Models\Order;
use App\Models\ApiLogs;
use App\Helpers\tokenexAPI;
use App\Helpers\Kount;
use App\Helpers\Util;
use Session;
use Validator;
use Log;

class Helper extends Model { // @TODO Check why he extends a model

    public static function getReactivationFee()
    {
        $reactivationProduct = Product::getProduct(Product::ID_REACTIVATION_PRODUCT);
        if ($reactivationProduct->is_enabled == 0)
            $reactivationFee = 0;
        else
            $reactivationFee = $reactivationProduct->price;

        return $reactivationFee;
    }
    
    // @TODO moved to specialized class iPayoutService
    // public static function iPayoutCommission($userName, $useId, $amount)
    // {
    //     $accounts_to_load = array(
    //         array(
    //             'UserName' => $userName,
    //             'Amount' => $amount,
    //             'Comments' => 'commissions deposit',
    //             'MerchantReferenceID' => $useId . time(),
    //         )
    //     );
    //     $params = array(
    //         'fn' => 'eWallet_Load',
    //         'PartnerBatchID' => 'Transfer from ncrase wallet - ' . date('Y-m-d h:s:i') . '.',
    //         'PoolID' => '',
    //         'arrAccounts' => $accounts_to_load,
    //         'CurrencyCode' => 'USD',
	//     'Autoload' => 'true'
    //     );
    //     $response = IPayOut::curl($params);

    //     $logId = Helper::logApiRequests($useId, 'IPayout - commission', \Config::get('api_endpoints.eWalletAPIURL'), $params);
    //     Helper::logApiResponse($logId->id, json_encode($response));
    //     // Log::info("Ipyout response to ",array($response));
    //     if (isset($response->error)) {
    //         //exception error
    //         return ['error' => 1, 'msg' => $response->msg];
    //     } else {
    //         if (isset($response->IsError) && $response->IsError == 1) {
    //             //error
    //             return ['error' => 1, 'response' => $response];
    //         } else {
    //             if (isset($response->response) && $response->response->m_Text != 'OK') {
    //                 //error
    //                 return ['error' => 1, 'msg' => $response->response->m_Text];
    //             } else {
    //                 //success
    //                 return ['error' => 0, 'msg' => 'Amount successfully transferred', 'TransactionRefID' => $response->response->TransactionRefID];
    //             }

    //         }
    //     }

    // }

    // @TODO moved to specialized class iPayoutService
    // public static function iPayoutCheckout($userName, $useId, $amount)
    // {
    //     $params = array(
    //         array(
    //             // regular payment
    //             'Amount' => $amount,
    //             'CurrencyCode' => 'USD',
    //             'ItemDescription' => IPayOut::ITEM_DESCRIPTION,
    //             'MerchantReferenceID' => $useId . "#" . time(),
    //             'UserReturnURL' => \config('api_endpoints.eWalletCheckoutThankYouPageURL'),
    //             'MustComplete' => 'true',
	// 	//'Autoload' => 'true'
    //         )
    //     );
    //     $params = array(
    //         'fn' => 'eWallet_AddCheckoutItems',
    //         'UserName' => $userName,
    //         'arrItems' => $params,
    //         'CurrencyCode' => 'USD',
    //     );
    //     $response = IPayOut::curl($params);
    //     if ($response['error'] == 1) {
    //         //error
    //         $logId = Helper::logApiRequests($useId, 'IPayout - checkout', '', $params);
    //         Helper::logApiResponse($logId->id, json_encode($response['response']));
    //         return ['error' => 1, 'response' => $response['response']];
    //     } else {
    //         //success
    //         return ['error' => 0, 'response' => (!empty($response['response']->arrItemsResponse) ? $response['response']->arrItemsResponse[0] : [])];
    //     }
    // }

    // @TODO moved to specialized class iPayoutService
    // public static function createiPayoutUser($user)
    // {
    //     try {
    //         $hasRec = IPayOut::getIPayoutByUserId($user->id);
    //         if (!empty($hasRec)) {
    //             return ['error' => 1, 'data' => $hasRec, 'msg' => 'iPayout account already setup'];
    //         }
    //         $primary_address = Address::getRec($user->id, Address::TYPE_BILLING, 1);
    //         $params = array(
    //             'fn' => 'eWallet_RegisterUser',
    //             'UserName' => $user->username,
    //             'FirstName' => $user->firstname,
    //             'LastName' => $user->lastname,
    //             'CompanyName' => '',
    //             'Address1' => $primary_address->address1,
    //             'Address2' => $primary_address->address2,
    //             'City' => $primary_address->city,
    //             'State' => $primary_address->stateprov,
    //             'ZipCode' => $primary_address->postalcode,
    //             'Country2xFormat' => $primary_address->countrycode,
    //             'PhoneNumber' => $user->phonenumber,
    //             'CellPhoneNumber' => '',
    //             'EmailAddress' => $user->email,
    //             'SSN' => '',
    //             'CompanyTaxID' => '',
    //             'GovernmentID' => '',
    //             'MilitaryID' => '',
    //             'PassportNumber' => '',
    //             'DriversLicense' => '',
    //             'DateOfBirth' => '',
    //             'WebsitePassword' => '',
    //             'DefaultCurrency' => 'USD'
    //         );
    //         $response = IPayOut::curl($params);
    //         $logId = Helper::logApiRequests($user->id, 'IPayout - add user', \Config::get('api_endpoints.eWalletAPIURL'), $params);
    //         if (isset($response->error)) {
    //             //exception error
    //             Helper::logApiResponse($logId->id, $response->msg);
    //             return ['error' => 1, 'msg' => $response->msg];
    //         } else {
    //             if (isset($response->IsError) && $response->IsError == 1) {
    //                 Helper::logApiResponse($logId->id, (isset($response->response->m_Text) ? $response->response->m_Text : ''));
    //                 return ['error' => 1, 'data' => [], 'msg' => (isset($response->response->m_Text) ? $response->response->m_Text : '')];
    //             } else {
    //                 if (isset($response->response) && $response->response->m_Code == 0) {
    //                     //success
                        
    //                     $rec = IPayOut::addUser($user->id, $response->response->TransactionRefID);
                        
    //                     Helper::logApiResponse($logId->id, 'iPayout account setup successfully -> '.$response->response->TransactionRefID);
    //                     return ['error' => 0, 'data' => $rec, 'msg' => 'iPayout account setup successfully'];
    //                 } else {
    //                     //error
    //                     Helper::logApiResponse($logId->id, (isset($response->response->m_Text) ? $response->response->m_Text : ''));
    //                     return ['error' => 1, 'msg' => $response->response->m_Text];
    //                 }
    //             }
    //         }
    //     } catch (\Exception $ex) {
    //         if(isset($logId)){
    //             Helper::logApiResponse($logId->id, $ex->getMessage());
    //         }            
    //         return ['error' => 1, 'msg' => $ex->getMessage()];
    //     }

    // }
    
    // @TODO moved to specialized class
    // public static function checkIpayoutUser($user)
    // {
    //     try {
    //         $primary_address = Address::getRec($user->id, Address::TYPE_BILLING, 1);
    //         $params = array(
    //             'fn' => 'eWallet_CheckIfUserNameExists',
    //             'UserName' => $user->username
    //         );
    //         $response = IPayOut::curl($params);
    //         $logId = Helper::logApiRequests($user->id, 'IPayout - add user', '', $params);
    //         if (isset($response->error)) {
    //             //exception error
    //             return ['error' => 1, 'msg' => $response->msg];
    //         } else {
    //             if($response->response->m_Code === -2){
    //                 return ['error' => 0, 'data' => $response->response, 'msg' => $response->response->m_Text];
    //             }else{
    //                 return ['error' => 0, 'data' => $response->response, 'msg' => 'User already exists'];
    //             }
    //         }
    //     } catch (\Exception $ex) {
    //         return ['error' => 1, 'msg' => $ex->getMessage()];
    //     }

    // }

    // @TODO move to specialized class, it is service?
    public static function getPayoutPaymentMethod($userId, $countryCode)
    {
        if (empty($countryCode)) {
            $userCountry = Address::where('userid', $userId)
                ->where('addrtype', Address::TYPE_REGISTRATION)
                ->where('primary', 1)
                ->whereNotNull('countrycode')
                ->first();
            $countryCode = (!empty($userCountry->countrycode) ? $userCountry->countrycode : '');
        }
        
        if (!empty($countryCode)) {
            return PayOutControl::getPayoutTypeByCountryCode($countryCode);
        } else {
            return '';
        }
    }

    /*
     * SOR get member info
     */
    public static function sor_member_info($contractNumber)
    {
        return SaveOn::getMembersInformation($contractNumber);
    }

    /*
     * SOR transfer
     */

    public static function sor_transfer($userId, $newProductId, $currentProductId)
    {
        if ($currentProductId != Product::ID_STANDBY_CLASS) {
            $referringUserSORID = SaveOn::getSORUserId($userId);
            if ($referringUserSORID != null) {
                // SOR transfer
                $sorRes = SaveOn::transfer($userId, $referringUserSORID, $newProductId);
                $sorResponse = $sorRes['response'];
                if (!empty($sorResponse->status_code) && $sorResponse->status_code == 200) {
                    SaveOn::where('user_id', $userId)->update(['product_id' => $newProductId, 'note' =>  SaveOn::USER_TRANSFER_AFTER_FAIL_SUBSCRIPTION]);
                }
            }
        }
    }

    /*
     * Network Merchants
     */

    public static function networkMerchants($user, $ccnumber, $ccexpYear, $ccexpMonth, $product, $address, $orderTotal, $paymentMethodType = null)
    {
        $ccexp = date('m', strtotime(date("Y") . "-" . $ccexpMonth . "-01")) . "/" . date('y', strtotime($ccexpYear . "-01-01"));
        $gw = new \gwapi; // new gateway api
        $gw->setLogin($paymentMethodType);
        $gw->setBilling($user->firstname, $user->lastname, $user->business_name, $address->address1, $address->address2, $address->city,
            $address->stateprov, $address->postalcode, $address->countrycode, $user->phonenumber, "", $user->email,
            "");
        $gw->setShipping($user->firstname, $user->lastname, $user->business_name, $address->address1, $address->address2, $address->city,
            $address->stateprov, $address->postalcode, $address->countrycode, $user->email);
        $gw->setOrder($user->id . "#" . time(), $product->productdesc, 0, 0, "", "");
        $gw->doSale($orderTotal, $ccnumber, $ccexp);
        return $gw->responses;
    }

    /*
     * bitpay payments
     */

    public static function bitPayPayment($req) {
        $data = json_decode($req->getContent(), true);
        $id = $data['id'];
        $transactionId = $id . "#bitpay";
        $client = new \GuzzleHttp\Client();
        $res = $client->request('GET', config('api_endpoints.BitPayAPIURL') . $id);
        $body = json_decode((string) $res->getBody());
        $status = $body->data->status;
        $arr = explode("|", $body->data->orderId, 2);
        $userId = $arr[0];
        $user = User::getById($userId);
        $paymentFor = json_decode(Cache::store('file')->get($userId . 'payment_for'));
//        $path = public_path() . '/bitpay/' . $userId . '/' . $paymentFor . '/' . $userId;
//        File::makeDirectory($path, $mode = 0777, true, true);
//        file_put_contents($path . '/' . $status . '_' . $id . '.json', (string) $res->getBody());

        if ($paymentFor == 'UPGRADE_PACKAGE') {
            $sesData = json_decode(Cache::store('file')->get($userId . 'upg__sesData'), true);
            $product = json_decode(Cache::store('file')->get($userId . 'upg__product'));
            $amount = json_decode(Cache::store('file')->get($userId . 'upg__amount'), true);
            if ($status == 'paid') {
                Helper::afterBitPayPaidStatus($user, $product, $amount, $sesData, $paymentFor, $transactionId);
            }
        }
    }

    private static function afterBitPayPaidStatus($user, $product, $amount, $sessionData, $paymentFor, $transactionId) {
        $paymentMethodId = Helper::createSecondoryPaymentMethodIfNotAvlPrimaryPaymentMethod($user->id, null, null, Helper::createEmptyPaymentRequest($user->firstname, $user->lastname, null), PaymentMethodType::TYPE_BITPAY);
        $orderSubtotal = $product->price;
        $orderTotal = $amount;
        Helper::createNewOrderAfterPayment($user->id, $orderSubtotal, $orderTotal, $paymentMethodId, $sessionData, $product, $transactionId, $paymentFor);
    }

    /*
     * Bit pay payment request
     */

    public static function bitPayPaymentRequest($user, $sesData, $paymentFor) {
        if ($paymentFor == 'UPGRADE_PACKAGE') {
            $product = Product::getById($sesData['upgradeProductId']);
            $amount = $product->price - $sesData['discount'];
            Cache::store('file')->put($user->id . 'payment_for', json_encode($paymentFor), 1000);
            Cache::store('file')->put($user->id . 'upg__product', json_encode($product), 1000);
            Cache::store('file')->put($user->id . 'upg__amount', json_encode($amount), 1000);
            Cache::store('file')->put($user->id . 'upg__sesData', json_encode($sesData), 1000);
        } else {
            return ['error' => 1, 'msg' => 'Invalid payment'];
        }
        if (empty($product)) {
            return ['error' => 1, 'msg' => 'Invalid product'];
        }
        if (empty($amount)) {
            return ['error' => 1, 'msg' => 'Invalid amount'];
        }
        $storageEngine = new \Bitpay\Storage\EncryptedFilesystemStorage(config('api_endpoints.EncryptedFilesystemStorageKey'));
        $tokenStr = config('api_endpoints.BitPayTokenStr');
        $privateKey = $storageEngine->load(storage_path() . '/keys/bitpaytest.pri');
        $publicKey = $storageEngine->load(storage_path() . '/keys/bitpaytest.pub');
        $network = new \Bitpay\Network\Testnet();
        $client = new \Bitpay\Client\Client();
        $curl_options = [
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
        ];
        $adapter = new \Bitpay\Client\Adapter\CurlAdapter($curl_options);
        $client->setPrivateKey($privateKey);
        $client->setPublicKey($publicKey);
        $client->setNetwork($network);
        $client->setAdapter($adapter);
        $token = new \Bitpay\Token();
        $token->setToken($tokenStr);
        $client->setToken($token);
        //new invoice
        $invoice = new \Bitpay\Invoice();
        $buyer = new \Bitpay\Buyer();
        $buyer->setEmail($user->email);
        $invoice->setBuyer($buyer);
        $invoice->setRefundAddresses($user->email);
        //temp item description
        $item = new \Bitpay\Item();
        $item
                ->setCode($product->id)
                ->setDescription($product->productdesc)
                ->setPrice($amount);
        $invoice->setItem($item);
        $invoice->setCurrency(new \Bitpay\Currency('USD'));
        // Configure the rest of the invoice
        $url = \Config::get('api_endpoints.BitPayCallBackURL');
        //local
        $invoice
                ->setOrderId($user->id . '|' . md5(time()))
                ->setRedirectUrl($url)
                ->setNotificationUrl($url . '/bitpay/callback');
        try {
            $client->createInvoice($invoice);
        } catch (\Exception $e) {
            return ['error' => 1, 'message' => 'Bitpay error: ' . $e->getMessage()];
        }
        $url = $invoice->getUrl();
        return ['error' => 0, 'url' => $url];
    }

    /*
     * payment using coupon code
     */

    public static function paymentUsingCouponCode($sesData, $product, $paymentFor, $orderConversionId = null) {
        if ($paymentFor == 'PURCHASE_SHOP_ITEM') {
            $orderSubtotal = $sesData['CheckOutQuantity'] * $product->price;
        } else if ($paymentFor == 'UPGRADE_PACKAGE') {
            $orderSubtotal = $product->price;
        } else if ($paymentFor == 'PURCHASE_TICKET') {
            $orderSubtotal = Product::TICKET_PURCHASE_DISCOUNT_PRICE;
        }
        else if ($paymentFor == 'PURCHASE_EVENTS_TICKET') {
            $orderSubtotal = self::getProductPriceForPurchaseEventsTicket($product);
        }

        //create empty orders
        $payment_method_id = Helper::createEmptyPaymentMethod(Auth::user()->id, null);

        // set the transaction id
        $transactionId = 'COUPON#' . $sesData['discountCode'];
        $orderId = Helper::createNewOrderAfterPayment(Auth::user()->id, $orderSubtotal, $orderTotal = 0, $payment_method_id, $sesData, $product, $transactionId, $paymentFor, $orderConversionId);

        return response()->json(['error' => 0, "orderId" => $orderId, 'userId' => Auth::user()->id]);
    }

    public static function paymentUsingCouponCodeForTicket($sesData, $products, $paymentFor) {
        if ($paymentFor == 'PURCHASE_EVENTS_TICKET') {

            $orderSubtotal = 0;
            foreach ($products as $product)
            {
                $orderSubtotal += self::getProductPriceForPurchaseEventsTicket($product);
            }

            $payment_method_id = Helper::createEmptyPaymentMethod(Auth::user()->id, null);
            Helper::createNewOrderWithMultipleProductsAfterPayment(Auth::user()->id, $orderSubtotal, $orderTotal = 0, $payment_method_id, $sesData, $products, null, $paymentFor);
        }
    }

    /*
     * check card already exist after tokenize
     */

    public static function checkExsitingCardAfterTokenize($req) {
        $tokenExResult = PaymentMethod::generateTokenEx($req->number);
        if ($tokenExResult['error'] == 1) {
            return ['error' => 1, 'msg' => "Invalid card number TOKENEX<br/>" . $tokenExResult['msg']];
        }
        $tokenEx = $tokenExResult['token'];
        //check already exists
        $isFound = PaymentMethod::checkCardAlreadyExists(Auth::user()->id, $tokenEx);
        if (!empty($isFound)) {
            return ['error' => 1, 'msg' => "Card already exists. Please change your payment method"];
        }
        return ['error' => 0, 'token' => $tokenExResult['token']];
    }

    /*
     * payment method and billing address checking in existing card payment
     */

    public static function checkExistingCardAndBillAddress($userId, $paymentMethodId) {
        $paymentMethod = PaymentMethod::select('*')
            ->where('id', $paymentMethodId)
            ->where('userID', $userId)
            ->first();

        if (empty($paymentMethod)) {
            return ['error' => 1, 'msg' => "Invalid payment methods"];
        }
        $billingAddress = Address::find($paymentMethod->bill_addr_id);
        if (empty($billingAddress)) {
            return ['error' => 1, 'msg' => 'Please update your billing address in your profile to proceed.'];
        } else if (empty($billingAddress->address1) || empty($billingAddress->city) || empty($billingAddress->postalcode) || empty($billingAddress->countrycode)) {
            return ['error' => 1, 'msg' => 'Your billing address details are missing in your profile section. Please update your info to proceed.'];
        }
        return ['error' => 0, 'billingAddress' => $billingAddress, 'paymentMethod' => $paymentMethod];
    }


    /*
     * de activate idecide user
     */

    public static function deActivateIdecideUser($userId) {
        $error = 0;
        $msg = "";
        //$idecide = IDecide::getIDecideUserId($userId);
        $idecide = IDecide::getIDecideUserInfo($userId);
        //de activate
        if (!empty($idecide) && IDecide::DEACTIVE == $idecide->status) {
            $error = 1;
            $msg = IDecide::USER_ALREADY_IN_INACTIVE_STATUS;
        } else if (!empty($idecide) && IDecide::DEACTIVE != $idecide->status) {
            $response = IDecide::disableUser($userId);
            if (!empty($response) && !empty($response['response']->success) && $response['response']->success == 1) {
                IDecide::where('idecide_user_id', $idecide->idecide_user_id)->update(['status' => IDecide::DEACTIVE]);
                $error = 0;
                $msg = IDecide::USER_DEACTIVATED_SUCCESSFULLY;
            } else {
                $error = 1;
                if (is_array($response['response']->errors)) {
                    foreach ($response['response']->errors as $err) {
                        $msg = $err . "<br>";
                    }
                } else {
                    $msg = IDecide::USER_NOT_DEACTIVATED_SUCCESSFULLY;
                }
            }
        } else {
            $error = 1;
            $msg = IDecide::USER_ACCOUNT_NOT_FOUND;
        }
        return array("error" => $error, "msg" => $msg);
    }

    /*
     * re activate idecide user
     */

    public static function reActivateIdecideUser($userId) {
        //$idecide = IDecide::getIDecideUserId($userId);
        $idecide = IDecide::getIDecideUserInfo($userId);
        //de activate
        if (!empty($idecide) && IDecide::ACTIVE == $idecide->status) {
            $error = 1;
            $msg = IDecide::USER_ALREADY_IN_ACTIVE_STATUS;
        } else if (!empty($idecide) && IDecide::ACTIVE != $idecide->status) {
            $response = IDecide::enableUser($userId);
            if (!empty($response) && !empty($response['response']->success) && $response['response']->success == 1) {
                IDecide::where('idecide_user_id', $idecide->idecide_user_id)->update(['status' => IDecide::ACTIVE]);
                $error = 0;
                $msg = IDecide::USER_ACTIVATED_SUCCESSFULLY;
            } else {
                $error = 1;
                if (is_array($response['response']->errors)) {
                    foreach ($response['response']->errors as $err) {
                        $msg = $err . "<br>";
                    }
                } else {
                    $msg = IDecide::USER_NOT_ACTIVATED_SUCCESSFULLY;
                }
            }
        } else {
            $error = 1;
            $msg = IDecide::USER_ACCOUNT_NOT_FOUND;
        }
        return array("error" => $error, "msg" => $msg);

    }

    /*
     * de activate save on  user
     */

    public static function deActivateSaveOnUser($userId, $productId, $distid, $note) {
        $error = 0;
        $msg = "";
        $sor = SaveOn::getSORUserInfo($userId);
        if (!empty($sor) && $sor->status == SaveOn::DEACTIVE) {
            $error = 1;
            $msg = SaveOn::USER_ALREADY_IN_INACTIVE_STATUS;
        } else if (!empty($sor->sor_user_id)) {
            $disabledUserRsponse = SaveOn::disableUser($productId, $distid, $note);
            if ($disabledUserRsponse['status'] == 'success' && $disabledUserRsponse['disabled'] == 'true') {
                SaveOn::where('sor_user_id', $sor->sor_user_id)->update(['status' => SaveOn::DEACTIVE]);
                $error = 0;
                $msg = SaveOn::USER_DEACTIVATED_SUCCESSFULLY;
            } else {
                $error = 1;
                $msg = SaveOn::USER_NOT_DEACTIVATED_SUCCESSFULLY;
            }
        } else {
            $error = 1;
            $msg = SaveOn::USER_ACCOUNT_NOT_FOUND;
        }
        return array("error" => $error, "msg" => $msg);
    }

    /*
     * activate save on user
     */

    public static function reActivateSaveOnUser($userId, $productId, $distid, $note) {
        if ($productId == Product::ID_Traverus_Grandfathering) {
            $productId = Product::ID_BUSINESS_CLASS;
        }
        $error = 0;
        $msg = "";
        $sor = SaveOn::getSORUserInfo($userId);
        if (!empty($sor) && $sor->status == SaveOn::ACTIVE) {
            $error = 1;
            $msg = SaveOn::USER_ALREADY_IN_ACTIVE_STATUS;
        } else if (!empty($sor->sor_user_id)) {
            $disabledUserRsponse = SaveOn::enableUser($productId, $distid, $note);
            if ($disabledUserRsponse['status'] == 'success' && $disabledUserRsponse['enabled'] == 'true') {
                $getMemberResponse = Helper::sor_member_info($distid);
                if ($getMemberResponse['status'] == "success") {
                    $response = json_decode($getMemberResponse['response'], true);
                    $response = $response[0];
                    if ($response['Status'] == "Active") {
                        SaveOn::where('sor_user_id', $sor->sor_user_id)->update(['status' => SaveOn::ACTIVE]);
                        $error = 0;
                        $msg = SaveOn::USER_ACTIVATED_SUCCESSFULLY;
                    } else {
                        $error = 1;
                        $msg = SaveOn::USER_NOT_ACTIVATED_SUCCESSFULLY;
                    }
                } else {
                    $error = 1;
                    $msg = $getMemberResponse['msg'];
                }
            }
        } else {
            $error = 1;
            $msg = SaveOn::USER_ACCOUNT_NOT_FOUND;
        }
        return array("error" => $error, "msg" => $msg);
    }

    /*
     * validate check out quantity
     */

    public static function validateCheckOutQuantity($req) {
        $validator = Validator::make($req->all(), [
                    'quantity' => 'required|numeric|min:1|max:100|digits_between:1,3'
                        ], [
                    'quantity.required' => 'Check Out quantity required',
                    'quantity.digits' => 'Check Out quantity should be digits',
                    'digits_between' => 'Please enter the valid quantity',
                    'quantity.numeric' => 'Check Out quantity should be numeric value',
                    'quantity.min' => 'Check Out quantity should be greater than 0',
                    'quantity.max' => 'Check Out quantity should not be greater than 100',
        ]);
        $msg = "";
        $valid = 1;
        if ($validator->fails()) {
            $valid = 0;
            $messages = $validator->messages();
            foreach ($messages->all() as $m) {
                $msg .= "<div> - " . $m . "</div>";
            }
        }
        $res = array();
        $res["valid"] = $valid;
        $res["msg"] = $msg;
        return $res;
    }

    /*
     * check primary address availability, if not available create address as primary,
     * if primary address available create address as secondary address
     */

    public static function createSecondoryAddressIfNotAvlPrimaryAddress($userId, $req, $paymentType) {
        $addressId = null;
        if ($paymentType == PaymentMethodType::TYPE_CREDIT_CARD || $paymentType == PaymentMethodType::TYPE_T1_PAYMENTS) {
            $hasPrimaryAddress = Address::getRec($userId, Address::TYPE_BILLING, 1);
            if (empty($hasPrimaryAddress)) {
                $addressId = Address::addNewRecSecondaryAddress($userId, Address::TYPE_BILLING, 1, $req);
            } else {
                $addressId = $hasPrimaryAddress->id;
            }
        }
        return $addressId;
    }

    /*
     * check primary payment method availability, if not available create payment method as primary,
     * if primary payment method available create address as secondary payment method
     */

    public static function createSecondoryPaymentMethodIfNotAvlPrimaryPaymentMethod($userId, $tokenEx, $addressId, $req, $paymentType = null)
    {
        if ($paymentType == PaymentMethodType::TYPE_CREDIT_CARD ||$paymentType == PaymentMethodType::TYPE_T1_PAYMENTS) {
            $hasPrimaryPaymentMethod = PaymentMethod::getRec($userId, 1, $paymentType);
            if (empty($hasPrimaryPaymentMethod))
                $paymentMethodId = PaymentMethod::addSecondaryCard($userId, 1, $tokenEx, $addressId, $paymentType, $req);
            else
                $paymentMethodId = PaymentMethod::addSecondaryCard($userId, 0, $tokenEx, $addressId, $paymentType, $req);
        } else if ($paymentType == PaymentMethodType::TYPE_ADMIN) {
            $paymentMethodId = PaymentMethod::addSecondaryCard($userId, 0, $tokenEx, $addressId, $paymentType, $req);
        } else {
            $hasSecondaryPaymentMethod = PaymentMethod::getAllRec($userId, $paymentType);
            if ($hasSecondaryPaymentMethod->count() == 0)
                $paymentMethodId = PaymentMethod::addSecondaryCard($userId, 0, $tokenEx, $addressId, $paymentType, $req);
            else
                $paymentMethodId = $hasSecondaryPaymentMethod[0]->id;
        }
        return $paymentMethodId;
    }

    /*
     * NMI & KOUNT payment process for new card
     */

    public static function NMIPaymentProcessUsingNewCard($req, $orderTotal, $product, $sessionId, $email, $phoneNumber, $paymentMethodType = null, $orderConversionId = null)
    {
        $expiry_date = $req->expiry_date;
        $temp = explode("/", $expiry_date);
        if ($paymentMethodType != PaymentMethodType::TYPE_T1_PAYMENTS) {
            // kount
            $kount = new Kount();
            $uniqueId = md5($email . time());
            $kountResponse = $kount->RequestInquiry($req, $orderTotal, $email, $phoneNumber, $product, $uniqueId, $sessionId);
            //dd($kountResponse);
            if (!$kountResponse['success']) {
                return ['error' => 1, 'msg' => "Payment Failed 000 :<br/>" . $kountResponse['message']];
            }
        }
        $nmiResult = NMIGateway::processPayment($req->number, $req->first_name, $req->last_name, $temp[0], $temp[1], $req->cvv, $orderTotal, $req->address1, $req->city, $req->stateprov, $req->postalcode, $req->countrycode, $paymentMethodType, $orderConversionId);
        if ($nmiResult['error'] == 1) {
            if ($paymentMethodType != PaymentMethodType::TYPE_T1_PAYMENTS) {
                $kount->RequestUpdate($sessionId, $kountResponse['transaction_id'], 'D');
            }
            return ['error' => 1, 'msg' => "Payment Failed 111 :<br/>" . $nmiResult['msg']];
        }
        if ($paymentMethodType != PaymentMethodType::TYPE_T1_PAYMENTS) {
            $kount->RequestUpdate($sessionId, $kountResponse['transaction_id'], 'A');
        }
        return $nmiResult;
    }

    /*
     * NMI & KOUNT payment process for existing card
     */

    public static function NMIPaymentProcessUsingExistingCard($userId, $billingAddress, $product, $sesData=null, $paymentMethod, $email, $phoneNumber, $firstName, $lastName, $paymentFor, $orderConversionId = null) {

        if ($paymentFor == 'PURCHASE_SHOP_ITEM') {
            $orderSubtotal = ($sesData['CheckOutQuantity'] * $product->price);
            $orderTotal = ($sesData['CheckOutQuantity'] * $product->price) - $sesData['discount'];
        } else if ($paymentFor == 'UPGRADE_PACKAGE') {
            $orderSubtotal = $product->price;
            $orderTotal = $product->price - $sesData['discount'];
        } else if ($paymentFor == 'FOUNDATION') {
            $orderSubtotal = $product->price;
            $orderTotal = $product->price - $sesData['discount'];
        } else if ($paymentFor == 'PURCHASE_TICKET') {
            $orderSubtotal = Product::TICKET_PURCHASE_DISCOUNT_PRICE;
            $orderTotal = $orderSubtotal - $sesData['discount'];
        } else if ($paymentFor == 'PURCHASE_EVENTS_TICKET') {
            $orderSubtotal = self::getProductPriceForPurchaseEventsTicket($product);
            $orderTotal = $orderSubtotal - $sesData['discount'];
        } else if($paymentFor == 'SHOPPING_CART'){
            $orderTotal = $product['shopping_cart_products']->sum('sub_total');
            
        } else {
            return response()->json(['error' => 1, 'msg' => "Invalid payment method(4053)"]);
        }


       
        //detokenize
        $tokenEx = new tokenexAPI();
        $tokenRes = $tokenEx->detokenizeLog(config('api_endpoints.TOKENEXDetokenize'), $paymentMethod->token);
        $tokenRes = $tokenRes['response'];        
        if (!$tokenRes->Success) {
            return response()->json(['error' => 1, 'msg' => "TokenEx Error(4054): " . $tokenRes->Error]);
        }
        
        
        ///FORCING PAYMENT TO USE PAYARC
        $paymentMethod->pay_method_type = PaymentMethodType::TYPE_PAYARC;
        
        //address
        $user = User::getById($userId);
        
        if (empty($paymentMethod->firstname)) {
            $paymentMethod->firstname = $firstName;
        }
        if (empty($paymentMethod->lastname)) {
            $paymentMethod->lastname = $lastName;
        }

        $nmiResult = NMIGateway::processPayment(
            $tokenRes->Value, 
            $paymentMethod->firstname, 
            $paymentMethod->lastname, 
            $paymentMethod->expMonth, 
            $paymentMethod->expYear, 
            $paymentMethod->cvv, 
            $orderTotal, 
            $billingAddress->address1, 
            $billingAddress->city, 
            $billingAddress->stateprov, 
            $billingAddress->postalcode,
            $billingAddress->countrycode, 
            $paymentMethod->pay_method_type, 
            $orderConversionId
        );

        
        if ($nmiResult['error'] == 1) {
            /*if (($paymentMethod->pay_method_type != PaymentMethodType::TYPE_T1_PAYMENTS)||($paymentMethod->pay_method_type != PaymentMethodType::TYPE_PAYARC)) {
                $kount->RequestUpdate($sesData['sessionId'], $kountResponse['transaction_id'], 'D');
            }*/
            return response()->json(['error' => 1, 'msg' => "Payment Failed(4056):" . $nmiResult['msg']]);
        }
        /*if (($paymentMethod->pay_method_type != PaymentMethodType::TYPE_T1_PAYMENTS)||($paymentMethod->pay_method_type != PaymentMethodType::TYPE_PAYARC)) {
            $kount->RequestUpdate($sesData['sessionId'], $kountResponse['transaction_id'], 'A');
        }*/
        if (!isset($sesData['discountCode'])) {
            $sesData['discountCode'] = '';
        }

        $authorization = $nmiResult['authorization'];
        
        $orderId = Helper::createNewOrderAfterPayment(
            $userId, 
            $orderSubtotal, 
            $orderTotal, 
            $paymentMethod->id, 
            $sesData, 
            $product, 
            $authorization,
            $paymentFor, 
            $orderConversionId
        );
        
        return response()->json(['error' => 0, 'response' => 'Payment processed successfully', "orderId" => $orderId, 'userId' => $userId]);

    }

    public static function createOrder($userId, $orderSubtotal, $orderTotal, $orderBV, $orderQV, $orderCV, $transactionId, $paymentMethodId, $sesData)
    {
        // create new order
        return Order::addNew(
            $userId,
            $orderSubtotal,
            $orderTotal,
            $orderBV,
            $orderQV,
            $orderCV,
            $transactionId,
            $paymentMethodId,
            null,
            null,
            null,
            (isset($sesData['discountCode']) ? $sesData['discountCode'] : '')
        );
    }

    public static function createNewOrderAfterPayment($userId, $orderSubtotal, $orderTotal, $paymentMethodId, $sesData, $product, $transactionId, $orderFor, $orderConversionId = null) {
       //
        $orderBV = $product->bv;
        $orderQV = $product->qv;
        $orderCV = $product->cv;
        // create new order item
        if ($orderFor == 'PURCHASE_SHOP_ITEM') {
                $orderId = Helper::createOrder($userId, $orderSubtotal, $orderTotal, $orderBV * $sesData['CheckOutQuantity'], $orderQV * $sesData['CheckOutQuantity'], $orderCV * $sesData['CheckOutQuantity'], $transactionId, $paymentMethodId, $sesData);
                OrderItem::addNew($orderId, $product->id, $sesData['CheckOutQuantity'], $orderTotal, $orderBV, $orderQV, $orderCV);
        } else if ($orderFor == 'PURCHASE_TICKET') {
            $orderId = Helper::createOrder($userId, $orderSubtotal, $orderTotal, $orderBV * 1, $orderQV * 1, $orderCV * 1, $transactionId, $paymentMethodId, $sesData);
            OrderItem::addNew($orderId, $product->id, 1, Product::TICKET_PURCHASE_DISCOUNT_PRICE, $orderBV, $orderQV, $orderCV);
        } else if ($orderFor == 'PURCHASE_EVENTS_TICKET') {

            $orderId = Helper::createOrder($userId, $orderSubtotal, $orderTotal, $orderBV * 1, $orderQV * 1, $orderCV * 1, $transactionId, $paymentMethodId, $sesData);

            $purchaseProducts = $sesData['products'];

            foreach ($purchaseProducts as $decoratedProduct)
            {
                OrderItem::addNew($orderId, $decoratedProduct->product->id, $decoratedProduct->quantity, $decoratedProduct->product->price, $orderBV, $orderQV, $orderCV);
            }

        } else if ($orderFor == 'UPGRADE_PACKAGE') {
            $orderId = Helper::createOrder($userId, $orderSubtotal, $orderTotal, $orderBV, $orderQV, $orderCV, $transactionId, $paymentMethodId, $sesData);
            OrderItem::addNew($orderId, $product->id, 1, $orderTotal, $orderBV, $orderQV, $orderCV);
            User::setCurrentProductId($userId, $sesData['newProductId']);
            Helper::afterPaymentSuccess($sesData['newProductId'], null, $userId);
            if (!empty($sesData) && isset($sesData['currentProductId']) && $sesData['currentProductId'] == Product::ID_STANDBY_CLASS) {
                $cOrder = Order::getById($orderId);
                $oCreatedDate = date('d', strtotime($cOrder->created_date));
                if ($oCreatedDate >= 25) {
                    $sDate = strtotime(date("Y-m-25", strtotime($cOrder->created_date)) . " +1 month");
                    $sDate = date("Y-m-d", $sDate);
                } else {
                    $sDate = strtotime(date("Y-m-d", strtotime($cOrder->created_date)) . " +1 month");
                    $sDate = date("Y-m-d", $sDate);
                }
                User::where('id', $userId)->update(['next_subscription_date' => $sDate, 'original_subscription_date' => $sDate]);
            }
        } else if ($orderFor == 'FOUNDATION') {
            $orderId = Helper::createOrder($userId, $orderSubtotal, $orderTotal, $orderBV, $orderQV, $orderCV, $transactionId, $paymentMethodId, $sesData);
            OrderItem::addNew($orderId, $product->id, 1, $orderTotal, $orderBV, $orderQV, $orderCV);
        } else if ($orderFor == 'PURCHASE_TICKET') {
            $orderId = Helper::createOrder($userId, $orderSubtotal, $orderTotal, $orderBV, $orderQV, $orderCV, $transactionId, $paymentMethodId, $sesData);
            OrderItem::addNew($orderId, $product->id, 1, $orderTotal, $orderBV, $orderQV, $orderCV);
        } else if ($orderFor == 'PURCHASE_EVENTS_TICKET') {
            $orderId = Helper::createOrder($userId, $orderSubtotal, $orderTotal, $orderBV, $orderQV, $orderCV, $transactionId, $paymentMethodId, $sesData);
            OrderItem::addNew($orderId, $product->id, 1, $orderTotal, $orderBV, $orderQV, $orderCV);
        }
        if (!empty($sesData['discountCode'])) {
            DiscountCoupon::applyCoupon($userId, $sesData['discountCode'], "code", $orderId, $orderSubtotal);
        }

        if ($orderConversionId && isset($orderId)) {
            OrderConversion::setOrderId($orderConversionId, $orderId);
        }

        return $orderId;
    }

    public static function createNewOrderWithMultipleProductsAfterPayment($userId, $orderSubtotal, $orderTotal, $paymentMethodId, $sesData, $purchaseProducts, $transactionId, $orderFor) {

        $decoratedProduct = $purchaseProducts[0];

        $orderBV = $decoratedProduct->product->bv;
        $orderQV = $decoratedProduct->product->qv;
        $orderCV = $decoratedProduct->product->cv;

        if ($orderFor == 'PURCHASE_EVENTS_TICKET') {

            $orderId = Helper::createOrder($userId, $orderSubtotal, $orderTotal, $orderBV * 1, $orderQV * 1, $orderCV * 1, $transactionId, $paymentMethodId, $sesData);

            foreach ($purchaseProducts as $decoratedProduct) {
                OrderItem::addNew($orderId, $decoratedProduct->product->id, $decoratedProduct->quantity, $decoratedProduct->product->price, $decoratedProduct->product->bv, $decoratedProduct->product->qv, $decoratedProduct->product->cv);
            }

            if (!empty($sesData['discountCode'])) {
                DiscountCoupon::applyCoupon($userId, $sesData['discountCode'], "code", $orderId, $orderSubtotal);
            }
        }

        return $orderId;
    }

    public static function checkIsUserPurchaseTicket($userId)
    {
        $orders = DB::table('orders')
            ->select('*')
            ->join('orderItem', 'orders.id', '=', 'orderItem.orderid')
            ->where('orders.userid', $userId)
            ->get();
        $ticket_purchased = false;
        foreach ($orders as $order) {
            if ($order->productid == ProductType::TYPE_TICKET) {
                $ticket_purchased = true;
                break;
            }
        }
        if (!$ticket_purchased) {
            $has = DB::table('check_ticket_purchase')
                ->select('*')
                ->where('user_id', $userId)
                ->where('purchase', 1)
                ->first();
            if (empty($has)) {
                DB::table('check_ticket_purchase')->insert(['user_id' => $userId, 'purchase' => 0]);
                $ticket_purchased = false;
            } else if ($has->purchase == 1) {
                $ticket_purchased = true;
            } else {
                $ticket_purchased = false;
            }
        }
        session(['ticket_purchased' => $ticket_purchased]);
    }

    public static function afterPaymentSuccess($newProductId, $addressId = null, $userId = null) {
        if (is_null($userId))
            $userId = Auth::user()->id;

        // if updated get related product
        $newProductId = User::upgrateProduct($newProductId);


    }

    /*
     * Kount api request construct
     */

    public static function kountApiRequestConstructForExistingCardPayments($tokenRes, $billingAddress, $firstName, $lastName) {
        $userDetails = new \stdClass();
        $userDetails->number = $tokenRes->Value;
        $userDetails->first_name = $firstName;
        $userDetails->last_name = $lastName;
        $userDetails->apt = $billingAddress->apt;
        $userDetails->address1 = $billingAddress->address1;
        $userDetails->city = $billingAddress->city;
        $userDetails->stateprov = $billingAddress->stateprov;
        $userDetails->postalcode = $billingAddress->postalcode;
        $userDetails->countrycode = $billingAddress->countrycode;
        return $userDetails;
    }

    /*
     * empty payment construct
     */

    public static function createEmptyPaymentRequest($firstName, $lastName, $billAddrId) {
        $recEWallet = new \stdClass();
        $recEWallet->token = null;
        $recEWallet->cvv = null;
        $recEWallet->first_name = $firstName;
        $recEWallet->last_name = $lastName;
        $recEWallet->expiry_date = null;
        $recEWallet->bill_addr_id = $billAddrId;
        return $recEWallet;
    }

    /*
     * empty payment method for who purchase using coupon code with total 0
     */

    public static function createEmptyPaymentMethod($userId, $addressId) {
        $emptyPayment = new \stdClass();
        $emptyPayment->userID = $userId;
        $emptyPayment->primary = 0;
        $emptyPayment->pay_method_type = PaymentMethodType::TYPE_COUPON_CODE;
        $emptyPayment->expiry_date = null;
        $emptyPayment->token = null;
        $emptyPayment->cvv = null;
        $emptyPayment->first_name = Auth::user()->firstname;
        $emptyPayment->last_name = Auth::user()->lastname;
        $emptyPayment->expMonth = null;
        $emptyPayment->expYear = null;
        $emptyPayment->bill_addr_id = $addressId;
        $emptyPayment->is_subscription = 0;
        //check payment method already exists
        $hasPaymentMethod = PaymentMethod::getRec($userId, 0, PaymentMethodType::TYPE_COUPON_CODE);
        if (empty($hasPaymentMethod)) {
            $paymentMethodId = PaymentMethod::addSecondaryCard($userId, 0, null, $addressId, PaymentMethodType::TYPE_COUPON_CODE, $emptyPayment);
        } else {
            $paymentMethodId = $hasPaymentMethod->id;
        }
        return $paymentMethodId;
    }

    public static function validateCheckOutPaymentType($req) {
        $validator = Validator::make($req->all(), [
                    'payment_method_id' => 'required'
                        ], [
                    'payment_method_id.required' => 'Payment method cannot be empty'
        ]);
        $msg = "";
        $valid = 1;
        if ($validator->fails()) {
            $valid = 0;
            $messages = $validator->messages();
            foreach ($messages->all() as $m) {
                $msg .= $m;
            }
        }
        $res = array();
        $res["valid"] = $valid;
        $res["msg"] = $msg;
        return $res;
    }

    public static function validatePaymentPage($req) {
        $validator = Validator::make($req->all(), [
                    'first_name' => 'required|max:50',
                    'last_name' => 'required|max:50',
                    'number' => 'required',
                    'cvv' => 'required|max:4',
                    'expiry_date' => 'required|size:7',
                    'address1' => 'required|max:255',
                    'countrycode' => 'required|max:10',
                    'city' => 'required|max:255',
//            'stateprov' => 'required|max:50',
                    'stateprov' => 'max:50',
                    'postalcode' => 'required|max:10',
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
//                    'stateprov.required' => 'State / Province is required',
                    'stateprov.max' => 'State / Province exceed the limit',
                    'postalcode.required' => 'Postal code is required',
                    'postalcode.max' => 'Postal code exceed the limit',
                    'terms.required' => 'Agree to terms and conditions',
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
            // validate expiration date
            $expiryDate = trim(str_replace(' ', '', $req->input('expiry_date')));
            $expireDateParts = explode('/', $expiryDate);

            if (!isset($expireDateParts[0]) || !isset($expireDateParts[1]) || strlen($expireDateParts[0]) != 2 || strlen($expireDateParts[1]) != 4) {
                $valid = 0;
                $msg = 'Invalid Expiration date';
            } else if (!preg_match('/^\d+$/', $expireDateParts[0]) || (!preg_match('/^\d+$/', $expireDateParts[1]) )) {
                $valid = 0;
                $msg = 'Invalid Expiration date';
            }
        }
        $res = array();
        $res["valid"] = $valid;
        $res["msg"] = $msg;
        return $res;
    }

    public static function randomPassword() {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
        $pass = array(); //remember to declare $pass as an array
        $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
        for ($i = 0; $i < 10; $i++) {
            $n = rand(0, $alphaLength);
            $pass[] = $alphabet[$n];
        }
        return implode($pass);
    }

    public static function logApiRequests($userId, $api, $endPoint, $postData) {
        return ApiLogs::create(['user_id' => $userId, 'api' => $api, 'endpoint' => $endPoint, 'request' => json_encode($postData)]);
    }

    public static function logApiResponse($id, $response) {
        ApiLogs::where('id', $id)->update(['response' => $response]);
    }

    public static function checkTMTAllowPayment($countryCode, $userId)
    {
        $countryId = '';
        if (!empty($countryCode)) {
            $countryId = DB::table('country')
                ->where('countrycode', '=', $countryCode)
                ->first();
            if (empty($countryId)) {
                $countryId = Address::where('userid', $userId)->where('countrycode', $countryCode)->where('addrtype', Address::TYPE_REGISTRATION)->whereNotNull('countrycode')->first();
            }
        }
        if (empty($countryId)) {
            return 0;
        } else {
            return count(DB::table('payment_type_country')
                ->where('country_id', '=', $countryId->id)
                ->where('payment_type', '=', PaymentMethodType::TYPE_T1_TYPE)
                ->get());
        }
    }

    /**
     * @param $product
     * @return mixed
     */
    public static function getProductPriceForPurchaseEventsTicket($product)
    {
        $productPrice = $product->product->price;

        if ($product->product->discount_price > 0) {
            $productPrice = $product->product->discount_price;
        }
        return $productPrice;
    }
}

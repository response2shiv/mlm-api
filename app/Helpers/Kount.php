<?php

namespace App\Helpers;

use Config;


class Kount {
    private $url;
    private $apiKey;
    private $merchantId;

    public function __construct()
    {
        $this->url = Config::get('api_endpoints.KOUNTServiceURL');
        $this->apiKey = Config::get('api_endpoints.KOUNTAPIKey');
        $this->merchantId = Config::get('api_endpoints.KOUNTMerchantID');
    }

    public function RequestInquiry($req, $pay, $email, $phone, $product, $uniqueId, $sessionId)
    {
        $orderTotal = ceil($pay * 100);
        $ipAddress = \Request::ip();
        if ($ipAddress == '::1') {
            $ipAddress = '112.135.32.214';
        }

        try {
            $inquiry = new \Kount_Ris_Request_Inquiry();
            $inquiry->setUrl($this->url);
            $inquiry->setApiKey($this->apiKey);
            $inquiry->setMerchantId($this->merchantId);

            $inquiry->setSessionId($sessionId);
            $inquiry->setIpAddress($ipAddress);
            $inquiry->setMack('Y');
            $inquiry->setAuth('A');
            $inquiry->setWebsite("Default");

            //$inquiry->setPayment('TOKEN', $kountHash);
            $inquiry->setPayment('CARD', $req->number);

            $inquiry->setName(trim($req->first_name . ' ' . $req->last_name));
            $inquiry->setEmail($email);

            $address = '';
            if (!empty($req->address1)) {
                $address = $req->address1;
            }

            if (!empty($req->address2)) {
                $address .= " " . $req->address2;
            }
            $inquiry->setBillingAddress(
                $req->apt,
                $address,
                $req->city,
                $req->stateprov,
                $req->postalcode,
                $req->countrycode,
                "",
                ""
            );
            $inquiry->setBillingPhoneNumber($phone);

            $cart = [];
            $cart[] = new \Kount_Ris_Data_CartItem(
                "Digital",
                $product->productname,
                $product->productdesc,
                1,
                $orderTotal
            );

            $inquiry->setCart($cart);
            $inquiry->setOrderNumber($uniqueId);
            $inquiry->setTotal($orderTotal);

            $response = $inquiry->getResponse();

            $auto = (string)$response->getAuto();
            $mode = (string)$response->getMode();

            if ($mode == 'E') {
                $errors = $response->getErrors();
                $message = '';
                foreach ($errors as $error) {
                    $message .= $error . '<br>';
                }

                return [
                    'success' => false,
                    'transaction_id' => $response->getTransactionId(),
                    'message' => $message
                ];
            } else {
                if ($auto == 'A' || $auto == 'R') {

                    return [
                        'success' => true,
                        'transaction_id' => $response->getTransactionId(),
                    ];
                } else {

                    $errors = $response->getErrors();
                    $message = '';
                    foreach ($errors as $error) {
                        $message .= $error . '<br>';
                    }

                    $message = rtrim($message, '<br>');
                    return [
                        'success' => false,
                        'transaction_id' => $response->getTransactionId(),
                        'message' => $message
                    ];
                }
            }
        } catch (\Exception $ex) {
            return [
                'success' => false,
                'message' => $ex->getMessage()
            ];
        }
    }

    public function RequestUpdate($sessionId, $transactionId, $auth)
    {
        try {
            $update = new \Kount_Ris_Request_Update();
            $update->setSessionId($sessionId);
            $update->setTransactionId($transactionId);
            $update->setMack('Y'); // or 'Y' for Merchant Acknowledgement of processing and shipping
            // additional optional setters
            $update->setAuth($auth);  // or 'A' for approved. Gateway Response: Declined = D  Approved = 'A'
            // $update->setAvsz('M');  // Address Verification System Zip Code verification response returned to merchant from processor. Acceptable values are ’M’ for match, ’N’ for no-match, or ’X’ for unsupported or unavailable.
            // $update->setAvst('N');  // Address Verification System Street verification response re- turned to merchant from processor. Acceptable values are ’M’ for match, ’N’ for no-match, or ’X’ for unsupported or unavailable.
            // $update->setCvvr('N');  // Card Verification Value response returned to merchant from processor. Acceptable values are ’M’ for match, ’N’ for no-match, or ’X’ unsupported or unavailable.
            $update->getResponse();
        } catch (\Exception $e) {

        }
    }
}

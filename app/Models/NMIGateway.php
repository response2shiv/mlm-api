<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Model;
use App\Helpers\tokenexAPI;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;

class NMIGateway extends Model
{

    public $timestamps = false;

    const TRANSACTION_TYPE_AUTHORIZATION = 1;
    const TRANSACTION_TYPE_CAPTURE = 2;
    const TRANSACTION_TYPE_SALE = 3;
    const TRANSACTION_TYPE_REFUND = 4;
    const TRANSACTION_TYPE_VOID = 5;

    /**
     * @param $tokenEx
     * @param $firstname
     * @param $lastname
     * @param $expMonth
     * @param $expYear
     * @param $cvv
     * @param $amount
     * @param $authorization
     * @param $lastFourDigitCardNo
     * @param null $pay_method_type
     * @return array
     */
    public static function refundPayment(
        $tokenEx,
        $firstname,
        $lastname,
        $expMonth,
        $expYear,
        $cvv,
        $amount,
        $authorization,
        $lastFourDigitCardNo,
        $pay_method_type = null
    ) {
        $formattedAmount = (int)round($amount * 100);
        $postData = array(
            "TransactionType" => self::TRANSACTION_TYPE_REFUND,
            'TransactionRequest' =>
            array(
                'gateway' =>
                array(
                    'name' => 'NmiGateway',
                    'login' => Config::get('api_endpoints.NMIUsername'),
                    'password' => Config::get('api_endpoints.NMIPassword'),
                ),
                'credit_card' =>
                array(
                    'number' => $tokenEx,
                    'month' => $expMonth,
                    'year' => $expYear,
                    'verification_value' => $cvv,
                    'first_name' => $firstname,
                    'last_name' => $lastname,
                ),
                'transaction' =>
                array(
                    'amount' => $formattedAmount,
                    'authorization' => $authorization,
                    'card_number' => $lastFourDigitCardNo,
                    'first_name' => $firstname,
                    'last_name' => $lastname
                ),
            ),
        );
        if ($pay_method_type == PaymentMethodType::TYPE_T1_PAYMENTS) {
            $postData['TransactionRequest']['gateway']['login'] = Config::get('api_endpoints.t1Username');
            $postData['TransactionRequest']['gateway']['password'] = Config::get('api_endpoints.t1Password');
        }
        if ($pay_method_type == PaymentMethodType::TYPE_PAYARC) {
            $postData['TransactionRequest']['gateway']['login'] = Config::get('api_endpoints.payArcUsername');
            $postData['TransactionRequest']['gateway']['password'] = Config::get('api_endpoints.payArcPassword');
        }

        return self::process($postData);
    }

    /**
     * @param $tokenEx
     * @param $firstName
     * @param $lastName
     * @param $expMonth
     * @param $expYear
     * @param $cvv
     * @param $amount
     * @param $address1
     * @param $city
     * @param $state
     * @param $postalCode
     * @param $countryCode
     * @param int|null $paymentMethodType
     * @param int $transactionType
     * @param int|null $orderConversionId
     * @return array
     */
    public static function processPayment(
        $tokenEx,
        $firstName,
        $lastName,
        $expMonth,
        $expYear,
        $cvv,
        $amount,
        $address1,
        $city,
        $state,
        $postalCode,
        $countryCode,
        $paymentMethodType = null,
        $orderConversionId = null
    ) {
        $loginKey    = 'api_endpoints.NMIUsername';
        $passwordKey = 'api_endpoints.NMIPassword';

        $t1PaymentMethodTypes = [
            PaymentMethodType::TYPE_T1_PAYMENTS,
            PaymentMethodType::TYPE_T1_PAYMENTS_SECONDARY_CC,
        ];

        $payArcT1OverrideEnabled = env('PAYARC_T1_OVERRIDE', false);

        if ($payArcT1OverrideEnabled) {
            $t1PaymentMethodTypes[] = PaymentMethodType::TYPE_PAYARC;
        }

        if (in_array($paymentMethodType, $t1PaymentMethodTypes)) {
            $loginKey = 'api_endpoints.t1Username';
            $passwordKey = 'api_endpoints.t1Password';
            Log::info("Processing using T1");
        } elseif ($paymentMethodType == PaymentMethodType::TYPE_PAYARC) {
            $loginKey = 'api_endpoints.payArcUsername';
            $passwordKey = 'api_endpoints.payArcPassword';
            Log::info("Processing using PayArc");
        }

        $payarcOverride = env('PAYARC_OVERRIDE', false);
        if($payarcOverride){
            $loginKey = 'api_endpoints.payArcUsername';
            $passwordKey = 'api_endpoints.payArcPassword';
            Log::info("Processing using PayArc");
        }

        // All amounts are in integer format (eg $1.00 is 100, num * 100), except the input amount
        // Required for NMI gateway and the billing system follows the same logic
        $amount *= 100;

        $currency = "USD";

        if ($orderConversionId) {
            // $orderConversion = OrderConversion::query()->find($orderConversionId)->whereNull('order_id');
            $orderConversion = OrderConversion::find($orderConversionId);

            if (!$orderConversion) {
                Log::info("Failed on conversion ID");
                return array(
                    'error' => 1,
                    'msg' => 'Error(4057): Please refresh the page and try checking out again.'
                );
            }

            // $time = now();
            // $minutesDifference = $time->diffInMinutes($orderConversion->created_at, true);

            // if ($minutesDifference > 30) {
            //     $orderConversion->delete();
            //     return array(
            //         'error' => 1,
            //         'msg' => 'Please refresh the page and try checking out again.'
            //     );
            // }

            $originalAmount = $orderConversion->original_amount;

            // Final verification, in case someone messed up some javascript and passed an old id
            if (trim($originalAmount) != trim($amount)) {
                // $orderConversion->delete();
                Log::info("Failed on conversion");
                return array(
                    'error' => 1,
                    'msg' => 'Error(4058): Please refresh the page and try checking out again'
                );
            }

            $currency = $orderConversion->converted_currency;
            $amount = $orderConversion->converted_amount;
        }

        $postData = array(
            "TransactionType" => self::TRANSACTION_TYPE_SALE,
            'TransactionRequest' => array(
                'gateway' => array(
                    'name' => 'NmiGateway',
                    'login' => Config::get($loginKey),
                    'password' => Config::get($passwordKey),
                ),

                'credit_card' => array(
                    'number' => $tokenEx,
                    'month' => $expMonth,
                    'year' => $expYear,
                    'verification_value' => $cvv,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                ),

                'transaction' => array(
                    'amount' => $amount,
                    'currency' => $currency,
                    'billing_address' => array(
                        'address1' => $address1,
                        'city' => $city,
                        'state' => $state,
                        'zip' => $postalCode,
                        'country' => $countryCode,
                    ),
                ),
            ),
        );

        if ($currency) {
            $postData['transaction']['currency'] = $currency;
        }

        return self::process($postData);
    }

    /**
     * @param $postData
     * @return array
     */
    private static function process($postData)
    {
        $response = null;
        $authorization = null;

        try {
            $response = (new tokenexAPI())
                ->processTransactionAndTokenize('ProcessTransactionAndTokenize', $postData);
            $response = json_decode($response);

            if ($response->TransactionResult) {
                $error = 0;
                $msg = null;
                $authorization = $response->Authorization;
            } else {
                $error = 1;
                $msg = "";

                if (isset($response->Error))
                    $msg .= $response->Error . "<br/>";

                if (isset($response->Message))
                    $msg .= $response->Message;

                $authorization = null;
            }
        } catch (Exception $ex) {
            $msg = $ex->getMessage();
            $error = 1;
        }

        $result = array(
            'error' => $error,
            'msg' => $msg,
            'authorization' => $authorization,
            'request' => $postData,
            'response' => $response
        );
        //dd($result);
        return $result;
    }
}

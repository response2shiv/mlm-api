<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use GuzzleHttp\Client;
use App\Models\UnicryptInvoiceTracker;
use App\Models\MerchantTransactionTracker;
use Exception;

class Unicrypt extends Model
{
    public static function create($user, $amount, $product_name, $product_description, $callback_url)
    {
        $orderId = md5($user->id . '_' . date('Y-m-d_h_i_s_') . mt_rand(1000, 9999));

        $createInvoiceResponse = static::createInvoice($orderId, $amount, $product_name, $product_description, $callback_url);

        if ($createInvoiceResponse['success'] !== true && $createInvoiceResponse['success'] !== 'true') {
            return array($success = false, $createInvoiceResponse, null);
        }

        $orderHash = $createInvoiceResponse['orderhash'];

        $allocateInvoiceResponse = static::allocateInvoiceToClient($orderHash, $user->firstname, $user->lastname, $user->email);

        return array(
            "redirect_url" => 'https://unicrypt.com/pay/gateway?orderhash=' . $createInvoiceResponse['orderhash'],
            "invoice" => $createInvoiceResponse,
            "client_allocate" => $allocateInvoiceResponse,
            "orderhash" => $createInvoiceResponse['orderhash']
        );
    }

    private static function createInvoice($orderId, $amount, $orderDesc, $orderSummary, $callback_url)
    {
        $guzzleClient = new Client();

        $response = $guzzleClient->get('https://unicrypt.com/pay/submit', [
            'query' => [
                'orderid' => $orderId,
                'amount' => $amount,
                'orderdesc' => $orderDesc,
                'ordersummary' => $orderSummary,
                'currency' => 'USD',
                'callback' => $callback_url,
                'merchkey' => env('UNICRYPT_MERCHANT_KEY')
            ]
        ]);

        if ($response->getBody()->getSize() == 0) {
            return [
                'orderhash' => null,
                'error' => 'Empty response from gateway',
                'success' => false
            ];
        }

        try {
            $json = json_decode($response->getBody()->getContents(), true);

            $merchtracker = new MerchantTransactionTracker();
            $merchtracker->merchant_id = 5;
            $merchtracker->transaction_id = $json['orderhash'];
            $merchtracker->status = 'UNPAID';
            $merchtracker->save();

            return $json;
        } catch (Exception $e) {
            return null;
        }
    }

    private static function allocateInvoiceToClient($orderHash, $firstName, $lastName, $email)
    {
        $guzzleClient = new Client();

        $response = $guzzleClient->get('https://unicrypt.com/pay/allocate', [
            'query' => [
                'orderhash' => $orderHash,
                'fname' => $firstName,
                'lname' => $lastName,
                'email' => $email,
            ]
        ]);

        if ($response->getBody()->getSize() == 0) {
            return [
                'status' => 'failure',
                'error' => 'Empty response from gateway',
                'error_reason' => 'Internal'
            ];
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    public static function getOrderStatus($orderHash)
    {
        try {

            $guzzleClient = new Client();

            $response = $guzzleClient->get('https://unicrypt.com/pay/status', [
                'query' => [
                    'orderhash' => $orderHash,
                    'merchkey' => env('UNICRYPT_MERCHANT_KEY')
                ]
            ]);

            if ($response->getBody()->getSize() == 0) {
                return [
                    'error' => 'Empty response from gateway'
                ];
            }
            $json = json_decode($response->getBody()->getContents(), true);
            return $json;
        } catch (Exception $e) {
            return null;
        }
    }
}

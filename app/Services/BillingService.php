<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Database\Eloquent\Model;


class BillingService extends Model
{

    private const ADD_PAYMENT_METHOD_API_URL = 'v1/api/user/customers/create-payment-method';

    public static function processShoppingCart(
        $preOrder,
        $card_number,
        $expiration_year,
        $expiration_month,
        $cvv,
        $billingAddress,
        $shippingAddress,
        $redirect_3ds_url = null,
        $currency = null,
        $userIpAddress
    ) {

        $user = Auth()->user();

        $data = [
            'first_name' => $user->firstname,
            'last_name' => $user->lastname,
            'email' => $user->email,
            'phone' => $user->phonenumber,
            'card_number' => $card_number,
            'expiration_year' => $expiration_year,
            'expiration_month' => $expiration_month,
            'cvv' => $cvv,
            'ip_address' => $userIpAddress ?: '127.0.0.1',
            'order_id' => $preOrder->id,
            'order_desc' => 'Shopping Cart',
            'tax' => $preOrder->ordertax ?: 0,
            'currency' => $currency ?: "USD",
            'amount' => $preOrder->ordertotal,
            'shipping' => 0,
            'address1' => $billingAddress->address1,
            'address2' => $billingAddress->address2,
            'city' => $billingAddress->city,
            'state' => $billingAddress->state,
            'zip' => $billingAddress->zipcode,
            'country' => $billingAddress->country_code,
            'shipping_first_name' => $user->firstname,
            'shipping_last_name' => $user->lastname,
            'shipping_address1' => $shippingAddress->address1,
            'shipping_city' => $shippingAddress->city,
            'shipping_state' => $shippingAddress->state,
            'shipping_zip' => $shippingAddress->zipcode,
            'shipping_country' => $shippingAddress->country_code,
            'shipping_phone' => $user->phonenumber,
            'shipping_email' => $user->email,
            '3d_redirect_url' => $redirect_3ds_url ?: 'https://myibuumrang.com/thank-you',
            'merchant_rotation_id' => env('BILLING_MERCHANT_ROTATION_ID')
        ];


        $request = new Client();

        $url =  env('BILLING_BASE_URL');
        $api_token = env('BILLING_API_TOKEN');

        $endpoint = 'https://' . $url . '/v1/api/payment/process';


        $billingResponse = $request->post($endpoint, [
            'json' => $data,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_token
            ]
        ]);

        $response = json_decode($billingResponse->getBody());

        return $response;
    }

    public static function addPaymentMethod($addressInfo, $cardInfo)
    {
        $baseUrl = env('BILLING_BASE_URL');
        $apiToken = env('BILLING_API_TOKEN');

        $user = \Auth::user();

        $client = new Client();
        $baseUrl = 'https://' . $baseUrl . '/';


        $cardInfo['number'] = str_replace(' ', '', $cardInfo['number']);

        try {
            $result = $client->post($baseUrl . self::ADD_PAYMENT_METHOD_API_URL, [
                'form_params' => [
                    'email' => $user->email,
                    'first_name' => $cardInfo['first_name'],
                    'last_name' => $cardInfo['last_name'],
                    'address1' => $addressInfo['address1'],
                    'address2' =>  $addressInfo['address1'],
                    'city' => $addressInfo['city'],
                    'state' => $addressInfo['stateprov'],
                    'zip' => $addressInfo['postalcode'],
                    'country' => $addressInfo['countrycode'],
                    'phone' => $user->phonenumber ?? $user->mobilenumber,
                    'card_number' => trim($cardInfo['number']),
                    'expiration_year' => substr($cardInfo['expiry_date'], -4),
                    'expiration_month' => substr($cardInfo['expiry_date'], 0, 2)
                ],
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiToken,
                    'timeout' => 60
                ]
            ]);

            $responseJson = $result->getBody()->getContents();
            return json_decode($responseJson, true);
        } catch (Exception $e) {
            return $e;
        }
    }
}

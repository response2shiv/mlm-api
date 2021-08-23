<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use GuzzleHttp\Client;
use Log;

class Ipaytotal extends Model
{
    //
    public static function getOrderStatus($tracker)
    {
        try {
            $guzzleClient = new Client();

            $response = $guzzleClient->post( \Config::get('api_endpoints.ipaytotal_url') . '/api/get/transaction', [
                'query' => [
                    'api_key' => \Config::get('api_endpoints.ipaytotal_key'),
                    'order_id' => $tracker->transaction_id,                    
                    "sulte_apt_no" => 13444
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

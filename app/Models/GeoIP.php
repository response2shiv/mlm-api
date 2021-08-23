<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use GuzzleHttp\Client;

class GeoIP extends Model
{
   
    public static function getInformationFromIP($ip){
        
        $guzzleClient = new Client();;
        $endpoint = "https://ip-geo-location.p.rapidapi.com";
        $apikey   = "9f65053febmshbb55509705f18adp1e99cajsn5e2e0654a78a";
   
        $baseUrl = $endpoint . '/ip/'.$ip;

        try {
            $result = $guzzleClient->get($baseUrl, [
                'query' => [
                    'format' => 'json'
                ],
                'headers' => [
                    'x-rapidapi-host' => 'ip-geo-location.p.rapidapi.com',
                    'x-rapidapi-key' => '9f65053febmshbb55509705f18adp1e99cajsn5e2e0654a78a',
                    'useQueryString' => true
                ]
            ]);

            $response = $result->getBody()->getContents();
             
            if(isset($response)){
                return $response;
            }else{
                return "US";
            }            
        } catch (Exception $e) {
            return "US";
        }
    }
}

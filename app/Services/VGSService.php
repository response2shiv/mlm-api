<?php

namespace App\Services;

use Facade\FlareClient\Http\Client;
use GuzzleHttp\Client as GuzzleHttpClient;
use Illuminate\Support\Facades\Config;

class VGSService
{
    public static function sendData($payload, $endpoint)
    {

        $client = new GuzzleHttpClient();

        $base_url =  Config::get('api_endpoints.vgs_url');
        $response = $client->request('post', "{$base_url}/{$endpoint}", [
            'json' => $payload
        ]);

        $data = json_decode($response->getBody());

        return $data->data;
    }
}

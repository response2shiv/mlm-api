<?php

namespace App\Helpers;
use Config;

class  IDecideInteractiveAPI
{
    private $apiUser;
    private $apiKey;
    private $service_url;
    private $live;

    public function __construct()
    {
        //test
        $this->live         = 'test';
        $this->apiUser      = Config::get('api_endpoints.IDecideUserName');
        $this->apiKey       = Config::get('api_endpoints.IDecidePassword');
        $this->service_url  = Config::get('api_endpoints.IDecideServiceURL');
    }

    public function _get($endPoint)
    {
        $headers = array(
            'Content-Type' => 'application/json'
        );
        $query["apiUser"] = $this->apiUser;
        $query["apiKey"] = $this->apiKey;

        $client = new \GuzzleHttp\Client();
        $options = [
            'headers' => $headers,
            'query' => http_build_query($query)
        ];

        $response = $client->get($this->service_url . $this->live . $endPoint, $options);
        return (string)$response->getBody();
    }

    public function _post($postData, $endPoint)
    {
        $headers = array(
            'Content-Type' => 'application/json'
        );
        $query["apiUser"] = $this->apiUser;
        $query["apiKey"] = $this->apiKey;
        $client = new \GuzzleHttp\Client();
        $options = [
            'headers' => $headers,
            'query' => http_build_query($query),
            'json' => $postData
        ];
        $response = $client->post($this->service_url . $endPoint, $options);
        return (string)$response->getBody();
    }

}

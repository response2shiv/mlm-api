<?php

namespace App\Helpers;

class tokenexAPI
{
    private $api_key;
    private $token_ex_id;
    private $service_url;

    public function __construct()
    {
        $this->service_url = config('api_endpoints.TOKENEXAPIServiceURL');
        $this->api_key = config('api_endpoints.TOKENEXAPIKey');
        $this->token_ex_id = config('api_endpoints.TOKENEXTokenEXId');
    }

    public function tokenize($endPoint, $data)
    {
        $headers = array(
            'Content-Type' => 'application/json'
        );
        $postData["APIKey"] = $this->api_key;
        $postData["TokenExID"] = $this->token_ex_id;
        $postData["Data"] = $data;
        $postData["TokenScheme"] = 1;
        $client = new \GuzzleHttp\Client(["base_uri" => $this->service_url . 'TokenServices.svc/REST/']);
        $options = [
            'headers' => $headers,
            'json' => $postData
        ];
        $response = $client->post($endPoint, $options);
        return (string)$response->getBody();
    }

    public function detokenize($endPoint, $token)
    {
        $headers = array(
            'Content-Type' => 'application/json'
        );

        $postData["APIKey"] = $this->api_key;
        $postData["TokenExID"] = $this->token_ex_id;
        $postData["Token"] = $token;
        $client = new \GuzzleHttp\Client(["base_uri" => $this->service_url . 'TokenServices.svc/REST/']);
        $options = [
            'headers' => $headers,
            'json' => $postData
        ];
        $response = $client->post($endPoint, $options);
        return (string)$response->getBody();
    }

    public function processTransactionAndTokenize($endPoint, $postData)
    {
        //dd($this->service_url, $endPoint);
        $headers = array(
            'Content-Type' => 'application/json'
        );
        $postData["APIKey"] = $this->api_key;
        $postData["TokenExID"] = $this->token_ex_id;
        $postData["TokenScheme"] = 3;
        $client = new \GuzzleHttp\Client(["base_uri" => $this->service_url . '/PaymentServices.svc/REST/']);
        $options = [
            'headers' => $headers,
            'json' => $postData,
            'verify' => false,
            'proxy' => [
                'https' => 'USm6oNacbtN5aXcTUjqV3gWU:c20adefb-1efb-446d-9654-0e7e9ed41441@tnt467848vl.sandbox.verygoodproxy.com:8080',
            ]
        ];

        $response = $client->post($endPoint, $options);

        // dd((string)$response->getBody());

        return (string)$response->getBody();
    }

    public function getKountHashValue($endPoint, $token)
    {
        $postData = array(
            'TokenExID' => $this->token_ex_id,
            'APIKey' => $this->api_key,
            'Token' => $token,
        );
        $headers = [
            'Content-Type' => 'application/json',
        ];
        $client = new \GuzzleHttp\Client(["base_uri" => $this->service_url . '/FraudServices.svc/REST/']);
        $options = [
            'headers' => $headers,
            'json' => $postData
        ];
        $response = $client->post($endPoint, $options);
        return (string)$response->getBody();
    }

    public function getKountHashValueAndTokenize($endPoint, $data)
    {
        $postData = array(
            'TokenExID' => $this->token_ex_id,
            'APIKey' => $this->api_key,
            'Data' => $data,
            'Encrypted' => false,
            'TokenScheme' => 1
        );
        $headers = [
            'Content-Type' => 'application/json'
        ];
        $client = new \GuzzleHttp\Client(["base_uri" => $this->service_url . '/FraudServices.svc/REST/']);
        $options = [
            'headers' => $headers,
            'json' => $postData
        ];
        $response = $client->post($endPoint, $options);
        return (string)$response->getBody();
    }


    public function getKountHashValueAndTokenizeLog($data)
    {
        $postData = array(
            'TokenExID' => $this->token_ex_id,
            'APIKey' => $this->api_key,
            'Token' => $data,
        );
        $headers = [
            'Content-Type' => 'application/json'
        ];
        $client = new \GuzzleHttp\Client(["base_uri" => $this->service_url . '/FraudServices.svc/REST/']);
        $options = [
            'headers' => $headers,
            'json' => $postData
        ];
        $response = $client->post(config('api_endpoints.TOKENEXGetKountHashValue'), $options);
        return array('response' => json_decode((string)$response->getBody()), 'request' => $options, 'api_endpoint' => config('api_endpoints.TOKENEXGetKountHashValue'));
    }

    public function detokenizeLog($endPoint, $token)
    {
        $headers = array(
            'Content-Type' => 'application/json'
        );
        $postData["APIKey"] = $this->api_key;
        $postData["TokenExID"] = $this->token_ex_id;
        $postData["Token"] = $token;
        $client = new \GuzzleHttp\Client(["base_uri" => $this->service_url . 'TokenServices.svc/REST/']);
        $options = [
            'headers' => $headers,
            'json' => $postData
        ];
        $response = $client->post($endPoint, $options);
        return array('response' => json_decode((string)$response->getBody()), 'request' => $options);
    }
}

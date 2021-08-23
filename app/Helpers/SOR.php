<?php

class SOR {

    private $service_url;
    private $user_name;
    private $secret;
    private $clubId;

    public function __construct($product) {
        // Base SOR URL
        $this->service_url = \Config::get('api_endpoints.SaveOnServiceURL');
        //
        if ($product == 1) {
            // standby
            $this->user_name = \Config::get('api_endpoints.iGo4Less0Username');
            $this->secret =  \Config::get('api_endpoints.iGo4Less0Password');
            $this->clubId = \Config::get('api_endpoints.iGo4Less0ClubId');
        } else if ($product == 2) {
            // coach
            $this->user_name = \Config::get('api_endpoints.iGo4Less1Username');
            $this->secret =  \Config::get('api_endpoints.iGo4Less1Password');
            $this->clubId = \Config::get('api_endpoints.iGo4Less1ClubId');

        } else if ($product == 3) {
            // businss
            $this->user_name = \Config::get('api_endpoints.iGo4Less2Username');
            $this->secret =  \Config::get('api_endpoints.iGo4Less2Password');
            $this->clubId = \Config::get('api_endpoints.iGo4Less2ClubId');
        } else if (in_array($product, [4,13,16,14])) {
            // first class OR premium first class
            $this->user_name = \Config::get('api_endpoints.iGo4Less3Username');
            $this->secret =  \Config::get('api_endpoints.iGo4Less3Password');
            $this->clubId = \Config::get('api_endpoints.iGo4Less3ClubId');
        }
    }

    public function _post($endPoint, $postData, $version) {
        $headers = array();
        if (!($endPoint == config('api_endpoints.SORGetMemberInfo') || $endPoint == config('api_endpoints.SORGetLoginToken') || $endPoint == config('api_endpoints.SORUserTransfer'))) {
            $headers = array(
                'Content-Type' => 'application/json',
                "x-saveon-secret" => $this->secret,
                "x-saveon-username" => $this->user_name
            );
        } else {
            $headers = array(
                'Content-Type' => 'application/json',
            );
        }
        if ($endPoint == config('api_endpoints.SORGetLoginToken')) {
            $postData['APIUsername'] = $this->user_name;
            $postData['APIPassword'] = $this->secret;
        } else if ($endPoint == config('api_endpoints.SORUserTransfer')) {
            $postData['APIUsername'] = $this->user_name;
            $postData['APIPassword'] = $this->secret;
            $postData['NewClubID'] = $this->clubId;
        }else if ($endPoint == config('api_endpoints.SORDeactivatedUser')) {
            $postData['APIUsername'] = $this->user_name;
            $postData['APIPassword'] = $this->secret;
        } else if ($endPoint == config('api_endpoints.SORActivateUser')) {
            $postData['APIUsername'] = $this->user_name;
            $postData['APIPassword'] = $this->secret;
        }else if ($endPoint == config('api_endpoints.SORGetMemberInfo')) {
            $postData['APIUsername'] = $this->user_name;
            $postData['APIPassword'] = $this->secret;
        } else if ($endPoint == config('api_endpoints.SORGetMembers')) {
            $postData['APIUsername'] = $this->user_name;
            $postData['APIPassword'] = $this->secret;
        }
        $client = new \GuzzleHttp\Client();
        $options = [
            'headers' => $headers,
            'json' => $postData
        ];
        if ($version) {
            $response = $client->post($this->service_url . 'v2/' . $endPoint, $options);
        } else {
            $response = $client->post($this->service_url . $endPoint, $options);
        }
        return (string) $response->getBody();
    }


    public function _postTransferRequest($endPoint, $postData)
    {
        $postData['APIUsername'] = $this->user_name;
        $postData['APIPassword'] = $this->secret;
        $postData['NewClubID'] = $this->clubId;
        $client = new \GuzzleHttp\Client();
        $options = [
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'json' => $postData
        ];
        $response = $client->post($this->service_url . $endPoint, $options);
        return json_encode(array('status_code' => (string)$response->getStatusCode(), 'response' => json_decode((string)$response->getBody())));
    }

}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Config;
use Exception;
use Log;

class VGSController extends Controller
{
    //This is the class I will call using POSTMAN
    public function addCard(Request $request){
        $req = Request()->all();
        Log::info("received -> ",$request->all());

        $client = new Client();
        $baseUrl = 'https://tnthxwntcbj.sandbox.verygoodproxy.com/post';
        try {
            $result = $client->post($baseUrl, [
                'json' => $request->all(),
                'headers' => [
                    'Content-type' => 'application/json'
                ]
            ]);

            $responseJson = $result->getBody()->getContents();
            //Log::info("response from VGS -> ",$responseJson);
            $this->setMessage("Card received");
            $this->setResponseCode(200);
            return json_decode($responseJson, true);
        } catch (Exception $e) {
            $this->setMessage("Failed - ".$e->getMessage());
            $this->setResponseCode(500);
        }

        
        $this->setResponse($request->all());
        return $this->showResponse();
    }

    //This is the class VGS will call after card is tokenized
    public function addCardHook(Request $request){
        $req = Request()->all();
        Log::info("addCardHook received -> ",$request->all());

        $this->setMessage("Card received");
        $this->setResponseCode(200);
        $this->setResponse($request->all());
        return $this->showResponse();
    }

    public function outbound(Request $request){
        $client = new Client();
        $baseUrl = 'https://api.dev2.bitjarlabs.com/api/vgs/receive-data';

        try {

            // $client->request('POST', $baseUrl, ['proxy' => 'USqQx6dUgkVCrtjky5Xkd6Rg:b4a4fa0d-1ea9-4220-acbf-d3d18c54a0f2@tnthxwntcbj.sandbox.verygoodproxy.com']);
            $result = $client->post($baseUrl, [
                'proxy' => 'USqQx6dUgkVCrtjky5Xkd6Rg:b4a4fa0d-1ea9-4220-acbf-d3d18c54a0f2@tnthxwntcbj.sandbox.verygoodproxy.com',
                'json' => $request->all(),
                'headers' => [
                    'Content-type' => 'application/json'
                ]
            ]);

            $responseJson = $result->getBody()->getContents();
            //Log::info("response from VGS -> ",$responseJson);
            $this->setMessage("Card received");
            $this->setResponseCode(200);
            return json_decode($responseJson, true);
        } catch (Exception $e) {
            $this->setMessage("Failed - ".$e->getMessage());
            $this->setResponseCode(500);
        }
    }
}

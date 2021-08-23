<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Log;
use Validtaor;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public $response_code   = 0;
    public $message         = array();
    public $response;
    public $successStatus   = 200;

    /**
     * Just to set the correct timezone
     */
    public function __construct() {
        date_default_timezone_set('America/Chicago');
    }

    /*
    * Getters and setters
    */
    public function setResponseCode($response_code){
        $this->response_code = $response_code;
    }
    public function getResponseCode(){
        return $this->response_code;
    }
    public function setMessage($message){
        $this->message[] = $message;
    }
    public function getMessage(){
        return $this->message;
    }
    public function setResponse($response){
        $this->response = $response;
    }
    public function getResponse(){
        return $this->response;
    }

    /*
    * Return json response
    */
    public function showResponse(){
        /*return Response::json(array('data' => $this->response,
        'errors' => $this->errors,
        'error_code' => $this->error_code));*/
        $json = array(
            'data' => $this->response,
            'message' => $this->getMessage(),
            'response_code' => $this->getResponseCode());

        if($this->response_code==0){
            $this->setResponseCode(200);
        }
        return response()->json($json, $this->getResponseCode());
    }

    /*
    * Parse errors from the validation class
    */
    public function parseErrors($validator){
        $messages = $validator->errors();
        foreach ($messages->all() as $message) {
            //$errors[] = $message;
            $this->setMessage($message);
        }
    }

    /*
    * Show logs
    */
    public function consoleLog($message, $data = array(), $show=true){
        if($show){
            Log::info($message, $data);
        }
    }

    protected function generateErrorMessageFromValidator($validator)
    {
        $errorMessage = '';

        if ($validator->fails()) {
            foreach ($validator->messages()->all() as $message) {
                $errorMessage .= "<div> - " . $message . "</div>";
            }
        }

        return $errorMessage;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiRequest extends Model {

    protected $table = "api_requests";
    public $timestamps = false;

    public static function addNew($token, $status) {
        //
        $req = request();
        $requestUri = $req->getRequestUri();
        $baseUrl = $req->getBaseUrl();
        //
        $r = new ApiRequest();
        $r->token = $token;
        $r->request = str_replace($baseUrl, "", $requestUri);
        $r->status = $status;
        $r->request_on = \utill::getCurrentDateTime();
        $r->save();
    }

}

<?php

namespace App\Http\Controllers\Affiliates;
use App\Http\Controllers\Controller;


use Illuminate\Http\Request;
use App\Models\Media;
use App\Models\PromoInfo;
use App\Models\Product;
use Auth;
use DB;

class ShopController extends Controller
{
    //
    public function index(Request $request)
    {
        $d['products'] = Product::getProductsByCountryCode($request->all());
        $this->setResponse($d);
        $this->setResponseCode(200);
        return $this->showResponse();
    }

    public function getProduct($productId, $country=null){

        $d['product'] = Product::getProductByCountryCodeById($country, $productId);
        $this->setResponse($d);
        $this->setResponseCode(200);
        return $this->showResponse();
    }

}

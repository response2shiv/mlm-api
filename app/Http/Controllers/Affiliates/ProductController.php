<?php

namespace App\Http\Controllers\Affiliates;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Helpers\CurrencyConverter;
use App\Models\OrderConversion;

use App\Models\Product;
use Log;

class ProductController extends Controller
{
    /**
     * ProductController constructor.
     */
    public function __construct() {
    }
    
    /**
     * Get product by ID
     */
    public function getProductById($id){
        $product = Product::getProduct($id);
        
        if($product){
            $this->setResponseCode(200);
            $this->setResponse($product);
        }else{
            $this->setResponseCode(404);
            $this->setMessage('Product Not Found.');
        }
        
        return $this->showResponse();
    }
    
    /**
     * Get product by ID and Currency
     */
    public function getProductByIdAndCurrency(request $request){
        $baseUrl = env('BILLING_BASE_URL') ;
        $apiToken = env('BILLING_API_TOKEN');

        if (!$baseUrl || !$apiToken) {
            return response()->json([
                'success' => false,
                'errors' => [
                    'server' => 'An internal error has occurred'
                ],
                'amount' => -1,
                'display_amount' => 'N/A'
            ])->setStatusCode(500);
        }
        
        $product = Product::getProduct(request()->get('product_id'));
        $price = $product->price ? number_format($product->price,2,'','') : number_format(request()->get('amount'),2,'','');
        $request->request->add(['amount' => $price]);
        $validator = $this->checkValidator();

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()->toArray(),
                'amount' => -1,
                'display_amount' => 'N/A'
            ])->setStatusCode(400);
        }

        $amount = request()->get('amount');
        $country = request()->get('country');
        $locale = request()->get('locale');
        
        
        $response = CurrencyConverter::convert($baseUrl, $apiToken, $amount, $country, $locale);

        // JSON is null or issue with
        if (!$response) {
            return response()->json([
                'success' => false,
                'errors' => [
                    'server' => 'An internal error has occurred'
                ],
                'amount' => $amount,
                'display_amount' => 'N/A'
            ])->setStatusCode(500);
        }

        if ($response['success'] !== 1) {
            $errors = $response['errors'];

            return response()->json([
                'success' => 0,
                'errors' => $errors,
                'amount' => -1,
                'display_amount' => 'N/A'
            ])->setStatusCode(400);
        }

        $orderConversion = new OrderConversion();
        
        $orderConversion->fill([
            'session_id' => session_id(),
            'original_amount' => $amount,
            'original_currency' => 'USD',
            'converted_amount' => $response['amount'],
            'converted_currency' => $response['currency'],
            'exchange_rate' => $response['exchange_rate'],
            'expires_at' => now()->addMinutes(30),
            'display_amount' => $response['display_amount']
            
        ]);

        $orderConversion->save();

        $response['order_conversion_id'] = $orderConversion->id;
        $response['expiration'] = $orderConversion->expires_at->timestamp;

        $product->price = number_format($product->price,2,'.',',');
        
        foreach($product as $k => $v){
            $response[$k] = $v;
        }
        return response()->json($response);
    }
    
    private function checkValidator()
    {
        $rules = [
            // 'amount' => 'required|numeric',
            'country' => 'required|size:2|string',
            // 'locale' => 'required|regex:/^[a-z]{2}_[A-Z]{2}$/',
            'product_id' => 'required|integer|exists:products,id'
        ];

        return Validator::make(request()->only(['amount', 'country', 'locale', 'product_id']), $rules);
    }
}

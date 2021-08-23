<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\OrderConversion;
use App\Helpers\CurrencyConverter;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Log;

class CurrencyController extends Controller
{
    private const CONVERT_API_URL = 'v1/api/currency/convert';
    private $guzzleClient;

    public function __construct()
    {
        $this->guzzleClient = new Client();
        parent::__construct();
    }

    private function getConvertValidator()
    {
        $rules = [
            'amount' => 'required|integer',
            'country' => 'required|size:2|string',
            'locale' => 'required|regex:/^[a-z]{2}_[A-Z]{2}$/'
        ];

        return Validator::make(request()->only(['amount', 'country', 'locale']), $rules);
    }

    public function convertPassthrough()
    {
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

        $validator = $this->getConvertValidator();

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
                'amount' => -1,
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

        return response()->json($response);
    }
}






<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Log;
use Storage;

class TaxController extends Controller
{
    public function calculateRate(Request $request) 
    {
        $client = \TaxJar\Client::withApiKey(config('api_endpoints.TaxJar'));
        if(env("APP_ENV") == "development" ){
            $client->setApiConfig('api_url', \TaxJar\Client::SANDBOX_API_URL);
        }

        try {
            $rates = $client->ratesForLocation($request->zip, [
                'city' => $request->city,
                'country' => $request->country
            ]);

            return response()->json(['error' => 0, 'data' => $rates]);

        } catch (\TaxJar\Exception $e) {

            return response()->json(['error' => 1, 'data' => $e->getMessage()]);

        }
    }

    public function calculateTaxes(Request $request) 
    {
        $client = \TaxJar\Client::withApiKey(config('api_endpoints.TaxJar'));
        if(env("APP_ENV") == "development" ){
            $client->setApiConfig('api_url', \TaxJar\Client::SANDBOX_API_URL);
        }
        try {
            $order_taxes = $client->taxForOrder([
                'from_country' => 'US',
                'from_zip' => '92093',
                'from_state' => 'CA',
                'from_city' => 'La Jolla',
                'from_street' => '9500 Gilman Drive',
                'to_country' => $request->country,
                'to_zip' => $request->zip,
                'to_state' => $request->state,
                'to_city' => $request->city,
                'to_street' => $request->street,
                'amount' => $request->amount,
                'shipping' => $request->shipping,
                'line_items' => $request->products
            ]);

            return response()->json(['error' => 0, 'data' => $order_taxes]);

        } catch (\TaxJar\Exception $e) {

            return response()->json(['error' => 1, 'data' => $e->getMessage()]);

        }
    }

    public function createOrder(Request $request) 
    {
        $client = \TaxJar\Client::withApiKey(config('api_endpoints.TaxJar'));
        if(env("APP_ENV") == "development" ){
            $client->setApiConfig('api_url', \TaxJar\Client::SANDBOX_API_URL);
        }

        try {
            $order = $client->createOrder([
                'transaction_id' => $request->transaction_id,
                'transaction_date' => $request->date,
                'from_country' => 'US',
                'from_zip' => '92093',
                'from_state' => 'CA',
                'from_city' => 'La Jolla',
                'from_street' => '9500 Gilman Drive',
                'to_country' => $request->country,
                'to_zip' => $request->zip,
                'to_state' => $request->state,
                'to_city' => $request->city,
                'to_street' => $request->street,
                'amount' => $request->amount,
                'shipping' => $request->shipping,
                'sales_tax' => $request->sales_tax,
                'line_items' => $request->products
              ]);
              

            return response()->json(['error' => 0, 'data' => $order]);

        } catch (\TaxJar\Exception $e) {

            return response()->json(['error' => 1, 'data' => $e->getMessage()]);

        }
    }
}

<?php

namespace App\Services;


class TaxjarService
{


    public static function calculateRate($city, $country, $zip)
    {
        $client = \TaxJar\Client::withApiKey(config('api_endpoints.TaxJar'));
        if (env("APP_ENV") == "development") {
            $client->setApiConfig('api_url', \TaxJar\Client::SANDBOX_API_URL);
        }

        try {
            $rates = $client->ratesForLocation($zip, [
                'city' => $city,
                'country' => $country
            ]);

            return response()->json(['error' => 0, 'data' => $rates]);
        } catch (\TaxJar\Exception $e) {

            return response()->json(['error' => 1, 'data' => $e->getMessage()]);
        }
    }

    public static function calculateTaxes($country, $zip, $state, $city, $street, $amount, $shipping, $products)
    {
        $client = \TaxJar\Client::withApiKey(config('api_endpoints.TaxJar'));
        if (env("APP_ENV") == "development") {
            $client->setApiConfig('api_url', \TaxJar\Client::SANDBOX_API_URL);
        }
        try {
            $order_taxes = $client->taxForOrder([
                'from_country' => 'US',
                'from_zip' => '92093',
                'from_state' => 'CA',
                'from_city' => 'La Jolla',
                'from_street' => '9500 Gilman Drive',
                'to_country' => $country,
                'to_zip' => $zip,
                'to_state' => $state,
                'to_city' => $city,
                'to_street' => $street,
                'amount' => $amount,
                'shipping' => $shipping,
                'line_items' => $products
            ]);

            return $order_taxes;
        } catch (\TaxJar\Exception $e) {

            return $e->getMessage();
        }
    }

    public static function createOrder($country, $zip, $state, $city, $street, $amount, $shipping, $products, $transaction_id, $date)
    {
        $client = \TaxJar\Client::withApiKey(config('api_endpoints.TaxJar'));
        if (env("APP_ENV") == "development") {
            $client->setApiConfig('api_url', \TaxJar\Client::SANDBOX_API_URL);
        }

        try {
            $order = $client->createOrder([
                'transaction_id' => $transaction_id,
                'transaction_date' => $date,
                'from_country' => 'US',
                'from_zip' => '92093',
                'from_state' => 'CA',
                'from_city' => 'La Jolla',
                'from_street' => '9500 Gilman Drive',
                'to_country' => $country,
                'to_zip' => $zip,
                'to_state' => $state,
                'to_city' => $city,
                'to_street' => $street,
                'amount' => $amount,
                'shipping' => $shipping,
                'sales_tax' => $sales_tax,
                'line_items' => $products
            ]);


            return response()->json(['error' => 0, 'data' => $order]);
        } catch (\TaxJar\Exception $e) {

            return response()->json(['error' => 1, 'data' => $e->getMessage()]);
        }
    }
}

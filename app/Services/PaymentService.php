<?php

namespace App\Services;

use App\Helpers\NetworkMerchants;
use App\Helpers\tokenexAPI;
use App\Models\Address;
use App\Models\NMIGateway;
use App\Models\PaymentMethod;
use App\Models\PaymentMethodType;
use App\Models\ShoppingCart;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class PaymentService extends Model
{

    public static function NMIPaymentProcessUsingExistingCard($paymentMethod, $orderConversionId = null)
    {
        //dd($cart);
        $cart = ShoppingCart::getUserCart();
        $billingAddress = Address::getById($paymentMethod->bill_addr_id);
        $total = $cart->total - $cart->discount;

        $tokenEx = new tokenexAPI();
        $tokenRes = $tokenEx->detokenizeLog(config('api_endpoints.TOKENEXDetokenize'), $paymentMethod->token);
        $tokenRes = $tokenRes['response'];

        if (!$tokenRes->Success) {
            return response()->json(['error' => 1, 'msg' => "TokenEx Error : " . $tokenRes->Error]);
        }

        return NMIGateway::processPayment(
            $tokenRes->Value,
            $paymentMethod->firstname,
            $paymentMethod->lastname,
            $paymentMethod->expMonth,
            $paymentMethod->expYear,
            $paymentMethod->cvv,
            $total,
            $billingAddress->address1,
            $billingAddress->city,
            $billingAddress->stateprov,
            $billingAddress->postalcode,
            $billingAddress->countrycode,
            $paymentMethod->pay_method_type,
            $orderConversionId
        );
    }


    public static function NMIPaymentProcessWithNewCard($pm, $orderConversionId = null)
    {

        $cart = ShoppingCart::getUserCart();
        $total = $cart->total - $cart->discount;

        Log::info("cart " . json_encode($cart));
        Log::info("credit card " . json_encode($pm));

        $expiration = $pm['expiry_date'];
        $expirationParts = explode("/", $expiration);
        $expiryMonth = $expirationParts[0];
        $expiryYear = $expirationParts[1];

        return NMIGateway::processPayment(
            $pm['card_number'],
            $pm['first_name'],
            $pm['last_name'],
            $expiryMonth,
            $expiryYear,
            $pm['cvv'],
            $total,
            $pm['billingAddress']['address1'],
            $pm['billingAddress']['city'],
            $pm['billingAddress']['stateprov'],
            $pm['billingAddress']['postalcode'],
            $pm['billingAddress']['countrycode'],
            PaymentMethodType::TYPE_T1_PAYMENTS,
            $orderConversionId
        );
    }

    public static function ProcessWithNetworkMerchants($user, $pm, $orderConversionId = null, $newCard = false)
    {
        $gateway = new NetworkMerchants();

        $gateway->setLogin(PaymentMethodType::TYPE_T1_PAYMENTS);

        $gateway->setBilling(
            $user->firstname,
            $user->lastname,
            $user->business_name,
            $pm['billingAddress']['address1'],
            "",
            $pm['billingAddress']['city'],
            $pm['billingAddress']['stateprov'],
            $pm['billingAddress']['postalcode'],
            $pm['billingAddress']['countrycode'],
            $user->phonenumber,
            "",
            $user->email,
            ""
        );

        $gateway->setShipping(
            $user->firstname,
            $user->lastname,
            $user->business_name,
            $pm['billingAddress']['address1'],
            "",
            $pm['billingAddress']['city'],
            $pm['billingAddress']['stateprov'],
            $pm['billingAddress']['postalcode'],
            $pm['billingAddress']['countrycode'],
            $user->email
        );

        $cart = ShoppingCart::getUserCart();
        $total = $cart->total - $cart->discount;

        $gateway->setOrder($user->id . "#" . time(), "SHOPPING_CART", 0, 0, "", "");
        $gateway->doSale($total,  $pm['card_number'],  $newCard ? $pm['expiry_date'] : "", "", null);
        return isset($gateway->responses) ? $gateway->responses : [
            'response' => 0,
            'responsetext' => 'Empty response from gateway'
        ];
    }

    public static function processWithBilling($cart)
    {
    }
}

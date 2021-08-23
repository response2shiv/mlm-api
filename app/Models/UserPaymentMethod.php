<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class UserPaymentMethod extends Model
{

    const TYPE_EWALLET = 4;

    protected $fillable = [
        'user_id',
        'user_payment_address_id',
        'first_name',
        'last_name',
        'card_token',
        'is_primary',
        'is_deleted',
        'active',
        'pay_method_type',
        'expiration_month',
        'expiration_year'
    ];

    public static function getById($id, $userId = '')
    {

        // if (env('DISABLE_BILLING', false) === true) {
        //     return null;
        // }

        $paymentMethod = self::where('id', $id);
        if (!empty($userId)) {
            $paymentMethod = $paymentMethod->where('user_id', $userId);
        }

        $paymentMethod = $paymentMethod->first();

        return $paymentMethod;
    }

    public function paymentMethodMerchant()
    {
        return $this->belongsTo(UserPaymentMethodMerchant::class);
    }


    public function billingAddress()
    {
        return $this->belongsTo(UserPaymentAddress::class, 'user_payment_address_id');
    }

    public static function findOrCreateEwalletPaymentMethod($user)
    {
        $result =  self::where('pay_method_type', self::TYPE_EWALLET)->where('user_id', $user->id)->first();

        if ($result) {
            return $result;
        }

        $address = User::getUserPrimaryAddress($user);

        return self::Create([
            'user_id' => $user->id,
            'user_payment_address_id' => $address->id,
            'first_name' => $user->firstname,
            'last_name' => $user->lastname,
            'card_token' => null,
            'is_primay' => false,
            'active' => true,
            'pay_method_type' => self::TYPE_EWALLET
        ]);
    }

    public static function setPaymentMethodAsPrimary($paymentMethod, $user)
    {
        try {
            self::where('user_id', $user->id)
                ->update(['is_primary' => 0]);

            self::find($paymentMethod)
                ->update(['is_primary' => 1]);

            return true;
        } catch (\Exception $e) {

            return false;
        }
    }

    public static function markAsDeleted($paymentMethodId)
    {
        self::where('id', $paymentMethodId)
            ->update(['is_deleted' => 1]);
    }

    public static function convertAndSave($cardInfo, $addressInfo)
    {

        try {
            $payment_address = [
                'address1'     => $addressInfo['address1'],
                'address2'     => $addressInfo['address2'],
                'city'         => $addressInfo['city'],
                'state'        => $addressInfo['state'],
                'zipcode'      => $addressInfo['zip'],
                'country_code' => $addressInfo['country_code'],
                'user_id'      => Auth::user()->id,
            ];

            $newUserPaymentAddress  = UserPaymentAddress::create($payment_address);

            $user_payment_method = [
                'user_id'          => Auth::user()->id,
                'user_payment_address_id' => $newUserPaymentAddress->id,
                'first_name'       => $addressInfo['first_name'],
                'last_name'        => $addressInfo['last_name'],
                'card_token'       => $cardInfo['token'],
                'is_primary'       => 1,
                'active'           => 1,
                'is_deleted'       => false,
                'expiration_month' => str_pad($cardInfo['expiration_month'], 2, '0', STR_PAD_LEFT),
                'expiration_year'  => $cardInfo['expiration_year'],
                'pay_method_type'  => \App\Models\PaymentMethodType::TYPE_IPAYTOTAL
            ];

            self::create($user_payment_method);
        } catch (\Exception $e) {
            return $e;
        }
    }

    public static function convertAndUpdate($cardInfo, $addressInfo, $paymentMethodId)
    {
        $paymentMethod = self::where('id', $paymentMethodId)->first();

        try {
            $user_payment_method = [
                'user_id' => Auth::user()->id,
                'first_name'       => $cardInfo['first_name'],
                'last_name'        => $cardInfo['last_name'],
                'is_primary'       => 1,
                'active'           => 1,
                'is_deleted'       => false,
                'expiration_year' => substr($cardInfo['expiry_date'], -4),
                'expiration_month' => substr($cardInfo['expiry_date'], 0, 2),
                'pay_method_type'  => \App\Models\PaymentMethodType::TYPE_IPAYTOTAL
            ];

            $paymentMethod->update($user_payment_method);

            $payment_address = [
                'address1'     => $addressInfo['address1'],
                'city'         => $addressInfo['city'],
                'state'        => $addressInfo['stateprov'],
                'zipcode'      => $addressInfo['postalcode'],
                'country_code' => $addressInfo['countrycode']
            ];

            $paymentMethod->billingAddress()->update($payment_address);
        } catch (Exception $e) {
            return $e;
        }
    }
}

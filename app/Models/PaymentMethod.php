<?php

namespace App\Models;

use App\Helpers\Util;
use Illuminate\Database\Eloquent\Model;
use App\Helpers\tokenexAPI;
use Auth;

class PaymentMethod extends Model
{

    const MINIMUM_TOKEN_LENGTH = 8;

    const PAYMENT_METHOD_TYPE_CREDITCARD = 1;
    const PAYMENT_METHOD_TYPE_ADMIN = 2;
    const PAYMENT_METHOD_TYPE_EWALLET = 3;
    const PAYMENT_METHOD_TYPE_BITPAY = 4;   // Not In use
    const PAYMENT_METHOD_TYPE_SKRILL = 5;   // Not In use
    const PAYMENT_METHOD_TYPE_SECONDARY_CC = 6;
    const PAYMENT_METHOD_TYPE_CREDIT_CARD_TMT = 8;  // No Longer Used
    const PAYMENT_METHOD_TYPE_CREDIT_CARD_T1 = 9;
    const PAYMENT_METHOD_TYPE_CREDIT_CARD_T1_SECONDARY = 10;
    const TYPE_PAYARC = 11; // refunds only


    public static $creditCards = [
        self::PAYMENT_METHOD_TYPE_CREDITCARD,
        self::PAYMENT_METHOD_TYPE_SECONDARY_CC,
        self::PAYMENT_METHOD_TYPE_CREDIT_CARD_TMT,  // No Longer Used
        self::PAYMENT_METHOD_TYPE_CREDIT_CARD_T1,
        self::PAYMENT_METHOD_TYPE_CREDIT_CARD_T1_SECONDARY
    ];

    protected $table = "payment_methods";
    public $timestamps = false;
    protected $fillable = [
        'userID',
        'primary',
        'deleted',
        'token',
        'cvv',
        'expMonth',
        'expYear',
        'firstname',
        'lastname',
        'bill_addr_id',
        'pay_method_type',
        'is_deleted',
        'flag',
        'created_at',
        'updated_at'
    ];

    public static function getById($id, $userId = NULL)
    {

        if (env('DISABLE_BILLING', false) === true) {
            return null;
        }

        $paymentMethod = self::where('id', $id);
        if (!empty($userId)) {
            $paymentMethod = $paymentMethod->where('userID', $userId);
        }

        $paymentMethod = $paymentMethod->first();

        return $paymentMethod;
    }

    public static function checkCardAlreadyExists($userId, $tokenEx)
    {
        $rec = PaymentMethod::where('userID', $userId)
            ->where('token', $tokenEx)
            ->where(function ($query) {
                $query->where('is_deleted', '=', 0)
                    ->orWhereNull('is_deleted');
            })
            ->first();
        return $rec;
    }

    public static function addSecondaryCard(
        $userId,
        $isPrimary,
        $token,
        $billAddressId,
        $paymentMethodTypeId,
        $req,
        $isSubscription = 0
    ) {
        $rec = new PaymentMethod();
        $rec->userID = $userId;
        $rec->primary = $isPrimary;
        $rec->pay_method_type = $paymentMethodTypeId;
        //
        $expiry_date = $req->expiry_date;
        $temp = explode("/", $expiry_date);

        $rec->token = $token;
        $rec->cvv = $req->cvv;
        $rec->firstname = $req->first_name;
        $rec->lastname = $req->last_name;
        $rec->expMonth = (!empty($temp[0]) ? $temp[0] : '');
        $rec->expYear = (!empty($temp[1]) ? $temp[1] : '');
        $rec->bill_addr_id = $billAddressId;
        $rec->is_subscription = $isSubscription;
        $rec->save();
        return $rec->id;
    }

    public static function addNewRec($userId, $isPrimary, $token, $billAddressId, $paymentMethodTypeId, $req)
    {
        $rec = PaymentMethod::where('userID', $userId)
            ->where('primary', $isPrimary)
            ->where('pay_method_type', $paymentMethodTypeId)
            ->where(function ($query) {
                $query->where('is_deleted', '=', 0)
                    ->orWhereNull('is_deleted');
            })
            ->first();

        if (empty($rec)) {
            $rec = new PaymentMethod();
            $rec->userID = $userId;
            $rec->primary = $isPrimary;
            $rec->pay_method_type = $paymentMethodTypeId;
        }
        if ($req != null) {
            //
            $expiry_date = $req->expiry_date;
            $temp = explode("/", $expiry_date);

            if ($token != null) {
                $rec->token = $token;
            }
            if (isset($req->encrypt_cvv)) {
                if ($req->encrypt_cvv != $req->cvv) {
                    $rec->cvv = $req->cvv;
                }
            } else {
                $rec->cvv = $req->cvv;
            }

            $rec->firstname = $req->first_name;
            $rec->lastname = $req->last_name;
            $rec->expMonth = (!empty($temp[0]) ? $temp[0] : '');
            $rec->expYear = (!empty($temp[1]) ? $temp[1] : '');
        }
        $rec->bill_addr_id = $billAddressId;
        $rec->save();
        return $rec->id;
    }

    public static function getAllRec($userId, $paymentMethodTypeId = null)
    {
        $payments =  PaymentMethod::where('userID', $userId);

        if (env('DISABLE_BILLING', false) === true) {
            $payments->where('pay_method_type', '=', PaymentMethod::PAYMENT_METHOD_TYPE_EWALLET);
        } else if ($paymentMethodTypeId) {
            $payments->where('pay_method_type', $paymentMethodTypeId);
        }

        return $payments->where(function ($query) {
            $query->where('is_deleted', '=', 0)
                ->orWhereNull('is_deleted');
        })
            ->get();
    }

    public static function getUserPaymentRecords($userId)
    {
        if (env('DISABLE_BILLING', false) === true) {
            PaymentMethod::where('userID', $userId)->where('pay_method_type', '=', PaymentMethod::PAYMENT_METHOD_TYPE_EWALLET)->get();
        }

        return PaymentMethod::where('userID', $userId)->get();
    }

    public static function getRecByCountry($userId)
    {
        $userCountry = Address::getRec($userId, Address::TYPE_BILLING);
        if (empty($userCountry)) {
            $userCountry = Address::getRec($userId, Address::TYPE_REGISTRATION);
        }
        $paymentMethodType = PaymentMethodType::TYPE_CREDIT_CARD;
        if (Helper::checkTMTAllowPayment($userCountry->countrycode, $userId) > 0) {
            $paymentMethodType = PaymentMethodType::TYPE_T1_PAYMENTS;
        }
        return PaymentMethod::where('userID', $userId)
            ->where('pay_method_type', $paymentMethodType)
            ->where(function ($query) {
                $query->where('is_deleted', '=', 0)
                    ->orWhereNull('is_deleted');
            })
            ->get();
    }

    public static function getRecAllPaymentMethod($userId, $isPrimary)
    {
        return PaymentMethod::where('userID', $userId)
            ->where('primary', $isPrimary)
            ->where(function ($query) {
                $query->where('is_deleted', '=', 0)
                    ->orWhereNull('is_deleted');
            })
            ->first();
    }

    public static function getRec($userId, $isPrimary, $paymentMethodTypeId)
    {
        $paymentMethod = PaymentMethod::where('userID', $userId)
            ->where('primary', $isPrimary)
            ->where('pay_method_type', $paymentMethodTypeId)
            ->where(function ($query) {
                $query->where('is_deleted', '=', 0)
                    ->orWhereNull('is_deleted');
            })
            ->first();

        if (empty($paymentMethod)) {
            return PaymentMethod::where('userID', $userId)
                ->where('primary', $isPrimary)
                ->where(function ($query) {
                    $query->where('is_deleted', '=', 0)
                        ->orWhereNull('is_deleted');
                })
                ->first();
        } else {
            return $paymentMethod;
        }
    }

    public static function deleteSecondary($userId, $paymentMethodTypeId)
    {
        PaymentMethod::where('userID', $userId)
            ->where('primary', 0)
            ->where('pay_method_type', $paymentMethodTypeId)
            ->delete();
    }

    public static function generateTokenEx($cardNo)
    {
        $t = new tokenexAPI();
        $res = $t->tokenize('Tokenize', $cardNo);
        $res = json_decode($res);
        //
        if ($res->Success) {
            $error = 0;
            $token = $res->Token;
            $msg = null;
        } else {
            $error = 1;
            $token = null;
            $msg = $res->Error;
        }

        $result = array();
        $result['error'] = $error;
        $result['token'] = $token;
        $result['msg'] = $msg;

        return $result;
    }

    public static function addNewCustomPaymentMethod($req)
    {
        return self::create($req);
    }

    public static function getFormatedCardNo($token)
    {
        if (Util::isNullOrEmpty($token) || strlen($token) < self::MINIMUM_TOKEN_LENGTH) {
            return "";
        }
        $count = strlen($token);
        $temp1 = substr($token, 0, 0);
        $temp2 = substr($token, -4);
        $xCount = $count - 8;
        return $temp1 . str_repeat('x', $xCount) . $temp2;
    }

    public static function getFormatedCVV($cvv)
    {
        if (Util::isNullOrEmpty($cvv)) {
            return "";
        }
        $count = strlen($cvv);
        return str_repeat('x', $count);
    }

    public static function getUserPaymentMethods($userId, $isCard = '')
    {
        if (env('DISABLE_BILLING', false) === true) {
            return [];
        }

        $paymentMethods = PaymentMethod::where('userID', $userId)
            ->whereNotNull('pay_method_type')
            ->where(function ($query) {
                $query->where('is_deleted', '=', 0)
                    ->orWhereNull('is_deleted');
            });
        if (!empty($isCard)) {
            $paymentMethods = $paymentMethods->whereIn('pay_method_type', [
                PaymentMethodType::TYPE_CREDIT_CARD,
                PaymentMethodType::TYPE_SECONDARY_CC,
                PaymentMethodType::TYPE_T1_PAYMENTS,
                PaymentMethodType::TYPE_T1_PAYMENTS_SECONDARY_CC
            ]);
        }
        $paymentMethods = $paymentMethods->orderBy('id', 'asc')
            ->get();

        return $paymentMethods;
    }

    public static function getByUserPayMethodType($userId, $paymentMethodTypeId)
    {
        return PaymentMethod::where('userID', $userId)
            ->where('pay_method_type', $paymentMethodTypeId)
            ->where(function ($query) {
                $query->where('is_deleted', '=', 0)
                    ->orWhereNull('is_deleted');
            })
            ->first();
    }

    public static function checkSubscriptionCardAdded($userId)
    {
        return PaymentMethod::where('userID', $userId)
            ->where('primary', 0)
            ->where('is_subscription', 1)
            ->where(function ($query) {
                $query->where('is_deleted', '=', 0)
                    ->orWhereNull('is_deleted');
            })
            ->where('pay_method_type', PaymentMethodType::TYPE_SECONDARY_CC)
            ->count();
    }

    public static function markAsDeleted($paymentMethodId)
    {
        self::where('id', $paymentMethodId)
            ->update(['is_deleted' => 1]);
    }

    public static function getPaymentMethodIdOfPayMethodTypeAdmin($userId)
    {
        $paymentMethod = PaymentMethod::where('userID', $userId)
            ->where('pay_method_type', PaymentMethodType::TYPE_ADMIN)
            ->first();

        if (!$paymentMethod) {
            $paymentMethod = self::create([
                'userID' => $userId,
                'pay_method_type' => PaymentMethodType::TYPE_ADMIN
            ]);

            return $paymentMethod->id;
        }

        return $paymentMethod->id;
    }

    /**
     * @param $userId
     * @param $data
     * @return bool
     */
    public function updatePaymentRecords($userId, $data)
    {
        if (!$payment = PaymentMethod::query()->where('userId', $userId)->first()) {
            return false;
        }

        $hasChange = false;
        foreach (array_keys($data) as $index => $key) {
            if (!array_key_exists($key, $payment->toArray())) {
                return false;
            }

            if (strcasecmp($data[$key], $payment[$key]) !== 0) {
                $hasChange = true;
                $payment->$key = $data[$key];
            }
        }

        if (!$hasChange) {
            return false;
        }

        return $payment->save();
    }

    public static function setPaymentMethodAsPrimary($paymentMethod, $user)
    {
        try{
            PaymentMethod::where('userID', $user->id)
                ->update(['primary' => 0]);

            PaymentMethod::find($paymentMethod)
                ->update(['primary' => 1]);
            
            return true;
            
        }catch(\Exception $e){

            return false;

        }
    }

    public function billingAddress()
    {
        return $this->belongsTo(Address::class, 'bill_addr_id');
    }


    public static function addUserPaymentMethod($pm)
    {
        $user = Auth::user();

        $address = new UserPaymentAddress();
        $address->user_id = $user->id;
        $address->addrtype = Address::TYPE_BILLING;
        $address->is_primary = true;
        $address->address1 = $pm['billingAddress']['address1'];
        $address->city = $pm['billingAddress']['city'];
        $address->stateprov = $pm['billingAddress']['stateprov'];
        $address->stateprov_abbrev = null;
        $address->postal_code = $pm['billingAddress']['postalcode'];
        $address->apt = $pm['billingAddress']['apt'];
        $address->country_code = $pm['billingAddress']['countrycode'];
        $address->save();

        $newPayment = new UserPaymentMethod();
        $newPayment->user_id = $user->id;
        $newPayment->user_payment_address_id = $address->id;
        $newPayment->first_name = $pm['first_name'];
        $newPayment->last_name = $pm['last_name'];
        $newPayment->card_number = $pm['card_number'];
        $newPayment->expiry_date = $pm['expiry_date'];
        $newPayment->is_primary = false;
        $newPayment->is_active = true;
        $newPayment->payment_method_type = PaymentMethodType::TYPE_T1_PAYMENTS;
        $newPayment->save();

        return $newPayment;
    }
}

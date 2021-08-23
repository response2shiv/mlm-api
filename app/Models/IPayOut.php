<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Helper;
use Log;
class IPayOut extends Model
{
    protected $table = 'ipayout_user';
    protected $fillable = ['user_id', 'transaction_id'];
    const ITEM_DESCRIPTION = 'Fund deposit from https://ncrease.com/';
    
    public static function addUser($userId, $transactionRefId)
    {
        $hasRec = self::getIPayoutByUserId($userId);
        if (empty($hasRec)) {
            $rec = new self();
            $rec->user_id = $userId;
            $rec->transaction_id = $transactionRefId;
            $rec->save();
            return $rec->id;
        }
        return $hasRec;
    }

    public static function getIPayoutByUserId($userId)
    {
        return self::where('user_id', $userId)->first();
    }
}

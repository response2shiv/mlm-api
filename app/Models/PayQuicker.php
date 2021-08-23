<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Helper;
use Log;
class PayQuicker extends Model
{
    protected $table = 'payquicker';
    protected $fillable = ['user_id', 'userCompanyAssignedUniqueKey'];
    const ITEM_DESCRIPTION = 'Fund deposit from https://ncrease.com/';
    
    public static function addUser($userId, $transactionRefId)
    {
        $hasRec = self::getPayoutByUserId($userId);
        if (empty($hasRec)) {
            $rec = new self();
            $rec->user_id = $userId;
            $rec->userCompanyAssignedUniqueKey = $transactionRefId;
            $rec->save();
            return $rec->id;
        }
        return $hasRec;
    }

    public static function getPayoutByUserId($userId)
    {
        return self::firstOrCreate(['user_id' => $userId]);  
    }
}
<?php

namespace App\Models;

use App\Helpers\Util;
use Illuminate\Database\Eloquent\Model;
use DB;

class PasswordResetTokens extends Model {

    protected $table = "password_resets";
    public $timestamps = false;

    public static function createNew($email) {
        $token = md5($email . Util::getRandomString(5));
        //
        $rec = new PasswordResetTokens();
        $rec->email = $email;
        $rec->token = $token;
        $rec->createdAt = time();
        $rec->save();
        return $token;
    }

    public static function getEmailByToken($token) {
        $rec = DB::table('password_resets')
                ->select('email', 'createdAt')
                ->where('token', $token)
                ->first();
        if (empty($rec))
            return null;
        else {
            $ts = $rec->createdAt;
            $diffInSec = time() - $ts;
            $diffInMin = $diffInSec / 60;
            if ($diffInMin > 15) {
                return null;
            } else
                return $rec->email;
        }
    }

}

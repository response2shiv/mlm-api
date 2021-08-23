<?php

namespace App\Models;

use DB;
use Illuminate\Database\Eloquent\Model;

class ProductTermsAgreement extends Model
{
    protected $table = 'product_terms_agreement';


    public static function addAgreement($agreeFor, $userId)
    {
        DB::table('product_terms_agreement')->insert([
            'user_id' => $userId,
            'agree_sor' => ($agreeFor == 'sor' ? 1 : 0),
            'agree_idecide' => ($agreeFor == 'idecide' ? 1 : 0),
            'agreed_sor_at' => ($agreeFor == 'sor' ? date('Y-m-d h:i:s') : null),
            'agreed_idecide_at' => ($agreeFor == 'idecide' ? date('Y-m-d h:i:s') : null),
        ]);
    }

    public static function getByUserId($userId, $agreeFor)
    {
        if ($agreeFor == 'idecide') {
            return DB::table('product_terms_agreement')
                ->where('user_id', $userId)
                ->where('agree_idecide', 1)
                ->first();
        } elseif ($agreeFor == 'sor') {
            return DB::table('product_terms_agreement')
                ->where('user_id', $userId)
                ->where('agree_sor', 1)
                ->first();
        } elseif ($agreeFor == 'both') {
            return DB::table('product_terms_agreement')
                ->where('user_id', $userId)
                ->first();
        }
        return [];
    }

}

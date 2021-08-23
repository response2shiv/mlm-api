<?php

namespace App\Helpers;

# Copied from JOIN 

class TSA
{
    public function __construct()
    {

    }

    public static function generate($userId)
    {
        $num = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9];
        //$num = [3, 4];

        $tsaB = $userId;

        for ($i = 0; $i < (7 - strlen($userId)); $i++) {
            shuffle($num);
            $tsaB = $num[array_rand($num)] . $tsaB;
        }

        $tsa = 'TSA' . $tsaB;

        if (self::checkTSA($tsa)) {
            self::generate($userId);
        } else {
            return $tsa;
        }
    }

    public static function checkTSA($tsa)
    {
        $tsaExists = \App\Models\User::where('distid', $tsa)->count();
        if ($tsaExists) {
            return true;
        }
        return false;
    }
}
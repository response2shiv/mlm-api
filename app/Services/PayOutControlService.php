<?php

namespace App\Services;

use App\Models\Helper;
use Illuminate\Support\Facades\Auth;
use App\Contracts\PayOutControlContract;


class PayOutControlService{

    public function getPayoutType():string
    {
        
        $payout = Helper::getPayoutPaymentMethod(Auth::user()->id, Auth::user()->countrycode ?? 'US' );
        
        if($payout)
            return $payout->type;

        throw new \Exception('E-wallet not found form ' . Auth::user()->countrycode);
    }
    
    
    public function getPayout(): PayOutControlContract{
        $this->type = $this->getPayoutType();
        $payout = strtolower($this->type);
        
        if ($payout == 'ipayout') {
            return new iPayoutService;
        } elseif ($payout == 'payquicker') {
            return new PayQuickerService();
        } else {
            throw new \Exception('E-wallet {$payout} not found.');
        }
    }

    

}
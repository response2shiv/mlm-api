<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\User;
use App\Models\VGSTokens;
use App\Models\PaymentMethod;
use App\Models\Address;
use App\Models\UserPaymentMethod;
use App\Models\UserPaymentAddress;
use DB;
use Log;

class ImportVGSData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:vgs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import VGS cards from the csv';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        ini_set('memory_limit', '-1');
        //Import vgs tokens
        VGSTokens::chunk(1000, function ($vgs_totkens) {
            foreach ($vgs_totkens as $vgs) {
                //just print
                // Log::info("Token found as ".$vgs->vgs_token);
                // $pm = PaymentMethod::find($vgs->payment_methods_id);
                // $address = Address::find($pm->bill_addr_id);

                try{
                    $pm = DB::table('payment_methods')
                        ->where('id',$vgs->payment_methods_id)
                        ->whereNotNull('userID')
                        ->whereNotNull('cvv')
                        ->whereNotNull('expMonth')
                        ->whereNotNull('expYear')
                        ->whereNotNull('firstname')
                        ->whereNotNull('lastname')
                        ->first();

                    $address = DB::table('addresses')
                        ->where('id',$pm->bill_addr_id)
                        ->whereNotNull('address1')
                        ->whereNotNull('city')
                        ->whereNotNull('stateprov')
                        ->whereNotNull('postalcode')
                        ->whereNotNull('countrycode')
                        ->first();

                    $primary = true;
                    if($pm && $address){
                        $upa                = new UserPaymentAddress();
                        $upa->address1      = $address->address1;
                        $upa->address2      = $address->address2;
                        $upa->city          = $address->city;
                        $upa->state         = $address->stateprov;
                        $upa->zipcode       = $address->postalcode;
                        $upa->country_code  = $address->countrycode;
                        $upa->created_at    = $address->created_at;
                        $upa->updated_at    = $address->updated_at;
                        $upa->save();


                        $find = UserPaymentMethod::where('user_id',$pm->userID)->first();
                        if($find){
                            $primary = false;
                        }

                        $upm = new UserPaymentMethod();
                        $upm->id                        = $pm->id;
                        $upm->user_id                   = $pm->userID;
                        $upm->user_payment_address_id   = $upa->id;
                        $upm->first_name                = $pm->firstname;
                        $upm->last_name                 = $pm->lastname;
                        $upm->card_token                = $vgs->vgs_token;
                        $upm->is_primary                = $primary;
                        $upm->active                    = 1;
                        $upm->cvv                       = $pm->cvv;
                        $upm->expiration_month          = $pm->expMonth;
                        $upm->expiration_year           = $pm->expYear;
                        $upm->save();

                        //flag card on old table
                        $pmtd = PaymentMethod::find($vgs->payment_methods_id);
                        $pmtd->vgs_migrated = 1;
                        $pmtd->save();
                    }
                } catch (\Exception $ex) {
                    Log::error("Failed to process on this card -> ".$ex->getMessage());
                }
            }
        });
    }
}

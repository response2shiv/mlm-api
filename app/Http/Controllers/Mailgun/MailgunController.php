<?php

namespace App\Http\Controllers\Mailgun;

use App\Http\Controllers\Controller;
use Mailgun\Mailgun;
use Log;

class MailgunController extends Controller
{

    public static function unsubscribe($email)
    {
        $myDomain = env('MAILGUN_DOMAIN');
        $apiHost = 'https://api.mailgun.net/v3/'.$myDomain;
        $privateKey = env('MAILGUN_PRIVATE') ;
        try {
             # Instantiate the client.
            $mgClient  = Mailgun::create($privateKey, $apiHost);
            $domain    = $myDomain;
            $recipient = $email;
            $tag       = ['*'];

            # Issue the call to the client.
            $result = $mgClient->suppressions()->unsubscribes()->create($domain, $recipient, $tag);
            
            return response()->json('ok', 200);
            
        } catch (Exception $e) {
            return null;
        }
    }

}






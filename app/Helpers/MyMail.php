<?php

namespace App\Helpers;

use App\Models\MailTemplate;
use Auth;
use Illuminate\Support\Facades\Mail;


class MyMail
{

    public const VIBE_RIDER_TEMPLATE = '<customer_first_name> Congratulations!

Redeem this code, <boomerang_code> and become a VIBE Rider!

Go to https://iGoBuum.com now before your code expires!

Thanks for being our customer!

<dist_first_name> <dist_last_name>';

    public const BILL_GENIUS_TEMPLATE = 'Congratulations <customer_first_name> <customer_last_name>!

Redeem this code, <boomerang_code>, and Lower Your Bills with Bill Genius.

Go to https://igobuum.com and get started saving today before your code expires!

Once you have access its simple:

1 - Upload your bills in 3 minutes or less

2 - Bill Genius does all of the work for you

3- Don’t pay unless you save

Once you complete the quick sign-up process, you will be given a username and password.
Be sure to write it down or take a screenshot.

Simple, Easy, Savings.';

    public const VIBE_DRIVER_TEMPLATE = '<customer_first_name> Congratulations!

Redeem this code, <boomerang_code> and become a VIBE Driver!

Go to https://iGoBuum.com now before your code expires!

Thanks for being our customer!

<dist_first_name> <dist_last_name>';

    public static function sendResettingPassword($toEmail, $fullName, $resettingUrl)
    {
        $data = array();

        $template = MailTemplate::getRec(MailTemplate::TYPE_RESET_PASSWORD);
        if ($template->is_active == 1) {
            $subject = $template->subject;
            $content = $template->content;
            // replace place holders
            $content = str_replace("<full_name>", $fullName, $content);
            $content = str_replace("<resetting_url>", $resettingUrl, $content);
            $content = nl2br($content);
            //
            $data['content'] = $content;

            Mail::send('admin.mail_template.base_template', $data, function ($message) use ($toEmail, $fullName, $subject) {
                $message->to($toEmail, $fullName)->subject($subject);
            });
        }
    }

    public static function sendVibeRiderInvitation($toEmail, $customerFirstName, $customerLastName, $boomerangCode)
    {
        $fullName = $customerFirstName . " " . $customerLastName;
        $subject = 'VIBE Rider Invitation';

        $template = static::VIBE_RIDER_TEMPLATE;

        $template = str_replace("<dist_first_name>", Auth::user()->firstname, $template);
        $template = str_replace("<dist_last_name>", Auth::user()->lastname, $template);
        $template = str_replace("<customer_first_name>", $customerFirstName, $template);
        $template = str_replace("<customer_last_name>", $customerLastName, $template);
        $template = str_replace("<boomerang_code>", $boomerangCode, $template);
        $template = nl2br($template);

        $data['content'] = $template;

        Mail::send('admin.mail_template.base_template', $data, function ($message) use ($toEmail, $fullName, $subject) {
            $message->to($toEmail, $fullName)->subject($subject);
        });

        return true;
    }

    public static function sendVibeDriverInvitation($toEmail, $customerFirstName, $customerLastName, $boomerangCode)
    {
        $fullName = $customerFirstName . " " . $customerLastName;
        $subject = 'VIBE Driver Invitation';

        $template = static::VIBE_DRIVER_TEMPLATE;

        $template = str_replace("<dist_first_name>", Auth::user()->firstname, $template);
        $template = str_replace("<dist_last_name>", Auth::user()->lastname, $template);
        $template = str_replace("<customer_first_name>", $customerFirstName, $template);
        $template = str_replace("<customer_last_name>", $customerLastName, $template);
        $template = str_replace("<boomerang_code>", $boomerangCode, $template);
        $template = nl2br($template);

        $data['content'] = $template;

        Mail::send('admin.mail_template.base_template', $data, function ($message) use ($toEmail, $fullName, $subject) {
            $message->to($toEmail, $fullName)->subject($subject);
        });

        return true;
    }

    public static function sendBoomerangInvitation($toEmail, $customer_firstname, $customer_lastname, $code)
    {
        $data = array();
        $template = \App\Models\MailTemplate::getRec(\App\Models\MailTemplate::TYPE_BOOMERANG_INVITATION_MAIL);
        if ($template->is_active == 1) {
            $subject = $template->subject;
            $content = $template->content;
            $fullName = $customer_firstname . " " . $customer_lastname;
            //
            $content = str_replace("<dist_first_name>", Auth::user()->firstname, $content);
            $content = str_replace("<dist_last_name>", Auth::user()->lastname, $content);
            $content = str_replace("<customer_first_name>", $customer_firstname, $content);
            $content = str_replace("<customer_last_name>", $customer_lastname, $content);
            $content = str_replace("<boomerang_code>", $code, $content);
            $content = nl2br($content);
            $data['content'] = $content;

            Mail::send('admin.mail_template.base_template', $data, function ($message) use ($toEmail, $fullName, $subject) {
                $message->to($toEmail, $fullName)->subject($subject);
            });

            return true;
        }
        return false;
    }

    public static function sendBillGeniusInvitation($toEmail, $customer_firstname, $customer_lastname, $code)
    {
        $data = array();
        $template = \App\Models\MailTemplate::getRec(\App\Models\MailTemplate::TYPE_BILLGENIUS_INVITATION_MAIL);
        if ($template->is_active == 1) {
            $subject = $template->subject;
            $content = $template->content;
            $fullName = $customer_firstname . " " . $customer_lastname;
            //
            $content = str_replace("<customer_first_name>", $customer_firstname, $content);
            $content = str_replace("<customer_last_name>", $customer_lastname, $content);
            $content = str_replace("<boomerang_code>", $code, $content);
            $content = nl2br($content);
            $data['content'] = $content;

            Mail::send('admin.mail_template.base_template', $data, function ($message) use ($toEmail, $fullName, $subject) {
                $message->to($toEmail, $fullName)->subject($subject);
            });

            return true;
        }
        return false;
    }

    // to customers
    public static function sendCustomerNewAccount($firstName, $lastName, $email, $password)
    {
        $data = array();

        $template = \App\Models\MailTemplate::getRec(\App\Models\MailTemplate::TYPE_CUSTOMER_NEW_ACCOUNT);
        if ($template->is_active == 1) {
            $subject = $template->subject;
            $content = $template->content;
            // replace place holders
            $content = str_replace("<customer_first_name>", $firstName, $content);
            $content = str_replace("<customer_last_name>", $lastName, $content);
            $content = str_replace("<customer_email>", $email, $content);
            $content = str_replace("<sor_password>", $password, $content);
            $content = nl2br($content);
            //
            $data['content'] = $content;
            //
            $toEmail = $email;
            $name = $firstName . " " . $lastName;
            //
            Mail::send('admin.mail_template.base_template', $data, function ($message) use ($toEmail, $name, $subject) {
                $message->to($toEmail, $name)->subject($subject);
            });
        }
    }

    // to distributors
    public static function sendSubscriptionRecurringSuccess($firstName, $lastName, $distId, $email)
    {

        if (env('APP_ENV') === 'prod' || env('APP_ENV') === 'production') {

            $data = array();

            $template = MailTemplate::getRec(MailTemplate::TYPE_SUBSCRIPTION_RECURRING_PAYMENT_SUCCESS);
            if ($template->is_active == 1) {
                $subject = $template->subject;
                $content = $template->content;
                // replace place holders
                $content = str_replace("<dist_first_name>", $firstName, $content);
                $content = str_replace("<dist_last_name>", $lastName, $content);
                $content = str_replace("<distid>", $distId, $content);
                $content = nl2br($content);
                //
                $data['content'] = $content;
                //
                $toEmail = $email;
                $name = $firstName . " " . $lastName;
                //
                Mail::send('admin.mail_template.base_template', $data, function ($message) use ($toEmail, $name, $subject) {
                    $message->to($toEmail, $name)->subject($subject);
                });
            }
        }
    }

    // to distributors
    public static function sendSubscriptionRecurringFailed($firstName, $lastName, $distId, $email, $errorMessage)
    {

        if (env('APP_ENV') === 'prod' || env('APP_ENV') === 'production') {

            $data = array();

            $template = MailTemplate::getRec(MailTemplate::TYPE_SUBSCRIPTION_RECURRING_PAYMENT_FAILED);
            if ($template->is_active == 1) {
                $subject = $template->subject;
                $content = $template->content;
                // replace place holders
                $content = str_replace("<dist_first_name>", $firstName, $content);
                $content = str_replace("<dist_last_name>", $lastName, $content);
                $content = str_replace("<distid>", $distId, $content);
                $content = str_replace("<error_message>", $errorMessage, $content);
                $content = nl2br($content);
                //
                $data['content'] = $content;
                //
                $toEmail = $email;
                $name = $firstName . " " . $lastName;
                //
                Mail::send('admin.mail_template.base_template', $data, function ($message) use ($toEmail, $name, $subject) {
                    $message->to($toEmail, $name)->subject($subject);
                });
            }
        }
    }

    public static function sendAddedToBinaryTree($firstName, $lastName, $distId, $email)
    {
        $data = array();

        $template = MailTemplate::getRec(MailTemplate::TYPE_ADDED_TO_BINARY_TREE_MAIL);
        if ($template->is_active == 1) {
            $subject = $template->subject;
            $content = $template->content;
            // replace place holders
            $content = str_replace("<dist_first_name>", $firstName, $content);
            $content = str_replace("<dist_last_name>", $lastName, $content);
            $content = str_replace("<dist_id>", $distId, $content);
            $content = nl2br($content);
            //
            $data['content'] = $content;
            //
            $toEmail = $email;
            $name = $firstName . " " . $lastName;
            //
            Mail::send('admin.mail_template.base_template', $data, function ($message) use ($toEmail, $name, $subject) {
                $message->to($toEmail, $name)->subject($subject);
            });
        }
    }

    public static function sendBulkMail($toEmail, $subject, $content)
    {
        $data = array();
        $content = nl2br($content);
        $data['content'] = $content;
        //
        Mail::send('admin.mail_template.base_template', $data, function ($message) use ($toEmail, $subject) {
            $message->to($toEmail)->subject($subject);
        });
    }


    /**
     * @param $user
     * @return int
     */
    public static function resendWelcomeEmail($user)
    {
        $data = [];
        $toEmail = $user->email;
        $distId = $user->distid;
        $template = MailTemplate::getRec(MailTemplate::TYPE_RESEND_WELCOME_EMAIL);
        $firstName = $user->firstname;
        $lastName = $user->lastname;
        $userName = $user->username;

        if (!$template->is_active) {
            return 1;
        }

        $subject = $template->subject;
        $content = $template->content;
        // replace place holders
        $content = str_replace("<customer_first_name>", $firstName, $content);
        $content = str_replace("<customer_username>", $userName, $content);
        $content = str_replace("<customer_distid>", $distId, $content);
        $content = nl2br($content);
        //
        $data['content'] = $content;
        //
        $name = $firstName . " " . $lastName;
        //
        Mail::send('admin.mail_template.base_template', $data, function ($message) use ($toEmail, $name, $subject) {
            $message->to($toEmail, $name)->subject($subject);
        });

        return 0;
    }


    public static function sendBinaryPlacementMail($toEmail, $link)
    {
        $data = array();
        $template = MailTemplate::getRec(MailTemplate::TYPE_BINARY_PLACEMENT_LINK);
        if ($template->is_active == 1) {
            $subject = $template->subject;
            $content = $template->content;
            $content = str_replace("<placement_link>", $link, $content);
            $content = nl2br($content);
            $data['content'] = $content;

            Mail::send('admin.mail_template.base_template', $data, function ($message) use ($toEmail, $subject) {
                $message->to($toEmail)->subject($subject);
            });

            return true;
        }
        return false;
    }
}

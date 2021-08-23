<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DB;

class MailTemplate extends Model {

    protected $table = "mail_templates";
    public $timestamps = false;

    const TYPE_RESET_PASSWORD = "Reseting Password";
    const TYPE_EMAIL_VERIFICATION = "Email Verification";
    const TYPE_NEW_ORDER = "New Order";
    const TYPE_CANCELLED_ORDER = "Cancelled Order";
    const TYPE_FAILED_ORDER = "Failed Order";
    const TYPE_ORDER_ON_HOLD = "Order-On-Hold";
    const TYPE_PROCESSING_ORDER = "Processing Order";
    const TYPE_COMPLETE_ORDER = "Complete Order";
    const TYPE_REFUND_ORDER = "Refund Order";
    const TYPE_CUSTOMER_INVOICE = "Customer Invoice";
    const TYPE_CUSTOMER_NEW_ACCOUNT = "Customer New Account";
    const TYPE_NEW_SUBSCRIPTION_ORDER = "New Subscription Order";
    const TYPE_SUBSCRIPTION_CHANGE = "Subscription Change";
    const TYPE_PROCESSING_SUBSCRIPTION = "Processing Subscription";
    const TYPE_COMPLETED_SUBSCRIPTION_ORDER = "Completed Subscription Order";
    const TYPE_CUSTOMER_SUBSCRITION_INVOICE = "Customer Subscription Invoice";
    const TYPE_UNSUBSCRIBED_CONFIRMATION = "Unsubscribed Confirmation";
    const TYPE_EXPIRED_SUBSCRIPTION = "Expired Subscription";
    const TYPE_SUSPENDED_SUBSCRIPTION = "Suspended Subscription";
    const TYPE_CUSTOMER_FIRST_ATTEMPT_FAIL = "Customer 1st Attempt Fail";
    const TYPE_CUSTOMER_SECOND_ATTEMPT_FAIL = "Customer 2nd Attempt Fail";
    const TYPE_NEW_DISTIBUTOR_ENROLLMENT = "New Distributor Enrollment";
    const TYPE_NEW_INTERN_ENROLLMENT_WELCOME = "New Intern Enrollment Welcome";
    const TYPE_DISTRIBUTOR_PROFILE_CHANGED = "Distributor Profile Changed";
    const TYPE_INTERN_PAID = "Intern Paid";
    const TYPE_CREDIT_MEMO = "Credit Memo";
    const TYPE_BINARY_PLACEMENT_LINK = "Binary Placement Invitation Link - EMAIL";
    const TYPE_SUBSCRIPTION_RECURRING_PAYMENT_FAILED = "Subscription recurring payment failed";
    const TYPE_SUBSCRIPTION_RECURRING_PAYMENT_SUCCESS = "Subscription recurring payment success";
    const TYPE_ADDED_TO_BINARY_TREE_MAIL = "Added to binary tree - EMAIL";
    const TYPE_ADDED_TO_BINARY_TREE_SMS = "Added to binary tree - SMS";
    const TYPE_RESEND_WELCOME_EMAIL = "Customer Resend Welcome Email";

    public static function install() {
        $recs = self::getDefaultRecs();
        foreach ($recs as $k => $v) {
            $rec = MailTemplate::where('type', $k)->first();
            if (empty($rec)) {
                $newRec = new MailTemplate();
                $newRec->type = $k;
                $newRec->subject = $v['subject'];
                $newRec->place_holders = $v['place_holders'];
                $newRec->is_active = $v['is_active'];
                $newRec->remarks = $v['remarks'];
                $newRec->save();
            } else {
                $rec->place_holders = $v['place_holders'];
                $rec->remarks = $v['remarks'];
                $rec->save();
            }
        }
    }

    public static function getRec($type) {
        return DB::table('mail_templates')
                        ->select('subject', 'content', 'is_active')
                        ->where('type', $type)
                        ->first();
    }

    private static function getDefaultRecs() {
        $res = array();
        $res[self::TYPE_RESET_PASSWORD] = array(
            "subject" => "Xstream Travel Resetting password",
            "place_holders" => "<full_name>,<resetting_url>",
            "remarks" => "To All users",
            "is_active" => 1
        );
        $res[self::TYPE_EMAIL_VERIFICATION] = array(
            "subject" => "Xstream Travel Email verification",
            "place_holders" => "<firstname>,<lastname>,<verify_url>",
            "remarks" => "To intern and customer",
            "is_active" => 1
        );
        $res[self::TYPE_NEW_ORDER] = array(
            "subject" => "New Order",
            "place_holders" => "",
            "remarks" => "To admin users",
            "is_active" => 0
        );
        $res[self::TYPE_CANCELLED_ORDER] = array(
            "subject" => "Cancelled Order",
            "place_holders" => "",
            "remarks" => "To admin users",
            "is_active" => 0
        );
        $res[self::TYPE_FAILED_ORDER] = array(
            "subject" => "Failed Order",
            "place_holders" => "",
            "remarks" => "To admin users",
            "is_active" => 0
        );
        $res[self::TYPE_ORDER_ON_HOLD] = array(
            "subject" => "Order on hold",
            "place_holders" => "",
            "remarks" => "To customer",
            "is_active" => 0
        );
        $res[self::TYPE_PROCESSING_ORDER] = array(
            "subject" => "Processing Order",
            "place_holders" => "",
            "remarks" => "To customer",
            "is_active" => 0
        );
        $res[self::TYPE_COMPLETE_ORDER] = array(
            "subject" => "Complete Order",
            "place_holders" => "",
            "remarks" => "To customer",
            "is_active" => 1
        );
        $res[self::TYPE_CUSTOMER_INVOICE] = array(
            "subject" => "Customer Invoice",
            "place_holders" => "",
            "remarks" => "To customer",
            "is_active" => 1
        );
        $res[self::TYPE_CUSTOMER_NEW_ACCOUNT] = array(
            "subject" => "Customer New Account",
            "place_holders" => "<customer_first_name>,<customer_last_name>,<customer_email>,<sor_password>",
            "remarks" => "To customer",
            "is_active" => 1
        );
        $res[self::TYPE_NEW_SUBSCRIPTION_ORDER] = array(
            "subject" => "New subscription order",
            "place_holders" => "",
            "remarks" => "To admin users",
            "is_active" => 0
        );
        $res[self::TYPE_SUBSCRIPTION_CHANGE] = array(
            "subject" => "Subscription change",
            "place_holders" => "",
            "remarks" => "To customer",
            "is_active" => 1
        );
        $res[self::TYPE_PROCESSING_SUBSCRIPTION] = array(
            "subject" => "Processing Subscription",
            "place_holders" => "",
            "remarks" => "To customer",
            "is_active" => 1
        );
        $res[self::TYPE_COMPLETED_SUBSCRIPTION_ORDER] = array(
            "subject" => "Completed Subscription Order",
            "place_holders" => "",
            "remarks" => "To customer",
            "is_active" => 1
        );
        $res[self::TYPE_CUSTOMER_SUBSCRITION_INVOICE] = array(
            "subject" => "Customer Subscription Invoice",
            "place_holders" => "",
            "remarks" => "To customer",
            "is_active" => 1
        );
        $res[self::TYPE_UNSUBSCRIBED_CONFIRMATION] = array(
            "subject" => "Unsubscribed Confirmation",
            "place_holders" => "",
            "remarks" => "To customer, customer service",
            "is_active" => 1
        );
        $res[self::TYPE_EXPIRED_SUBSCRIPTION] = array(
            "subject" => "Expired Subscription",
            "place_holders" => "",
            "remarks" => "To customer",
            "is_active" => 0
        );
        $res[self::TYPE_SUSPENDED_SUBSCRIPTION] = array(
            "subject" => "Suspended Subscription",
            "place_holders" => "",
            "remarks" => "To customer, customer serivce",
            "is_active" => 1
        );
        $res[self::TYPE_CUSTOMER_FIRST_ATTEMPT_FAIL] = array(
            "subject" => "Customer 1st attempt fail",
            "place_holders" => "",
            "remarks" => "To customer",
            "is_active" => 1
        );
        $res[self::TYPE_CUSTOMER_SECOND_ATTEMPT_FAIL] = array(
            "subject" => "Customer 2nd attempt fail",
            "place_holders" => "",
            "remarks" => "To customer, customer service",
            "is_active" => 1
        );
        $res[self::TYPE_NEW_DISTIBUTOR_ENROLLMENT] = array(
            "subject" => "New Distributor Enrollment",
            "place_holders" => "",
            "remarks" => "To sponsor",
            "is_active" => 1
        );
        $res[self::TYPE_NEW_INTERN_ENROLLMENT_WELCOME] = array(
            "subject" => "New Intern Enrollment",
            "place_holders" => "",
            "remarks" => "To distributor",
            "is_active" => 1
        );
        $res[self::TYPE_DISTRIBUTOR_PROFILE_CHANGED] = array(
            "subject" => "Distributor Profile Changed",
            "place_holders" => "",
            "remarks" => "To distributor",
            "is_active" => 1
        );
        $res[self::TYPE_INTERN_PAID] = array(
            "subject" => "Intern Paid",
            "place_holders" => "",
            "remarks" => "To distributor",
            "is_active" => 0
        );
        $res[self::TYPE_CREDIT_MEMO] = array(
            "subject" => "Credit Memo",
            "place_holders" => "",
            "remarks" => "To customer",
            "is_active" => 0
        );
        $res[self::TYPE_SUBSCRIPTION_RECURRING_PAYMENT_SUCCESS] = array(
            "subject" => "Subscription recurring payment success",
            "place_holders" => "<dist_first_name>,<dist_last_name>,<distid>",
            "remarks" => "To distributor",
            "is_active" => 0
        );
        $res[self::TYPE_SUBSCRIPTION_RECURRING_PAYMENT_FAILED] = array(
            "subject" => "Subscription recurring payment failed",
            "place_holders" => "<dist_first_name>,<dist_last_name>,<distid>,<error_message>",
            "remarks" => "To distributor",
            "is_active" => 0
        );
        $res[self::TYPE_BINARY_PLACEMENT_LINK] = array(
            "subject" => "Enrollment Link",
            "place_holders" => "<placement_link>",
            "remarks" => "To distributor",
            "is_active" => 0
        );
        $res[self::TYPE_ADDED_TO_BINARY_TREE_SMS] = array(
            "subject" => "Added to binary tree - SMS",
            "place_holders" => "<dist_first_name>,<dist_last_name>,<dist_id>",
            "remarks" => "To distributor(sms)",
            "is_active" => 0
        );
        $res[self::TYPE_ADDED_TO_BINARY_TREE_MAIL] = array(
            "subject" => "Added to binary tree",
            "place_holders" => "<dist_first_name>,<dist_last_name>,<dist_id>",
            "remarks" => "To distributor",
            "is_active" => 0
        );
        return $res;
    }

}

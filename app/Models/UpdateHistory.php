<?php

namespace App\Models;

use Auth;
use DB;
use Illuminate\Database\Eloquent\Model;
use App\Helpers\Util;

class UpdateHistory extends Model {

    protected $table = "update_history";
    public $timestamps = false;

    //
    const TYPE_ORDER = "ORDER";
    const TYPE_ORDER_ITEM = "ORDER_ITEM";
    const TYPE_PRODUCT = "PRODUCT";
    const TYPE_CUSTOMER = "CUSTOMER";
    const TYPE_USER = "USER";
    const TYPE_ADDRESS = "ADDRESS";
    const TYPE_ADJUSTMENT = "ADJUSTMENT";
    //
    const MODE_ADD = "ADD";
    const MODE_UPDATE = "UPDATE";
    const MODE_REFUND = "REFUND";

    public static function orderUpdate($orderId, $rec, $req) {
        $before = array();
        $after = array();
        //
        if ($rec->ordertotal != $req->ordertotal) {
            $before['Order total'] = $rec->ordertotal;
            $after['Order total'] = $req->ordertotal;
        }
        if ($rec->ordersubtotal != $req->ordersubtotal) {
            $before['Order subtotal'] = $rec->ordersubtotal;
            $after['Order subtotal'] = $req->ordersubtotal;
        }
        if ($rec->created_date != $req->created_date) {
            $before['Created Date'] = $rec->created_date;
            $after['Created Date'] = $req->created_date;
        }
        if ($rec->orderbv != $req->orderbv) {
            $before['Order BV'] = $rec->orderbv;
            $after['Order BV'] = $req->orderbv;
        }
        if ($rec->orderqv != $req->orderqv) {
            $before['Order QV'] = $rec->orderqv;
            $after['Order QV'] = $req->orderqv;
        }
        if ($rec->ordercv != $req->ordercv) {
            $before['Order CV'] = $rec->ordercv;
            $after['Order CV'] = $req->ordercv;
        }
        if ($rec->orderqc != $req->orderqc) {
            $before['Order QC'] = $rec->orderqc;
            $after['Order QC'] = $req->orderqc;
        }
        if ($rec->orderac != $req->orderac) {
            $before['Order AC'] = $rec->orderac;
            $after['Order AC'] = $req->orderac;
        }

        self::addNew(self::TYPE_ORDER, $orderId, $before, $after);
    }

    public static function orderItemAdd($itemId, $rec) {
        $before = array();
        $after = array();
        //
        $prod_after = Product::getById($rec->productid);
        $after['Product'] = $prod_after->productname;
        $after['Price'] = $rec->itemprice;
        $after['BV'] = $rec->bv;
        $after['QV'] = $rec->qv;
        $after['CV'] = $rec->cv;
        $after['QC'] = $rec->qc;
        $after['AC'] = $rec->ac;
        //
        self::addNew(self::TYPE_ORDER_ITEM, $itemId, $before, $after, self::MODE_ADD);
    }

    public static function orderItemUpdate($itemId, $rec, $req) {
        $before = array();
        $after = array();
        //
        if ($rec->productid != $req->productid) {
            $prod_before = Product::getById($rec->productid);
            $prod_after = Product::getById($req->productid);
            //
            $before['Product'] = $prod_before->productname;
            $after['Product'] = $prod_after->productname;
        }
        if ($rec->itemprice != $req->itemprice) {
            $before['Price'] = $rec->itemprice;
            $after['Price'] = $req->itemprice;
        }
        if ($rec->bv != $req->bv) {
            $before['BV'] = $rec->bv;
            $after['BV'] = $req->bv;
        }
        if ($rec->qv != $req->qv) {
            $before['QV'] = $rec->qv;
            $after['QV'] = $req->qv;
        }
        if ($rec->cv != $req->cv) {
            $before['CV'] = $rec->cv;
            $after['CV'] = $req->cv;
        }
        if ($rec->qc != $req->qc) {
            $before['QC'] = $rec->qc;
            $after['QC'] = $req->qc;
        }
        if ($rec->ac != $req->ac) {
            $before['AC'] = $rec->ac;
            $after['AC'] = $req->ac;
        }

        self::addNew(self::TYPE_ORDER_ITEM, $itemId, $before, $after);
    }

    //
    public static function orderAdd($orderId) {
        self::addNew(self::TYPE_ORDER, $orderId, null, null, self::MODE_ADD);
    }

    public static function productAdd($productId) {
        $before = array();
        $after = array();
        //
        $prod_after = Product::getById($productId);
        $after['Product'] = $prod_after->productname;
        $after['Type'] = $prod_after->producttype;
        $after['Description'] = $prod_after->productdesc;
        $after['Price'] = $prod_after->price;
        $after['Item Code'] = $prod_after->itemcode;
        $after['BV'] = $prod_after->bv;
        $after['CV'] = $prod_after->cv;
        $after['QV'] = $prod_after->qv;
        $after['QC'] = $prod_after->qc;
        $after['AC'] = $prod_after->ac;
        $after['SKU'] = $prod_after->sku;
        $after['Auto Ship'] = $prod_after->isautoship;
        self::addNew(self::TYPE_PRODUCT, $productId, $before, $after, self::MODE_ADD);
    }

    public static function productUpdate($productId, $rec, $req) {
        $before = array();
        $after = array();
        //
        if ($rec->productname != $req->productname) {
            $before['Product Name'] = $rec->productname;
            $after['Product Name'] = $req->productname;
        }
        if ($rec->is_enabled != $req->is_enabled) {
            $before['Product Enabled'] = $rec->is_enabled;
            $after['Product Enabled'] = $req->is_enabled;
        }
        if ($rec->productdesc != $req->productdesc) {
            $before['Product Description'] = $rec->productdesc;
            $after['Product Description'] = $req->productdesc;
        }
        if ($rec->producttype != $req->producttype) {
            $before['Product Type'] = $rec->producttype;
            $after['Product Type'] = $req->producttype;
        }
        if ($rec->price != $req->price) {
            $before['Price'] = $rec->price;
            $after['Price'] = $req->price;
        }
        if ($rec->itemcode != $req->itemcode) {
            $before['Item Code'] = $rec->itemcode;
            $after['Item Code'] = $req->itemcode;
        }
        if ($rec->bv != $req->bv) {
            $before['BV'] = $rec->bv;
            $after['BV'] = $req->bv;
        }
        if ($rec->cv != $req->cv) {
            $before['CV'] = $rec->cv;
            $after['CV'] = $req->cv;
        }
        if ($rec->qv != $req->qv) {
            $before['QV'] = $rec->qv;
            $after['QV'] = $req->qv;
        }
        if ($rec->qc != $req->qc) {
            $before['QC'] = $rec->qc;
            $after['QC'] = $req->qc;
        }
        if ($rec->ac != $req->ac) {
            $before['AC'] = $rec->ac;
            $after['AC'] = $req->ac;
        }
        
        self::addNew(self::TYPE_PRODUCT, $productId, $before, $after);
    }

    public static function customerUpdate($cutomerId, $rec, $req) {
        $before = array();
        $after = array();
        //
        if ($rec->email != $req->email) {
            $before['Email'] = $rec->email;
            $after['Email'] = $req->email;
        }
        if ($rec->name != $req->name) {
            $before['Name'] = $rec->name;
            $after['Name'] = $req->name;
        }
        if ($rec->mobile != $req->mobile) {
            $before['Mobile'] = $rec->mobile;
            $after['Mobile'] = $req->mobile;
        }

        self::addNew(self::TYPE_CUSTOMER, $cutomerId, $before, $after);
    }

    public static function userAdd($userId) {
        $before = array();
        $after = array();

        $rec_after = User::getById($userId);
        $after['First Name'] = $rec_after->firstname;
        $after['Last Name'] = $rec_after->lastname;
        $after['Dist ID'] = $rec_after->distid;
        $after['Account Status'] = $rec_after->account_status;
        $after['User Type'] = $rec_after->usertype;
        $after['Email'] = $rec_after->email;
        $after['Email Verified'] = $rec_after->email_verified;
        $after['Phone Number'] = $rec_after->phonenumber;
        $after['Mobile Number'] = $rec_after->mobilenumber;
        $after['Business Name'] = $rec_after->business_name;
        $after['Username'] = $rec_after->username;
        $after['Entered By'] = $rec_after->entered_by;
        $after['Sponser ID'] = $rec_after->sponsorid;
        $after['Remarks'] = $rec_after->remarks;
        $after['Default Password'] = $rec_after->default_password;
        self::addNew(self::TYPE_USER, $userId, $before, $after, self::MODE_ADD);
    }

    public static function userUpdate($userId, $rec, $req) {
        $before = array();
        $after = array();
        //
        if ($rec->firstname != $req->firstname) {
            $before['First Name'] = $rec->firstname;
            $after['First Name'] = $req->firstname;
        }
        if ($rec->lastname != $req->lastname) {
            $before['Last Name'] = $rec->lastname;
            $after['Last Name'] = $req->lastname;
        }
        if ($rec->business_name != $req->business_name) {
            $before['Business Name'] = $rec->business_name;
            $after['Business Name'] = $req->business_name;
        }
        if ($rec->phonenumber != $req->phonenumber) {
            $before['Phone Number'] = $rec->phonenumber;
            $after['Phone Number'] = $req->phonenumber;
        }
        if ($rec->mobilenumber != $req->mobilenumber) {
            $before['Mobile Number'] = $rec->mobilenumber;
            $after['Mobile Number'] = $req->mobilenumber;
        }
        if ($rec->username != $req->username) {
            $before['Username'] = $rec->username;
            $after['Username'] = $req->username;
        }
        if ($rec->account_status != $req->account_status) {
            $before['Account Status'] = $rec->account_status;
            $after['Account Status'] = $req->account_status;
        }
        if ($rec->sponsorid != $req->sponsorid) {
            $before['Sponsor ID'] = $rec->sponsorid;
            $after['Sponsor ID'] = $req->sponsorid;
        }
        if ($rec->email != $req->email) {
            $before['Email'] = $rec->email;
            $after['Email'] = $req->email;
        }
        if ($rec->email_verified != $req->email_verified) {
            $before['Email Verified'] = $rec->email_verified;
            $after['Email Verified'] = $req->email_verified;
        }
        if ($rec->default_password != $req->default_password) {
            $before['Default Password'] = $rec->default_password;
            $after['Default Password'] = $req->default_password;
        }
        if ($rec->remarks != $req->remarks) {
            $before['Remarks'] = $rec->remarks;
            $after['Remarks'] = $req->remarks;
        }
        self::addNew(self::TYPE_USER, $userId, $before, $after);
    }


    public static function addressAdd($userId, $addressId, $isPrimary)
    {
        $before = array();
        $after = array();

        //$address_after = Address::getRec($userId, $isPrimary);
        $address_after = Address::getById($addressId);

        $after['Address Type'] = $address_after->addrtype;
        $after['Primary'] = $address_after->primary;
        $after['Address 1'] = $address_after->address1;
        $after['Address 2'] = $address_after->address2;
        $after['City'] = $address_after->city;
        $after['State/Province'] = $address_after->stateprov;
        $after['Postal Code'] = $address_after->postalcode;
        $after['Country Code'] = $address_after->countrycode;
        $after['Apt/Suite'] = $address_after->apt;
        self::addNew(self::TYPE_ADDRESS, $addressId, $before, $after, self::MODE_ADD);
    }

    public static function adjustmentAdd($adjustmentId, $distid, $amount, $note) {
        $before = array();
        $after = array();

        $after['Dist ID'] = $distid;
        $after['Adjustment Amount'] = $amount;
        $after['Note'] = $note;
        self::addNew(self::TYPE_ADJUSTMENT, $adjustmentId, $before, $after, self::MODE_ADD);
    }

    public static function addressUpdate($addressId, $rec, $req) {
        $before = array();
        $after = array();
        //
        if ($rec->address1 != $req->address1) {
            $before['Address 1'] = $rec->address1;
            $after['Address 1'] = $req->address1;
        }
        if ($rec->address2 != $req->address2) {
            $before['Address 2'] = $rec->address2;
            $after['Address 2'] = $req->address2;
        }
        if ($rec->city != $req->city) {
            $before['City'] = $rec->city;
            $after['City'] = $req->city;
        }
        if ($rec->stateprov != $req->stateprov) {
            $before['State/Province'] = $rec->stateprov;
            $after['State/Province'] = $req->stateprov;
        }
        if ($rec->postalcode != $req->postalcode) {
            $before['Postal Code'] = $rec->postalcode;
            $after['Postal Code'] = $req->postalcode;
        }
        if ($rec->countrycode != $req->countrycode) {
            $before['Country Code'] = $rec->countrycode;
            $after['Country Code'] = $req->countrycode;
        }
        if ($rec->apt != $req->apt) {
            $before['Apt/Suite'] = $rec->apt;
            $after['Apt/Suite'] = $req->apt;
        }
        self::addNew(self::TYPE_ADDRESS, $addressId, $before, $after);
    }

    

    
    public static function addNew($type, $typeId, $before, $after, $mode = self::MODE_UPDATE) {
        $r = new UpdateHistory();
        $r->type = $type;
        $r->type_id = $typeId;
        $r->before_update = json_encode($before);
        $r->after_update = json_encode($after);
        $r->mode = $mode;
        $r->created_at = Util::getCurrentDateTime();
        $r->updated_by = Auth::user()->id;
        $r->save();
    }

}

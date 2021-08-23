<?php

$mode = env("APP_ENV", 'dev');  // live or dev
$api_endpoinds = [
    'mode' => $mode,
    //
    'TOKENEXDetokenize' => 'Detokenize',
    'TOKENEXProcessTransactionAndTokenize' => 'ProcessTransactionAndTokenize',
    'TOKENEXGetKountHashValue' => 'GetKountHashValue',
    //idecide
    'iDecideUserDisable' => 'setDisabled',
    'iDecideUserEnable' => 'setEnabled',
    'iDecideCheckExistinUser' => 'users/subscriptions/grantDefaultPackage',
    'iDecideCreateNewUser' => 'users/create',
    'iDecideUpdatePassword' => 'update/password',
    'IDecideGenerateSSOToken' => 'createToken',
    'IDecideUpdateUserEmailAddress' => 'update/email',
    'IDecideUpdateUser' => 'update',
    //SOR
    'SORActivateUser' => 'clubmembership/activatemember',
    'SORGetMemberInfo' => 'clubmembership/getmembers',
    'SORDeactivatedUser' => 'clubmembership/deactivatemember',
    // TODO - Clean this up
    'SORGetLoginToken' => 'clubmembership/getlogintoken',
    'SORGetLoginToken' => 'clubmembership/getlogintokennovalidation',
    // 'SORGetLoginTokenNoVal' => 'clubmembership/getlogintokennovalidation',
    // TODO END
    'SORCreateUser' => 'clubmembership/createdefault',
    'SORUserTransfer' => 'clubmembership/transferuser',
    'UserAccountTypeID' => 9,
    'SORGetMembers' => 'clubmembership/getmembers',
    'HunterioAPIKey' => 'b03a524b35ba2ceb78a69ece6b57907502ee555a'
];

if ($mode == "prod") {
    $live_credentials = [
        //
        // 'MerchantGUID' => '4a812785-50bd-481f-86ae-3b086430a946',
        // 'MerchantPassword' => 'bYYwDaDuvL',
        //'eWalletAPIURL' => 'https://www.i-payout.net/eWalletWS/ws_JsonAdapter.aspx',
        //
        //
        'NetworkMerchantsUsername' => 'bitjarapi2',
        'NetworkMerchantsPassword' => '1EldmfcROI!',
        //
        //
        'SaveOnServiceURL' => 'https://api.saveonresorts.com/',
        //
        // idecide
        'IDecideServiceURL' => 'https://api.idecideinteractive.com/idecide/',
        'IDecideUserName' => 'ncrease',
        'IDecidePassword' => 'e30bf7c923bd46ab80a83',
        //
        // payap
        'CIDToken' => '100230401',
        //
        // token ex
        'TOKENEXAPIServiceURL' => 'https://api.tokenex.com/',
        'TOKENEXAPIKey' => 'AHnRo2tZAoGG5HJFjuYPEnkZa51hogANGW4VH3f3',
        'TOKENEXTokenEXId' => '4357543053584306',
        //
        // kount
        'KOUNTServiceURL' => 'https://risk.kount.net/',
        'KOUNTIFrameURL' => 'https://ssl.kaptcha.com/',
        'KOUNTAPIKey' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiI2MTYxMDciLCJhdWQiOiJLb3VudC4xIiwiaWF0IjoxNTUwOTQzNDQxLCJzY3AiOnsia2EiOm51bGwsImtjIjpudWxsLCJhcGkiOmZhbHNlLCJyaXMiOnRydWV9fQ.jVbi4AQibs3PE6U18PoS4zhezrcVYMXhVOwEz0O1XmA',
        'KOUNTMerchantID' => '616107',
        //
        // nmi
        'NMIUsername' => 'bitjarapi2',
        'NMIPassword' => '1EldmfcROI!',
        //
        // t1_payment
        't1Username' => 'Rsacco',
        't1Password' => '1EldmfcROI!',
        //
        // payArc payment
        'payArcUsername' => 'payncrease',
        'payArcPassword' => '1EldmfcROI!',
        //
        // bitpay
        'BitPayTokenStr' => '79SZEyqYUQf895yhdQBtvCnB9f4GqKKNgCZvTCGPfNQJ',
        'BitPayAPIURL' => 'https://bitpay.com/invoices/',
        'BitPayCallBackURL' => 'https://ncrease.com',
        'EncryptedFilesystemStorageKey' => 'dksiflzi3kl3ialdkf',
        //
        // skrill
        'SkrillURL' => 'https://pay.skrill.com',
        'SkrillPayToEmail' => 'shawn@traverusglobal.com',
        'SkrillCallBackURL' => 'http://ncrease.com/skrill/callback',
        'SkrillCancelURL' => 'https://ncrease.com/skrill/cancel',
        //
        // EsignGenie - note use zoho web based client
        'EsignGenie_Auth_URL' => 'https://www.esigngenie.com/esign/api/oauth2/access_token',
        'EsignGenie_NewDoc_URL' => 'https://www.esigngenie.com/esign/api/templates/createFolder',
        'EsignGenie_Client_id' => '66d5e92638fd4c20a32cbf0c19d77480',
        'EsignGenie_Secret' => '680149ecad62411f808c1171311d884d',
        'EsignGenie_W8BEN_Template_id' => '[64113]',
        // Note: we might want to move these to params passed in init in future version
        'EsignGenie_Redirect_Good' => 'https://ncrease.com/',
        'EsignGenie_Redirect_Fail' => 'https://ncrease.com/update-user-tax-info-international/',

        'eWalletMerchantURL' => 'https://ncrease.globalewallet.com/MemberLogin.aspx',

        //VGS
        'vgs_url' => env('VGS_LV_URL'),
        'vgs_proxy' => env('VGS_LV_PROXY'),
        'vgs_port' => env('VGS_LV_PORT'),
        'vgs_userpaswd' => env('VGS_LV_USER_PASSWORD'),

        //iPaytotal
        'ipaytotal_url' => 'https://ipaytotal.solutions',
        'ipaytotal_key' => '6418lIvLsPnAM0u4CwduuC3yZg5RmlVY96RER4WQft35b9ZBAMXj9wRmbJP0T5ZC94ZU',
        'TaxJar' => 'fa9e45f33af0782931ce2250f3218981'
    ];
    $api_endpoinds = array_merge($api_endpoinds, $live_credentials);
} else {
    $dev_credentials = [
        'MerchantGUID' => '4a812785-50bd-481f-86ae-3b086430a946',
        'MerchantPassword' => '7uKy7ABm25',
        'eWalletAPIURL' => 'https://testewallet.com/eWalletWS/ws_JsonAdapter.aspx',
        'eWalletMerchantURL' => 'https://ncrease.testewallet.com',
        'eWalletCheckoutThankYouPageURL' => 'http://ncrease.com/thank-you',
        //
        //
        'NetworkMerchantsUsername' => 'demo',
        'NetworkMerchantsPassword' => 'password',
        //
        //
        // 'SaveOnServiceURL' => 'https://api.saveonuat.com/',//URL for sandbox
        'SaveOnServiceURL' => 'https://api.saveonresorts.com/', //URL for production
        //
        // idecide
        // 'IDecideServiceURL' => '',
        // 'IDecideUserName' => '',
        // 'IDecidePassword' => '',
        'IDecideServiceURL' => 'https://api.idecideinteractive.com/idecide/',
        'IDecideUserName' => 'ncrease',
        'IDecidePassword' => 'e30bf7c923bd46ab80a83',
        //
        // payap
        'CIDToken' => '',
        //
        // token ex
        'TOKENEXAPIServiceURL' => 'https://api.tokenex.com/',
        'TOKENEXAPIKey' => 'AHnRo2tZAoGG5HJFjuYPEnkZa51hogANGW4VH3f3',
        'TOKENEXTokenEXId' => '4357543053584306',
        //
        // kount
        'KOUNTServiceURL' => 'https://risk.beta.kount.net/',
        'KOUNTIFrameURL' => 'https://tst.kaptcha.com/',
        'KOUNTAPIKey' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiI5OTk2NjYiLCJhdWQiOiJLb3VudC4xIiwiaWF0IjoxNDk0NTM0Nzk5LCJzY3AiOnsia2EiOm51bGwsImtjIjpudWxsLCJhcGkiOmZhbHNlLCJyaXMiOnRydWV9fQ.eMmumYFpIF-d1up_mfxA5_VXBI41NSrNVe9CyhBUGck',
        'KOUNTMerchantID' => '999666',
        //
        // nmi
        'NMIUsername' => 'demo',
        'NMIPassword' => 'password',
        //
        // t1_payment
        't1Username' => 'Rsacco',
        't1Password' => '1EldmfcROI!',

        //
        // payArc payment
        'payArcUsername' => 'payncrease',
        'payArcPassword' => '1EldmfcROI!',
        //   'payArcUsername' => 'demo',
        //   'payArcPassword' => 'password',
        'payArcSecurityKey' => '2JkwJZ6h28DWWJYRsjxTKz96x773KSX3',
        'payArcSecCode' => 'WEB',
        //
        // bitpay
        'BitPayTokenStr' => 'C2HJM7LZw3bXdVoqQkGkyf',
        'BitPayAPIURL' => 'https://test.bitpay.com/invoices/',
        'BitPayCallBackURL' => 'https://dev.cloud.countdown4freedom.com',
        'EncryptedFilesystemStorageKey' => 'dksiflzi3kl3ialdkf',
        //
        // skrill
        'SkrillURL' => 'https://pay.skrill.com',
        'SkrillPayToEmail' => 'shawn@traverusglobal.com',
        'SkrillCallBackURL' => 'https://dev.cloud.countdown4freedom.com/skrill/callback',
        'SkrillCancelURL' => 'https://dev.cloud.countdown4freedom.com/skrill/cancel',
        //
        // EsignGenie - note use zoho web based client
        'EsignGenie_Auth_URL' => 'https://www.esigngenie.com/esign/api/oauth2/access_token',
        'EsignGenie_NewDoc_URL' => 'https://www.esigngenie.com/esign/api/templates/createFolder',
        'EsignGenie_Client_id' => '429386c821914e1194ca8c2e544ee681',
        'EsignGenie_Secret' => '95083dc67f9a423d90d892d6f6b87e3c',
        'EsignGenie_W8BEN_Template_id' => '[63943]',
        // Note: we might want to move these to params passed in init in future version
        'EsignGenie_Redirect_Good' => env('EGENIE_SUCCESS_URL'),
        'EsignGenie_Redirect_Fail' => env('EGENIE_ERROR_URL'),

        //VGS
        'vgs_url' => env('VGS_SB_URL'),
        'vgs_proxy' => env('VGS_SB_PROXY'),
        'vgs_port' => env('VGS_SB_PORT'),
        'vgs_userpaswd' => env('VGS_SB_USER_PASSWORD'),

        //iPaytotal
        'ipaytotal_url' => 'https://ipaytotal.solutions',
        'ipaytotal_key' => '6418lIvLsPnAM0u4CwduuC3yZg5RmlVY96RER4WQft35b9ZBAMXj9wRmbJP0T5ZC94ZU',
        'TaxJar' => 'f13e00420533690dc92539e760137d54'
    ];
    $api_endpoinds = array_merge($api_endpoinds, $dev_credentials);
}
return $api_endpoinds;

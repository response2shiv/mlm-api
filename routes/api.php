<?php

use Illuminate\Support\Facades\Route;


Route::middleware('throttle:60,1')->prefix('v1/affiliate')->group(function () {

    Route::post('login', ['uses' => 'Affiliates\AuthController@login']);
    Route::post('forgot-password', 'Affiliates\AuthController@sendPasswordResettingEmail');
    Route::post('reset-password', 'Affiliates\AuthController@resetPassword');
    Route::get('admin-sso/{distid}/{token}', 'Affiliates\AuthController@getAdminSSO');
});

Route::get('/unicrypt', function () {
    // return 'Hello World - ';
    dd(Request());
});


Route::middleware('throttle:60,1')->prefix('v1/worldseries')->group(function () {
    Route::post('calculate', 'Affiliates\WorldSeriesEventsController@calculate');
    Route::post('bonus-runs', 'Affiliates\WorldSeriesEventsController@calculateBonusRuns');

    Route::get('snapshots', function () {
        Artisan::call('worldseries:snapshots', []);
    });
});

//Just to test UniCrypt requests - CAN BE DELETED WHEN IMPLEMENTATION IS DONE
Route::middleware('throttle:60,1')->prefix('v1/unicrypt')->group(function () {
    Route::get('create-invoice', 'UnicryptController@createInvoice');
    Route::any('status', 'UnicryptController@checkStatus');
});

//Just to test VGS requests - CAN BE DELETED WHEN IMPLEMENTATION IS DONE
Route::middleware('throttle:60,1')->prefix('v1/vgs')->group(function () {
    Route::post('print', 'Affiliates\TemporaryPaymentController@print');
});

Route::middleware(['auth:api', 'throttle:60,1'])->prefix('v1/tax')->group(function () {
    Route::post('calculate/rate', 'TaxController@calculateRate');
});

Route::middleware('throttle:60,1')->prefix('v1/email')->group(function () {
    Route::get('verify/{email}', 'Affiliates\UserController@verifyEmail');
    Route::get('unsubscribe/{email}', 'Mailgun\MailgunController@unsubscribe');
});

Route::middleware(['throttle:60,1'])->prefix('v1/tax')->group(function () {
    Route::post('calculate/rate', 'TaxController@calculateRate');
    Route::post('calculate/taxes', 'TaxController@calculateTaxes');
    Route::post('create/order', 'TaxController@createOrder');
});

Route::middleware('throttle:60,1')->prefix('v1/join')->group(function () {
    Route::post('signup-world-series', 'Affiliates\WorldSeriesEventsController@create');
    Route::post('resume-owner-world-series', 'Affiliates\WorldSeriesController@resumeOwner');
    Route::post('resume-player-world-series', 'Affiliates\WorldSeriesController@resumePlayer');
    Route::get('top-teams-world-series', 'Affiliates\WorldSeriesController@resumeTopTeams');
    Route::get('email/verify/{email}', 'Affiliates\UserController@verifyEmail');

    # To test
    Route::post('errors-world-series', 'Affiliates\WorldSeriesErrorsController@create');
    Route::get('get-errors-world-series', 'Affiliates\WorldSeriesErrorsController@getRefundOrders');

    # UniCrypt
    Route::post('unicrypt/create-invoice/', 'UnicryptController@joinCreateInvoice');
    Route::any('unicrypt/status', 'UnicryptController@checkStatus');

    # Merchant Transaction Tracker
    Route::post('merchant/create-transaction', 'MerchantTransactionController@createTransaction');
    Route::get('merchant/status/{transaction_id}', 'MerchantTransactionController@checkMerchantTransactionStatus');
});


Route::middleware('throttle:60,1')->middleware(['auth:api', 'throttle:60,1'])->group(function () {
    Route::prefix('v1/affiliate')->group(function () {
        Route::get('show-ip-server', 'Affiliates\UserController@showIpServer');
        Route::prefix('user')->group(function () {
            Route::post('change-password', 'Affiliates\UserController@changePassword');
            Route::get('my-profile/{type?}', 'Affiliates\UserController@showMyProfile');
            Route::post('save-profile', 'Affiliates\UserController@saveProfile');
            Route::post('save-placements', 'Affiliates\UserController@savePlacements');
            Route::post('save-primary-address', 'Affiliates\UserController@savePrimaryAddress');
            Route::post('save-billing-address', 'Affiliates\UserController@saveBillingAddress');
            Route::post('save-shipping-address', 'Affiliates\UserController@saveShippingAddress');
            Route::put('update-shipping-address/{id}', 'Affiliates\UserController@updateShippingAddress');
            Route::put('set-primary-shipping-address/{id}', 'Affiliates\UserController@setPrimaryShippingAddress');
            Route::post('billing-add-new-card', 'Affiliates\UserController@billingAddNewCard');
            Route::post('delete-payment-method', 'Affiliates\UserController@deletePaymentMethod');
            Route::post('replicated-preferences', 'Affiliates\UserController@savePreferences');
            Route::post('save-profile-picture-url', 'Affiliates\UserController@saveProfilePicture');
            Route::get('user-info', 'Affiliates\UserController@getUserInfo');
            Route::get('primary-address', 'Affiliates\UserController@getUserPrimaryAddress');
            Route::get('shipping-address', 'Affiliates\UserController@getUserShippingAddress');
            Route::get('get-shipping-address/{addressId}', 'Affiliates\UserController@showMyProfile_shipping_address');
            Route::get('delete-shipping-address/{addressId}', 'Affiliates\UserController@deleteUserShippingAddress');

            /**
             * Payment Methods
             */
            Route::get('payment-methods', 'Affiliates\UserController@getPaymentMethods');
            Route::post('payment-methods/remove-card', 'Affiliates\UserController@deletePaymentMethod');
            Route::post('payment-methods/setPrimary', 'Affiliates\UserController@setPaymentMethodAsPrimary');
            Route::post('payment-methods/add-card', 'Affiliates\UserController@addPaymentMethod');
            Route::get('payment-methods/get-card/{id}', 'Affiliates\UserController@getPaymentMethod');

            Route::get('monthpqv/{userid}/{month}/{year}', 'Affiliates\UserController@getPaymentMethods');

            //CAREFULL This endpoint will remove the user from its position and all respective records
            Route::post('purge', 'Affiliates\UserController@purgeUser');
            Route::post('purge-pending', 'Affiliates\UserController@purgePendingsers');
        });

        // Billgenius SSO
        Route::prefix('billgenius')->group(function () {
            Route::post('sso', 'BillgeniusController@executeSSO');
        });

        // Save On
        Route::prefix('save-on')->group(function () {
            Route::post('/create-save-on-account', 'Affiliates\SaveOnController@createNewAccountByUser');
        });

        //Buckets
        Route::post('/buckets/add-volume', 'Affiliates\BucketController@addVolume');
        Route::get('/buckets/{userId}', 'Affiliates\BucketController@getBucketUserVolumes');

        //IDecide
        Route::prefix('idecide')->group(function () {
            Route::post('reset-password', 'Affiliates\iDecideController@resetPassword');
            Route::post('reset-mail', 'Affiliates\iDecideController@resetEmail');
            Route::post('create-idecide-account', 'Affiliates\iDecideController@createNewAccountByUser');
        });

        Route::prefix('dashboard')->group(function () {
            //View variables from env file
            Route::get('test-mailgun/{sendmail?}', 'Affiliates\DashboardController@testMailgun');

            Route::get('details', 'Affiliates\DashboardController@index');
            //Route::post('igo-link', 'Affiliates\DashboardController@upgradeProductCheckOut'); //Execute upgrade on the account
            Route::get('igo', 'Affiliates\DashboardController@iGo');
            Route::get('idecide', 'Affiliates\DashboardController@idecide');
            Route::post('events-update-token', 'Affiliates\DashboardController@upateEventsToken');
            Route::get('rank/{rank}/{month}/{year}', 'Affiliates\DashboardController@getRankWidget');
            Route::get('monthly-projected-totals', 'Affiliates\DashboardController@getMonthlyProjectedQv');
            Route::get('monthly-projected-details', 'Affiliates\DashboardController@getMonthlyProjectedQvDetails');
            Route::post('monthly-projected-details', 'Affiliates\DashboardController@getMonthlyProjectedQvDetails');
            Route::post('monthly-projected-details-datatable', 'Affiliates\DashboardController@getMonthlyProjectedQvDetailsDataTable');
        });

        Route::prefix('binary-placement')->group(function () {
            //View variables from env file
            Route::get('/', 'Affiliates\BinaryPlacement@index');
            Route::get('/direct-line', 'Affiliates\BinaryPlacement@getDirectLine');
            Route::post('/update', 'Affiliates\BinaryPlacement@updatePlacement');

            Route::post('placement-send-sms', 'Affiliates\BinaryPlacement@sendSMS');
            Route::post('placement-send-mail', 'Affiliates\BinaryPlacement@sendMail');
        });

        Route::prefix('bucket-placement-lounge')->group(function () {
            //View variables from env file
            Route::get('/', 'Affiliates\BinaryPlacement@index');
            Route::get('/direct-line', 'Affiliates\BinaryPlacement@getDirectLine');
            Route::post('/update', 'Affiliates\BinaryPlacement@updatePlacement');

            Route::get('/get-users', 'Affiliates\BucketPlacementController@getUsers');
            Route::post('/search-user', 'Affiliates\BucketPlacementController@searchUser');

            Route::post('/set-user-on-bucket', 'Affiliates\BucketPlacementController@setUserOnBucket');
        });



        //E-Wallet
        Route::get('e-wallet', 'Affiliates\EwalletTransactionController@index');
        Route::get('e-wallet/transfer-history', 'Affiliates\EwalletTransactionController@getTransferHistory');
        Route::post('e-wallet/transfer-history', 'Affiliates\EwalletTransactionController@getTransferHistory');
        Route::post('e-wallet/transfer-to-ipayout', 'Affiliates\EwalletTransactionController@transferToPayOut');
        Route::post('e-wallet/vitals', 'Affiliates\EwalletTransactionController@vitalsSubmit');
        Route::post('authy/request', 'Affiliates\UserTransferController@twoFactorAuthRequest');
        Route::post('authy/email/request', 'Affiliates\UserTransferController@twoFactorEmailAuthRequest');
        Route::post('authy/verify', 'Affiliates\EwalletTransactionController@submitTFA');
        Route::get('authy/email/pdf/verify/{token}/{authy_id}', 'Affiliates\EwalletTransactionController@verifyEmailPDFToken');
        Route::get('authy/email/verify/{token}/{authy_id}', 'Affiliates\EwalletTransactionController@verifyEmailToken');

        //Ipayout
        Route::post('ipayout/create', 'Affiliates\IpayoutController@createPayout');
        Route::post('ipayout/checkcheck-username', 'Affiliates\IpayoutController@checkUsernamePayout');

        # New Commissions Viewer
        Route::post('commission-viewer', 'Affiliates\CommissionController@getCommissionViewer');
        Route::post('commission-viewer-details', 'Affiliates\CommissionController@getCommissionDetails');
        Route::post('commission-viewer-details-level', 'Affiliates\CommissionController@getCommissionDetailsByLevel');

        # Older Commissions Viewer
        Route::get('commission', 'Affiliates\CommissionController@getCommission');
        Route::post('commission/weekly', 'Affiliates\CommissionController@commissionWeekly');
        Route::post('commission/weekly/details', 'Affiliates\CommissionController@commissionWeeklyDetails');

        Route::post('commission/unilevel-commission-details', 'Affiliates\CommissionController@unilevelCommissionDetails');
        Route::post('commission/leadership-commission-details', 'Affiliates\CommissionController@leadershipCommissionDetails');
        Route::post('commission/tsb-commission-details', 'Affiliates\CommissionController@tsbCommissionDetails');
        Route::post('commission/promo-commission-details', 'Affiliates\CommissionController@promoCommissionDetails');
        Route::post('commission/vibe-commission-details', 'Affiliates\CommissionController@vibeCommissionDetails');

        // Commissions Viewer - Tab Weekly
        Route::post('commission-weekly', 'Affiliates\CommissionController@getWeeklyCommission');
        Route::post('commission-weekly-details', 'Affiliates\CommissionController@getWeeklyCommissionDetails');
        Route::post('commission-weekly-binary-details', 'Affiliates\CommissionController@getWeeklyCommissionBinaryDetails');

        //Subscriptions
        Route::prefix('subscription')->group(function () {
            Route::get('/index/{country?}/{locale?}', 'Affiliates\SubscriptionController@index');
            Route::get('subscription-reactivate', 'Affiliates\SubscriptionController@dlgSubscriptionReactivate');
            Route::get('subscription-reactivate-suspended-user/{country?}/{locale?}', 'Affiliates\SubscriptionController@dlgSubscriptionReactivateSuspendedUser');
            Route::get('get-upgrade-countdown', 'Affiliates\UpgradeController@getUpgradeCountdown');
            Route::get('upgrade-now/{package}/{country?}/{locale?}', 'Affiliates\UpgradeController@dlgUpgradePackage'); //Get the package information
            Route::post('get-grace-period', 'Affiliates\SubscriptionController@getGracePeriod');
            Route::post('upgrade-now', 'Affiliates\UpgradeController@upgradeProductCheckOut'); //Execute upgrade on the account
            Route::post('save-now', 'Affiliates\SubscriptionController@saveSubscription');
            Route::post('add-new-card-subscription', 'Affiliates\SubscriptionController@addNewCard');
            Route::post('reactivate-subscription-add-coupon-code', 'Affiliates\SubscriptionController@checkCouponCode');
            Route::post('reactivate-subscription', 'Affiliates\SubscriptionController@reactivateSubscription');
            Route::post('reactivate-suspended-subscription', 'Affiliates\SubscriptionController@reactivateSubscriptionSuspendedUser');
            Route::post('add-new-card-subscription-reactivate', 'Affiliates\SubscriptionController@addNewCardSubscriptionReactivate');
            Route::post('add-new-card-subscription-reactivate-suspended-user', 'Affiliates\SubscriptionController@addNewCardSubscriptionSuspendedUserReactivate');
            Route::post('upgrade-package-check-out-new-card', 'Affiliates\UpgradeController@upgradeProductsCheckOutNewCard');
        });

        //Country / States
        Route::get('get-countries', 'Affiliates\TemporaryPaymentController@getCountries');
        Route::get('get-states/{country_code}', 'Affiliates\TemporaryPaymentController@getStates');

        Route::prefix('reports')->group(function () {
            //Route::post('entire-organization-report', 'Affiliates\ReportController@getEntireOrganizationReportDataTable');
            Route::get('entire-organization-report', 'Affiliates\ReportController@getEntireOrganizationReportData');
            Route::post('entire-organization-report-data', 'Affiliates\ReportController@getEntireOrganizationReportDataTable');
            Route::post('weekly-enrollment-report', 'Affiliates\ReportController@weeklyEnrollmentReportDataTable');
            Route::post('weekly-binary-view', 'Affiliates\ReportController@weeklyBinaryReportDataTable');
            Route::post('personal-enrollments', 'Affiliates\ReportController@getPersonallyEnrolledDistributorsDataTable');
            Route::post('vip-distributors', 'Affiliates\ReportController@getVipDistributorsDataTable');
            Route::post('personally-enrolled-detail', 'Affiliates\ReportController@getEnrolledInternDataTable');
            Route::post('distributors-by-level-detail/', 'Affiliates\ReportController@getDistributorsByLevelDetailDataTable');
            Route::post('org-drill-down/{distid}', 'Affiliates\ReportController@getOrgDrillDownDataTable');
            Route::post('dist-by-country', 'Affiliates\ReportController@getDistributorsByCountryDataTable');
            Route::post('pre-enrollment-selection', 'Affiliates\ReportController@getPreEnrollmentSelectionDataTable');
            Route::post('subscription-report', 'Affiliates\ReportController@getSubcsriptionReportDataTable');
            Route::post('subscription-by-payment-method', 'Affiliates\ReportController@getSubscriptionByPaymentMethodDataTable');
            Route::post('rank-advancement-report', 'Affiliates\ReportController@GetRankAdvancementDataTable');
            Route::post('fsb-commission-report', 'Affiliates\ReportController@getFsbCommissionDataTable');
            Route::post('subscription-history', 'Affiliates\ReportController@subscriptionHistoryDataTable');
            Route::get('historical', 'Affiliates\ReportController@getHistoricalReport');
            Route::get('invoices', 'Affiliates\ReportController@invoice');
            Route::get('orders-completed', 'Affiliates\ReportController@getOrderCompleted');
            Route::post('orders-completed', 'Affiliates\ReportController@getOrderCompleted');
            Route::get('orders-pending', 'Affiliates\ReportController@gerOrderPending');
            Route::post('orders-pending', 'Affiliates\ReportController@gerOrderPending');
            Route::get('invoice-view/{order_id}', 'Affiliates\ReportController@viewInvoice');
            Route::get('pre-order-view/{order_id}', 'Affiliates\ReportController@viewPreOrder');
            Route::post('pear/{id?}', 'Affiliates\ReportController@getPearReport');
            Route::get('pear/{id?}', 'Affiliates\ReportController@getPearReport');
            Route::get('pear-report/{id?}', 'Affiliates\ReportController@getPearReportByUser')->name('pear-report');
            Route::post('pear-report/{id?}', 'Affiliates\ReportController@getPearReportByUser')->name('pear-report');
        });

        Route::prefix('purchase')->group(function () {
            Route::post('foundation', 'Affiliates\TemporaryPaymentController@checkoutFoundation');
            Route::post('check-coupon', 'Affiliates\UpgradeController@checkCouponCodeUpgrade');
            Route::post('add-new-card-foundation', 'Affiliates\TemporaryPaymentController@checkoutCardFoundation');
            Route::post('generic-check-out', 'Affiliates\TemporaryPaymentController@genericCheckout');
            Route::post('generic-check-out-new-card', 'Affiliates\TemporaryPaymentController@genericCheckOutNewCard');
            Route::get('add-new-discount-code', 'Affiliates\TemporaryPaymentController@generateNewDiscountCouponCode');
            Route::post('create-discount-code', 'Affiliates\TemporaryPaymentController@createNewDiscountCoupon');
        });

        Route::prefix('media')->group(function () {
            Route::get('all-media', 'Affiliates\MediaController@getAllMedia');
            Route::get('training', 'Affiliates\MediaController@checkVideoAccess');
            Route::get('downloads', 'Affiliates\MediaController@downloads');
        });

        Route::prefix('shop')->group(function () {
            Route::post('index', 'Affiliates\ShopController@index');
            Route::get('/product/{id}/{country?}/', 'Affiliates\ShopController@getProduct');
        });

        Route::prefix('shopping-cart')->group(function () {
            Route::get('setting', 'Affiliates\ShoppingCartController@getSetting');
            Route::get('cart', 'Affiliates\ShoppingCartController@getShoppingCart');
            Route::post('update-cart', 'Affiliates\ShoppingCartController@updateCart');
            Route::post('add-product', 'Affiliates\ShoppingCartController@addProductToCart');
            Route::post('update-product', 'Affiliates\ShoppingCartController@updateProductOnCart');
            Route::post('remove-product', 'Affiliates\ShoppingCartController@removeProductFromCart');
            Route::get('checkout', 'Affiliates\ShoppingCartController@checkout');
            Route::post('process-payment', 'Affiliates\ShoppingCartController@processPayment');
            Route::post('apply-payment-method', 'Affiliates\ShoppingCartController@applyPaymentMethod');
            Route::post('apply-voucher', 'Affiliates\ShoppingCartController@applyVoucher');
            Route::post('remove-voucher', 'Affiliates\ShoppingCartController@removeVoucher');
            Route::get('payments-methods-available', 'Affiliates\ShoppingCartController@paymentMethodsAvailable');

            Route::get('get-status/{orderhash}', 'Affiliates\ShoppingCartController@getOrderStatus');
            Route::post('test-send-status', 'UnicryptController@changeStatus');

            Route::post('finish-purchase', 'Affiliates\ShoppingCartController@processAfterMerchantResponse');
            Route::get('get-total-items-cart', 'Affiliates\ShoppingCartController@getTotalItemsCart');
        });

        Route::prefix('products')->group(function () {
            //Route::post('entire-organization-report', 'Affiliates\ReportController@getEntireOrganizationReportDataTable');
            Route::get('by-id/{id}', 'Affiliates\ProductController@getProductById');
        });

        Route::post('product-currency', 'Affiliates\ProductController@getProductByIdAndCurrency');

        Route::prefix('ranks')->group(function () {
            Route::get('insights/{rank}', 'Affiliates\UserRankHistoryController@getRankValues');
        });

        Route::prefix('organization')->group(function () {
            //Route::post('entire-organization-report', 'Affiliates\ReportController@getEntireOrganizationReportDataTable');
            Route::get('customer-data', 'Affiliates\CustomerController@getCustomerDistData');
            Route::post('customer-data', 'Affiliates\CustomerController@getCustomerDistData');
            Route::get('binary-viewer/{id?}', 'Affiliates\BinaryViewerController@getBinaryViewerData')->name('binaryViewer');
            Route::post('binary-viewer/search', 'Affiliates\BinaryViewerController@getAjaxDistributors');
            Route::post('binary-viewer/init-search', 'Affiliates\BinaryViewerController@getInitSearchDistributors');
        });

        Route::prefix('currency')->group(function () {
            Route::get('convert', 'Billing\CurrencyController@convertPassthrough');
            Route::post('convert', 'Billing\CurrencyController@convertPassthrough');
        });
    });
});

Route::middleware('throttle:60,1')->get('v1/status/healthcheck', 'HealthCheckController@check');
Route::middleware('throttle:60,1')->get('v1/status/mailgun', 'HealthCheckController@mailgun');

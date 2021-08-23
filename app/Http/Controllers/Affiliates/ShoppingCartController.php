<?php

namespace App\Http\Controllers\Affiliates;

use App\Helpers\Util;
use App\Http\Controllers\Controller;
use App\Jobs\DistributeVolumes;
use App\Models\Address;
use App\Models\BillingCountries;
use App\Models\DiscountCoupon;
use App\Models\EwalletTransaction;
use App\Models\Order;
use App\Models\PreOrder;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\ShoppingCart;
use App\Models\ShoppingCartSettings;
use App\Models\ShoppingCartProducts;
use App\Models\Unicrypt;
use App\Models\User;
use App\Models\UserPaymentMethod;
use App\Models\UserSettings;
use App\Services\BillingService;
use App\Services\PreOrderService;
use app\Services\TaxjarService;
use App\Services\UnicryptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use Log;
use Validator;
use DB;

class ShoppingCartController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function getShoppingCart()
    {
        //Get all items from shopping cart
        $shoppingCart = ShoppingCart::getUserCart();


        if ($shoppingCart) {
            $this->setResponseCode(200);
            $this->setResponse($shoppingCart);
            return $this->showResponse();
        }

        $this->setResponseCode(400);
        $this->setResponse(['error' => 1, 'msg' => "Shopping Cart is Empty"]);
        return $this->showResponse();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function getSetting()
    {
        //Get all items from shopping cart
        $shoppingCartSettings = ShoppingCartSettings::all()->first();

        if ($shoppingCartSettings) {
            $this->setResponseCode(200);
            $this->setResponse($shoppingCartSettings);
            return $this->showResponse();
        }

        $this->setResponseCode(400);
        $this->setResponse(['error' => 1, 'msg' => "Shopping Cart Settings is Empty"]);
        return $this->showResponse();
    }



    /**
     * Add product to the shopping cart
     * Create cart if it doens't exist
     * @return \Illuminate\Http\Response
     */
    public function addProductToCart(Request $request)
    {
        $data = $request->all();

        //Check if data is correct
        $validate = $this->validateCartRequest($data, true);

        if ($validate) {
            return $validate;
            exit();
        }

        //Add item to cart
        $product = Product::getById($data['product_id']);

        #check if product is a foundantion/donation
        if ($product->id == 39) {
            ShoppingCartProducts::AddProductDonationToCart($product, $data['quantity'], $data['amount']);
        } else {
            ShoppingCartProducts::addProductToCart($product, $data['quantity']);
        }

        $cartItems = ShoppingCartProducts::getProductsCount();

        $this->setResponse(["cartItems" => $cartItems, "error" => 0, "msg" => "Item Added to Cart."]);
        $this->setMessage("Item Added to Cart.");
        $this->setResponseCode(200);

        return $this->showResponse();
    }

    public function updateCart(Request $request)
    {

        foreach ($request->all() as $key => $value) {

            ShoppingCartProducts::updateProductOnCart($key, $value);
        }

        $shoppingCart = ShoppingCart::getUserCart();

        if ($shoppingCart) {
            $this->setResponseCode(200);
            $this->setResponse($shoppingCart);
            return $this->showResponse();
        }
    }

    /**
     * Update product quantity on the shopping cart
     * Create cart if it doesn't exist
     * @return \Illuminate\Http\Response
     */
    public function updateProductOnCart(request $request)
    {
        $data = $request->all();
        $validate = $this->validateCartRequest($data);
        if ($validate) {
            return $validate;
            exit();
        }

        $data = $request->all();

        $product = Product::getById($data['product_id']);

        //Add item to cart
        $create = ShoppingCartProducts::updateProductOnCart($product, $data['quantity']);

        $this->setMessage("Products saved.");
        $this->setResponseCode(200);
        $this->setResponse(ShoppingCart::getUserCart()->with('items.product')->get());
        return $this->showResponse();
    }

    /*
    * Validating request
    */
    private function validateCartRequest($data, $insert = false)
    {
        //Check if data is correct
        $validator = Validator::make($data, [
            'product_id' => 'required|numeric',
            'quantity' => 'required|numeric|min:1',
        ], [
            'product_id.required' => 'Product ID is required',
            'quantity.required' => 'Quantity is required',
        ]);

        if ($validator->fails()) {
            $messages = $validator->messages();
            foreach ($messages->all() as $m) {
                $this->setMessage($m);
            }
            $this->setResponseCode(400);
            return $this->showResponse();
            exit();
        }

        if ($insert) {
            //Check if we can add this product on cart based on allow_multiple_on_cart
            if (!ShoppingCartProducts::isProductAllowed($data['product_id'])) {
                $this->setResponse(["error" => 1, "msg" => "This product can not be combined with any other products."]);
                $this->setMessage('This product can not be combined with any other products.');
                $this->setResponseCode(400);
                return $this->showResponse();
                exit();
            }

            //Check if the total+new product exceeds the limit defined to orders
            if (ShoppingCart::checkTotalLimitOnShoppingCart($data['product_id'], $data['quantity'], $data['amount'])) {
                $this->setResponse(["error" => 1, "msg" => "The order max total exceeded the limit allowed."]);
                $this->setMessage('The order max total exceeded the limit allowed.');
                $this->setResponseCode(400);
                return $this->showResponse();
                exit();
            };
        }

        //Checking if product exists
        $product = Product::getById($data['product_id']);
        $invalid = false;
        if (!$product) {
            $invalid = true;
        } else {
            if (!$product->is_enabled) {
                $invalid = true;
            }
        }

        if ($invalid) {
            $this->setMessage('Invalid Product ID');
            $this->setResponseCode(404);
            return $this->showResponse();
            exit();
        }
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function removeProductFromCart(Request $request)
    {
        $data = $request->all();
        //Check if data is correct
        $validator = Validator::make($data, [
            'product_id' => 'required|numeric',
        ], [
            'product_id.required' => 'Product ID is required',
        ]);

        //Check if data is correct
        if ($validator->fails()) {
            $messages = $validator->messages();
            foreach ($messages->all() as $m) {
                $this->setMessage($m);
            }
            $this->setResponseCode(400);
            return $this->showResponse();
            exit();
        }

        //Remove item from cart
        $create = ShoppingCartProducts::removeProductFromCart($data);

        $cartItems = ShoppingCartProducts::getProductsCount();

        $userCart = ShoppingCart::getUserCart();
        $userCart->total = number_format($userCart->total, 2) ?? '0,00';
        $userCart->subtotal = number_format($userCart->subtotal, 2) ?? '0,00';
        $userCart->items = count($userCart->items);

        $this->setResponse([
            "cartItems" => $cartItems,
            "shopping_cart" => $userCart

        ]);

        $this->setMessage("Product removed.");
        $this->setResponseCode(200);
        // $this->setResponse(ShoppingCart::getUserCart());
        return $this->showResponse();
    }

    public function checkout()
    {
        //Prepare Cart to checkout
        $cart = ShoppingCart::getUserCart();

        #calculate taxes
        # $tax = ShoppingCart::getCartTaxes($cart);
        # $cart->tax = $tax->amount_to_collect;

        $this->setResponseCode(200);
        $this->setResponse($cart);
        return $this->showResponse();
    }

    public function paymentMethodsAvailable()
    {
        $user = Auth::user();
        $cart = ShoppingCart::getUserCart();

        $subtotal = $cart->total - $cart->discount;


        # Get merchant by country code
        $merchant = BillingCountries::whereCode($user->country_code)->first();
        $merchantByCountry = is_null($merchant) ? 'US' : $merchant->merchant;

        $paymentMethod = array();
        $paymentMethod[] = array("name" => "ewallet", "label" => "E-wallet");
        $paymentMethod[] = array("name" => $merchantByCountry, "label" => "Credit Card");

        $data["allow_creditCard"] = false;

        $minimunLimitUnicrypt = Util::getSetting('shopping-cart-unicrypt-minimum');
        if ($subtotal >= (int)$minimunLimitUnicrypt) {
            $data["allow_creditCard"] = true;
        }

        $data['paymentMethods'] = $paymentMethod;
        $data['minimum_via_unicrypt'] = $minimunLimitUnicrypt;

        $this->setResponse($data);
        $this->setResponseCode(200);

        return $this->showResponse();
    }

    public function processPayment(Request $request)
    {
        $user = Auth::user();
        $cart = ShoppingCart::getUserCart();
        $gift = $request->gift;
        $shipping_address_id = $request->shipping_address ?: null;

        if (!$cart) {
            return response()->json(['error' => 1, 'msg' => 'Sorry, Your cart is empty.', 'data' => []]);
        } else {

            if (!$request->payment_method || empty($request->payment_method)) {
                return response()->json(['error' => 1, 'msg' => 'Sorry, Invalid payment method id.', 'data' => []]);
            }

            $total = $cart->total - $cart->discount;

            if ($request->payment_method == 'ewallet') {
                return $this->doPaymentByEwallet($gift, $shipping_address_id);
            }

            if ($request->payment_method == 'unicrypt') {

                # Create a pre order
                $preOrder = $this->createPreOrderAfterCheckout($cart, $shipping_address_id);

                if ($preOrder) {
                    $preOrder->gift_box = $gift;
                    $preOrder->save();
                    $preOrder->shipping_address_id = $shipping_address_id;
                    # Call the Unicrypt Payment
                    $payment = UnicryptService::createInvoice($user, $preOrder);

                    # If can create an invoice and allocate the user, return success
                    if ($payment) {

                        # Tracking the orderhash from unicrypt with pre-order
                        $preOrder->orderhash = $payment['orderhash'];
                        $preOrder->save();

                        # Remove all data from the cart
                        ShoppingCart::destroyCart();
                    } else {
                        $response = ['error' => 1, 'msg' => 'Sorry, The payment failed, try again.', 'data' => []];
                    }
                } else {
                    $response = ['error' => 1, 'msg' => 'Sorry, The order is not completed, try again.', 'data' => []];
                }

                $response = ['error' => 0, 'msg' => 'Payment successful.', 'data' =>  $payment];
            }

            if ($request->payment_method == 'billing') {

                $preOrder = $this->createPreOrderAfterCheckout($cart, $shipping_address_id);
                $preOrder->gift_box = $gift;
                $preOrder->shipping_address_id = $shipping_address_id;
                $preOrder->save();

                $creditCard = UserPaymentMethod::whereId($request->payment_method_id)->first();

                # Update the PreOrder with payment method id
                if ($request->payment_method_id) {
                    $preOrder->payment_methods_id = $request->payment_method_id;
                    $preOrder->user_payment_methods_id = $request->payment_method_id;
                    $preOrder->shipping_address_id = $shipping_address_id;
                    $preOrder->save();
                }

                $billingAddress = $creditCard->billingAddress;
                $shippingAddress = $creditCard->billingAddress;

                # Calling Billing System to proccess on iPayTotal
                $billingResponse = BillingService::processShoppingCart(
                    $preOrder,
                    $creditCard->card_token,
                    $creditCard->expiration_year,
                    $creditCard->expiration_month,
                    request()->cvv,
                    $billingAddress,
                    $shippingAddress,
                    request()->redirect_3ds_url,
                    request()->currency,
                    UserSettings::getByUserId($user->id)->current_ip
                );

                # Tracking the orderhash from ipaytotal with pre-order
                if (isset($billingResponse->merchant_transaction_id)) {
                    $preOrder->orderhash = $billingResponse->merchant_transaction_id;
                    $preOrder->save();
                }

                if (!$billingResponse->success) {
                    if (isset($billingResponse->status)) {
                        if ($billingResponse->status == '3d_redirect') {
                            $billingResponse->redirect_url = $billingResponse->redirect_3ds_url;
                            ShoppingCart::destroyCart();
                            $response = ['error' => 0, 'msg' => "You will be redirected to our secure account confirmation screen. Please wait...", 'data' => $billingResponse];
                        } else {
                            $response = ['error' => 1, 'msg' => $billingResponse->response_text, 'data' => $billingResponse];
                        }
                    }

                    $response = ['error' => 1, 'msg' => $billingResponse->errors, 'data' => $billingResponse];
                } else {
                    # If success doesn't redirect
                    $billingResponse->redirect_url = '';

                    # If payment is success, import the pre order to order and go ahead with the process of each product
                    $service = new PreOrderService;
                    $migrated = $service->migratePreOrderToOrder($billingResponse->merchant_transaction_id);
                    
                    

                    ShoppingCart::destroyCart();
                    $response = ['error' => 0, 'msg' => $billingResponse->response_text, 'data' => $billingResponse];
                }
            }

            if ($request->payment_method == 'voucher') {

                $orderId = $this->createOrderAfterCheckout(null, null, $cart->voucher->id, $gift, $shipping_address_id);


                # Update order with coupon code.
                $order = Order::whereId($orderId)->update(
                    ['trasnactionid' => 'COUPON#' . $cart->voucher->code]
                );

                DiscountCoupon::markAsUsed($user->id, $cart->voucher->code);

                return response()->json(['error' => 0, 'msg' => 'Payment successful', 'data' => ['redirect_url' => '']]);
            }
        }

        return response()->json($response);
    }

    public function applyVoucher(Request $request)
    {
        $voucher = DiscountCoupon::isValid($request->voucher_code);

        if (!$voucher) {
            $this->setResponse([
                'error' => 1,
                'msg' => 'This voucher is invalid'
            ]);
            $this->setResponseCode(200);

            return $this->showResponse();
        }

        $userCart = ShoppingCart::where('user_id', Auth::user()->id)->first();

        $userCart->voucher_id = $voucher->id;
        $userCart->discount = $voucher->discount_amount;
        $userCart->save();

        $voucher->discount_amount = number_format($userCart->discount, 2);

        $userCart = ShoppingCart::getUserCart();
        $userCart->subtotal = number_format($userCart->subtotal, 2) ?? '0,00';

        $this->setResponseCode(200);
        $this->setResponse([
            'error' => 0,
            'msg' => 'Voucher is applied',
            'voucher' => $voucher,
            'shopping_cart' => $userCart
        ]);

        return $this->showResponse();
    }

    public function removeVoucher(Request $request)
    {
        $voucher = DiscountCoupon::whereId($request->get('voucher_id'))->first();

        if (!$voucher) {
            $this->setResponse(['error' => 1, 'msg' => 'Some problem to remove voucher, try again']);
            $this->setResponseCode(500);
            return $this->showResponse();
        }

        $userCart = ShoppingCart::where('user_id', Auth::user()->id)
            ->whereVoucherId($request->get('voucher_id'))
            ->first();

        if ($userCart) {
            $userCart->voucher_id = null;
            $userCart->discount = 0;
            $userCart->save();

            $userCart = ShoppingCart::getUserCart();
            $userCart->subtotal = number_format($userCart->subtotal, 2) ?? '0,00';

            $this->setResponse([
                'error' => 0,
                'msg' => 'Voucher removed successfully',
                'shopping_cart' => $userCart
            ]);
            $this->setResponseCode(200);

            return $this->showResponse();
        }
    }

    private function doPaymentByEwallet($gift, $shipping_address_id = null)
    {
        $cart = ShoppingCart::getUserCart();

        $total = $cart->subtotal;

        $voucher = $cart->voucher_id ? $cart->voucher->id : null;

        $user = Auth::user();

        if ($user->estimated_balance < $total) {
            return response()->json(['error' => 1, 'msg' => "Not enough e-wallet balance"]);
        }

        $ewallet_payment_method = UserPaymentMethod::findOrCreateEwalletPaymentMethod($user);

        $orderId = $this->createOrderAfterCheckout($ewallet_payment_method->id, null, $voucher, $gift, $shipping_address_id);

        EwalletTransaction::addPurchase(
            $user->id,
            EwalletTransaction::TYPE_SHOPPING_CART,
            -$total,
            $orderId
        );

        return response()->json(['error' => 0, 'msg' => 'Payment successful', 'data' => ['redirect_url' => '']]);
    }



    /*
     * New flow with uncrypt payment. Now we create a pre-order, after the payment is complete
     * we need to replicate this data to orders
     */
    private function createPreOrderAfterCheckout($cart, $shipping_address_id = null)
    {

        if (!is_null($cart)) {

            # Get the shipping address
            // $user = Auth::user();
            // $shippingAddress = $user->getUserShippingAddress($user);

            $items = $cart->items()->get();

            $bv = $cv = $qv = 0;

            $reactivateSeptember = false;
            if ($items) {
                foreach ($items as $item) {
                    $bv += $item->product->bv * $item->quantity;
                    $cv += $item->product->cv * $item->quantity;
                    $qv += $item->product->qv * $item->quantity;
                    $septemberSubscriptionIds = array(80, 81, 82, 83);
                    if (in_array($item->product->id, $septemberSubscriptionIds)) {
                        $reactivateSeptember = true;
                    }
                }
            }

            // Reactivate September
            if ($reactivateSeptember) {
                $createdDate = '2020-09-30 00:00:00';
                $isTSBOrder = true;
            } else {
                $createdDate = null;
                $isTSBOrder = false;
            }

            $preOrder = PreOrder::addNew(
                $cart->user->id,
                $cart->total,
                $cart->subtotal,
                $bv,
                $qv,
                $cv,
                null, //authorization
                null,
                $shipping_address_id,
                null,
                $createdDate,
                null,
                null,
                $order_refund_ref = null,
                $orderQC = 0,
                $orderAC = 0,
                $isTSBOrder = $isTSBOrder
            );

            foreach ($cart->items()->get() as $product) {

                $this->createPreOrderItemForOrder($preOrder, $product);
            }

            return PreOrder::findOrFail($preOrder);
        } else {

            return false;
        }
    }

    /*
     * New flow with uncrypt payment. Now we create a pre-order, after the payment is complete
     * we need to replicate this data to orders
     */
    private function createPreOrderItemForOrder($preOrderId, $product)
    {

        switch ($product->product->producttype) {
            case ProductType::TYPE_DONATION:
                PreOrder::createOrderForDonation($preOrderId, $product);
                break;
            case ProductType::TYPE_TICKET:
                PreOrder::createOrderForTicket($preOrderId, $product);
                break;
            case ProductType::TYPE_UPGRADE:
                PreOrder::createOrderForUpgrade($preOrderId, $product);
                break;
            case ProductType::TYPE_MEMBERSHIP:
                PreOrder::createOrderForMembership($preOrderId, $product);
                break;
            case ProductType::TYPE_SIMPLE_PRODUCT:
                PreOrder::createOrderForSimpleProduct($preOrderId, $product);
                break;
        }
    }

    private function createOrderAfterCheckout($payment_method_id, $authorization = null, $voucher_id = null, $gift = 0, $shipping_address_id = null)
    {
        # Get the shipping address
        //$user = Auth::user();
        // $shippingAddress = $shipping_address_id;

        $cart = ShoppingCart::getUserCart();
        $items = $cart->items()->get();

        $bv = $cv = $qv = 0;

        $reactivateSeptember = false;
        if ($items) {
            foreach ($items as $item) {
                $bv += $item->product->bv * $item->quantity;
                $cv += $item->product->cv * $item->quantity;
                $qv += $item->product->qv * $item->quantity;
                $septemberSubscriptionIds = array(80, 81, 82, 83);
                if (in_array($item->product->id, $septemberSubscriptionIds)) {
                    $reactivateSeptember = true;
                }
            }
        }

        // Reactivate September
        if ($reactivateSeptember) {
            $createdDate = '2020-09-30 00:00:00';
            $isTSBOrder = true;
        } else {
            $createdDate = null;
            $isTSBOrder = false;
        }

        $orderId = Order::addNew(
            $cart->user->id,
            $cart->total,
            $cart->subtotal, // Is total minus voucher discount
            $bv,
            $qv,
            $cv,
            $authorization,
            $payment_method_id,
            $shipping_address_id,
            null,
            $createdDate,
            $voucher_id,
            null,
            $order_refund_ref = null,
            $orderQC = 0,
            $orderAC = 0,
            $isTSBOrder = $isTSBOrder
        );

        $order = Order::whereId($orderId)->update(
            ['gift_box' => $gift]
        );

        Log::info("create Order NRO " . $orderId);

        foreach ($cart->items()->get() as $product) {

            $this->createOrderItemForOrder($orderId, $product);
        }

        ShoppingCart::destroyCart();


        $userExistInTree = User::checkIfUsersInTree($cart->user_id);
        info($userExistInTree);
        if ($userExistInTree) {
            $user = User::find($cart->user->id);
            $order = Order::find($orderId);
            info("dispaching job");
            DistributeVolumes::dispatch($user, $order);
        }

        return $orderId;
    }

    private function createOrderItemForOrder($orderId, $product)
    {
        switch ($product->product->producttype) {
            case ProductType::TYPE_DONATION:
                Order::createOrderForDonation($orderId, $product);
                break;
            case ProductType::TYPE_TICKET:
                Order::createOrderForTicket($orderId, $product);
                break;
            case ProductType::TYPE_UPGRADE:
                Order::createOrderForUpgrade($orderId, $product);
                break;
            case ProductType::TYPE_MEMBERSHIP:
                Order::createOrderForMembership($orderId, $product);
                break;
            case ProductType::TYPE_SIMPLE_PRODUCT:
                Order::createOrderForSimpleProduct($orderId, $product);
                break;
        }
    }


    private function validateCheckoutData($req)
    {
        $validator = Validator::make($req, [
            'payment_method_id' => 'required'
        ], [
            'payment_method_id.required' => 'Payment method cannot be empty'
        ]);
        $msg = "";
        $valid = 1;
        if ($validator->fails()) {
            $valid = 0;
            $messages = $validator->messages();
            foreach ($messages->all() as $m) {
                $msg .= $m;
            }
        }
        $res = array();
        $res["valid"] = $valid;
        $res["msg"] = $msg;
        return $res;
    }

    public function getOrderStatus($orderHash)
    {
        $invoice = Unicrypt::getOrderStatus($orderHash);

        if (!$invoice) {
            return response()->json(['error' => 1, 'msg' => 'Order Hash not found']);
        }

        return $invoice;
    }

    public function processAfterMerchantResponse(Request $request)
    {
        # Tracking data from ipaytotal response.
        Log::info('callback request from 3ds ipaytotal: ', $request->all());

        try {
            $migrated = false;

            $preOrderId = $request->get('pre_order_id');
            $merchantOrderId = $request->get('order_id');

            $preorder = PreOrder::find($preOrderId);
            if (empty($preorder->orderhash) && $preorder) {
                $preorder->orderhash = $merchantOrderId;
                $preorder->save();
            }

            # If payment is success, import the pre order to order and go ahead with the process of each product
            $service = new PreOrderService;
            $migrated = $service->migratePreOrderToOrderById($preOrderId);
            //$migrated = $service->migratePreOrderToOrder($merchantOrderId);

        } catch (\Throwable $th) {
            Log::error('error on ipaytotal proccess: ', $th->getMessage());
        }

        if ($migrated) {
            if (ShoppingCart::cartExists()) {
                ShoppingCart::destroyCart();
            }

            $response = ['error' => 0, 'status' => 'success', 'msg' => 'Purchase finished'];
        } else {
            $response = ['error' => 1, 'status' => 'failed', 'msg' => 'Pre Order not found'];
        }

        return $response;
    }

    public function getTotalItemsCart()
    {
        $shoppingCart = ShoppingCartProducts::getProductsQuantityCount();
        return response()->json(['total' => $shoppingCart]);
    }
}

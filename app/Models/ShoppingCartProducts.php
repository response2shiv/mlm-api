<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Models\ShoppingCart;
use DB;
use Auth;
use Log;

class ShoppingCartProducts extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'shopping_cart_products';


    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /*
    * Add product to user shopping cart
    */
    public static function addProductToCart($product, $qty)
    {
        $cart = ShoppingCart::getUserCart();

        if (!$cart) {
            $cart = ShoppingCart::createUserCart();
        }

        if ($cart) {
            //Check if product already exists on cart before adding
            $cart_product = self::where('shopping_cart_id', $cart->id)
                ->where("product_id", $product->id)->first();

            if ($cart_product) {
                $qty_updated = $cart_product->quantity + $qty;
                $cart_product->quantity = $qty_updated;
                $cart_product->sub_total = $cart_product->product_price * $qty_updated;
                $cart_product->save();
            } else {
                $cart_product = new self();
                $cart_product->shopping_cart_id = $cart->id;
                $cart_product->product_id       = $product->id;
                $cart_product->quantity         = $qty;
                $cart_product->product_price    = $product->price;
                $cart_product->sub_total        = $product->price * $qty;
                $cart_product->save();
            }
        }
    }

    /**
     * Add Dontate/Foundation Product in cart
     */
    public static function AddProductDonationToCart($product, $qty, $amount)
    {
        // dd($product,$qty,$amount);
        $cart = ShoppingCart::getUserCart();
        if (!$cart) {
            $cart = ShoppingCart::createUserCart();
        }

        if ($cart) {
            //Check if product already exists on cart before adding
            $cart_product = self::where('shopping_cart_id', $cart->id)
                ->where("product_id", $product->id)->first();

            if ($cart_product) {
                $qty_updated = $cart_product->quantity + $qty;
                $cart_product->quantity = $qty_updated;
                $cart_product->sub_total = $amount * $qty_updated;
                $cart_product->save();
            } else {
                $cart_product = new self();
                $cart_product->shopping_cart_id = $cart->id;
                $cart_product->product_id       = $product->id;
                $cart_product->quantity         = $qty;
                $cart_product->product_price    = $amount;
                $cart_product->sub_total        = $amount * $qty;
                $cart_product->save();
            }
        }
    }

    /*
    * Remove product from the user shopping cart
    */
    public static function removeProductFromCart($data)
    {
        $cart = ShoppingCart::getUserCart();
        $cart_product = self::where('shopping_cart_id', $cart->id)
            ->where("product_id", $data['product_id'])->first();
        if ($cart && $cart_product) {
            $cart_product->delete();
        }
    }

    /*
    * Get product count
    */
    public static function getProductsCount()
    {
        $cart = ShoppingCart::getUserCart();

        if (!$cart)
            return false;

        $cart_product = self::where('shopping_cart_id', $cart->id)->get()->count();
        return $cart_product;
    }

    /*
    * Get product quantity count
    */
    public static function getProductsQuantityCount()
    {
        $cart = ShoppingCart::getUserCart();

        if (!$cart)
            return 0;

        $cart_product = self::where('shopping_cart_id', $cart->id)->sum('quantity');
        return $cart_product;
    }

    /*
    * Get products list
    */
    public static function getProductsList()
    {
        $cart = ShoppingCart::getUserCart();

        if (!$cart)
            return false;

        $cart_products = self::where('shopping_cart_id', $cart->id)->get();
        return $cart_products;
    }

    /*
    * Check if product can be added based on allow_multiple_on_cart
    */
    public static function isProductAllowed($product_id)
    {
        $new_product = Product::where('id', $product_id)->first();
        $cart = ShoppingCart::getUserCart();

        //Cart is empty, its clear to be added
        if (self::getProductsQuantityCount() < 1) {
            return true;
        }

        # Get products from the cart
        $cart_products = $cart->items()->get();

        //Check if any product on the cart is set as 0 and if its a different product
        foreach ($cart_products as $cart_product) {

            if ($cart_product->product_id == $new_product->id && $cart_product->product->allow_quantity_change) {
                //Product is the same and its set to allow quantity change, we should just update quantity
            } else {
                //product is different and if any doesn't allow multiple on cart
                if ($cart_product->product->allow_multiple_on_cart == 0 || $new_product->allow_multiple_on_cart == 0) {
                    return false;
                }
            }
        }

        //There is another product on the cart, should not combine.
        if ($new_product->allow_multiple_on_cart == 0) {
            return true;
        }



        return true;
    }

    /*
    * Update product quantity on cart
    */
    public static function updateProductOnCart($product_id, $qty)
    {
        $cart = ShoppingCart::getUserCart();

        $cannotUpdate = ShoppingCart::checkTotalLimitOnShoppingCart($product_id, $qty);
        if (!$cannotUpdate) {
            $cart_product = self::where('shopping_cart_id', $cart->id)
                ->where("product_id", $product_id)->first();

            if ($cart && $cart_product) {

                $cart_product->quantity = $qty;
                $cart_product->sub_total = $cart_product->product_price * $qty;
                $cart_product->save();
            }
        }
        return false;
    }
}

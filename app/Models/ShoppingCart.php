<?php

namespace App\Models;

use App\Helpers\Util;
use App\Services\TaxjarService;
use Illuminate\Database\Eloquent\Model;
use Auth;
use DB;
use Illuminate\Support\Facades\Log;

class ShoppingCart extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'shopping_carts';

    protected $appends = [
        'total_calculated'
    ];

    /*
    * Check if cart exists
    */
    public static function cartExists()
    {
        $cart = self::where('user_id', Auth::user()->id)->first();
        if ($cart) {
            return true;
        } else {
            return false;
        }
    }

    /*
    * Getting an object of the user cart
    */
    public static function getUserCart()
    {
        $cart = self::where('user_id', Auth::user()->id)->first();  

        if (is_object($cart)) {
            if (!empty($cart->voucher_id)) {
                $voucher = DiscountCoupon::find($cart->voucher_id);
                if (is_object($voucher)){
                    $cart->voucher = $voucher;                    
                                    
                }else{
                    $cart->voucher_id = null;
                    $cart->discount = 0; 
                    $cart->save();                                    
                }           
            }
                        
            $cart->total = $cart->items()->sum('sub_total');
            $cart->items = $cart->items()->with('product.photos')->get();
            $cart->subtotal = $cart->subTotal;
            
            return $cart;
        }
        return false;
    }

    /*
    * Create shopping for user in case he doesn't have
    */
    public static function createUserCart()
    {
        $cart = new self();
        $cart->user_id = Auth::user()->id;
        $cart->is_card_selected = false;
        $cart->save();
        return $cart;
    }

    /*
    * Load shopping cart and all products on it
    */
    public function items()
    {
        return $this->hasMany(ShoppingCartProducts::class, 'shopping_cart_id');
    }

    /*
     * Load Payment Cart Method
     */
    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    /**
     * Load User Cart
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }


    public static function destroyCart()
    {
        $cart = ShoppingCart::getUserCart();

        foreach ($cart->items()->get() as $product) {
            Log::info("Delete item cart: " . $product->productname);
            DB::table("shopping_cart_products")->where('shopping_cart_id', $cart->id)->delete();
        }

        DB::table("shopping_carts")->where("id", $cart->id)->delete();
        Log::info("Shopping cart destoryed");
    }

    public function getSubTotalAttribute($value)
    {
        return ($this->discount >= $this->total) ? 0 : ($this->total - $this->discount);
    }

    public function getTotalCalculatedAttribute($value)
    {
        return ($this->discount >= $this->total) ? 0 : ($this->total - $this->discount);
    }

    public function voucher()
    {
        return $this->hasOne(DiscountCoupon::class, 'voucher_id');
    }

    public static function getShippingAddress($userId)
    {

        return Address::getShippingAddress($userId);
    }

    public static function getCartRate($cart)
    {

        $shippingAddress = self::getShippingAddress($cart->user_id);

        $result = TaxjarService::calculateRate(
            $shippingAddress->city,
            $shippingAddress->country,
            $shippingAddress->postalcode
        );

        return $result;
    }

    public static function getCartTaxes($cart)
    {

        $shippingAddress = self::getShippingAddress($cart->user_id);


        foreach ($cart->items as $item) {
            $line_itens[] = [
                'id' => $item->id,
                'quantity' => $item->quantity,
                'product_tax_code' => $item->product->tax_code,
                'unit_price' => $item->product_price,
                'discount' => 0
            ];
        }


        $result = TaxjarService::calculateTaxes(
            $shippingAddress->country,
            $shippingAddress->postalcode,
            $shippingAddress->stateprov,
            $shippingAddress->city,
            $shippingAddress->address1,
            $cart->subtotal,
            10,
            $line_itens
        );

        return $result;
    }

    public static function checkTotalLimitOnShoppingCart($product_id, $quantity, $DonationAmount = 0)
    {
        $userCart = self::getUserCart();
        $product  = Product::getById($product_id);
        $maxTotalAllowedOnCart = Util::getSetting('max-total-cart') ?: 1300;


        if (is_object($userCart)) {
            if ($product->id == 39) {
                #check new total for donation/foundation products
                $newSubTotal = $userCart->subtotal + $DonationAmount;
            } else {
                $newSubTotal = $userCart->subtotal + ($product->price * $quantity);
            }


            if ($newSubTotal > (int)$maxTotalAllowedOnCart) {
                return true;
            }     
        }
        return false;
    }
}

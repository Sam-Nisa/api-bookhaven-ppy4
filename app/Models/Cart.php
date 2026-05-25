<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = ['user_id'];

    /**
     * Get the cart items
     */
    public function items()
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Get the user that owns the cart
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the total number of items in the cart
     */
    public function getTotalItemsAttribute()
    {
        return $this->items->sum('quantity');
    }

    /**
     * Get the subtotal of the cart
     */
    public function getSubtotalAttribute()
    {
        return $this->items->sum(function ($item) {
            $book = $item->book;
            $price = floatval($book->price);
            $discountValue = floatval($book->discount_value ?? 0);
            $discountType = $book->discount_type;

            // Calculate discounted price
            $finalPrice = $price;
            if ($discountType === 'percentage' && $discountValue > 0) {
                $finalPrice = $price - ($price * $discountValue / 100);
            } elseif ($discountType === 'fixed' && $discountValue > 0) {
                $finalPrice = max(0, $price - $discountValue);
            }

            return $finalPrice * $item->quantity;
        });
    }

    /**
     * Check if cart is empty
     */
    public function isEmpty()
    {
        return $this->items->isEmpty();
    }

    /**
     * Clear all items from cart
     */
    public function clear()
    {
        $this->items()->delete();
    }
}

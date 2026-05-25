<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    use HasFactory;

    protected $fillable = ['cart_id', 'book_id', 'quantity'];

    protected $casts = [
        'quantity' => 'integer',
    ];

    /**
     * Get the book associated with the cart item
     */
    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * Get the cart that owns the cart item
     */
    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * Get the total price for this cart item (price * quantity)
     */
    public function getTotalPriceAttribute()
    {
        $book = $this->book;
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

        return $finalPrice * $this->quantity;
    }

    /**
     * Get the unit price after discount
     */
    public function getUnitPriceAttribute()
    {
        $book = $this->book;
        $price = floatval($book->price);
        $discountValue = floatval($book->discount_value ?? 0);
        $discountType = $book->discount_type;

        // Calculate discounted price
        if ($discountType === 'percentage' && $discountValue > 0) {
            return $price - ($price * $discountValue / 100);
        } elseif ($discountType === 'fixed' && $discountValue > 0) {
            return max(0, $price - $discountValue);
        }

        return $price;
    }
}

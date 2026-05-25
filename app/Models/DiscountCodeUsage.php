<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiscountCodeUsage extends Model
{
    use HasFactory;

    protected $fillable = [
        'discount_code_id',
        'user_id',
        'order_id',
        'discount_amount',
    ];

    protected $casts = [
        'discount_amount' => 'decimal:2',
    ];

    /**
     * Get the discount code that was used
     */
    public function discountCode()
    {
        return $this->belongsTo(DiscountCode::class);
    }

    /**
     * Get the user who used the discount code
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the order where this discount was applied
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}

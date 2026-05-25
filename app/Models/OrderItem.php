<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'book_id',
        'quantity',
        'price',
        'total',
        'commission_rate',
        'commission_amount',
        'owner_earnings',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'total' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'owner_earnings' => 'decimal:2',
    ];

    /**
     * Get the order that owns the order item
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the book associated with the order item
     */
    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * Calculate commission and owner earnings
     */
    public function calculateCommission($commissionRate = 10)
    {
        $this->commission_rate = $commissionRate;
        $this->commission_amount = round(($this->total * $commissionRate) / 100, 2);
        $this->owner_earnings = round($this->total - $this->commission_amount, 2);
        $this->save();
    }

    /**
     * Get the owner of this item (book author)
     */
    public function getOwnerAttribute()
    {
        return $this->book->author;
    }
}

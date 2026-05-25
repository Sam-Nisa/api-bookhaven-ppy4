<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'total_amount',
        'subtotal',
        'shipping_cost',
        'tax_amount',
        'status',
        'payment_method',
        'payment_qr_code',
        'payment_qr_md5',
        'payment_status',
        'payment_transaction_id',
        'shipping_address',
        'discount_code_id',
        'discount_code',
        'discount_amount',
        'qr_expires_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'shipping_address' => 'array',
        'qr_expires_at' => 'datetime',
    ];

    /**
     * Get the user that owns the order
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the order items
     */
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the total number of items in the order
     */
    public function getTotalItemsAttribute()
    {
        return $this->items->sum('quantity');
    }

    /**
     * Get the discount code used in this order
     */
    public function discountCode()
    {
        return $this->belongsTo(DiscountCode::class);
    }

    /**
     * Check if the QR code has expired
     */
    public function isQRExpired()
    {
        return $this->qr_expires_at?->isPast() ?? false;
    }

    /**
     * Check if the order is expired and should be deleted
     */
    public function shouldBeDeleted()
    {
        return $this->isQRExpired() && $this->payment_status === 'failed' && !$this->payment_transaction_id;
    }

    /**
     * Get total commission from this order
     */
    public function getTotalCommissionAttribute()
    {
        return $this->items->sum('commission_amount');
    }

    /**
     * Get total owner earnings from this order
     */
    public function getTotalOwnerEarningsAttribute()
    {
        return $this->items->sum('owner_earnings');
    }

    /**
     * Process commission for all items in this order
     */
    public function processCommissions($commissionRate = 10)
    {
        foreach ($this->items as $item) {
            $item->calculateCommission($commissionRate);
        }
    }

    /**
     * Distribute earnings to owners when order is completed
     */
    public function distributeEarnings()
    {
        if ($this->payment_status !== 'completed') {
            return;
        }

        foreach ($this->items as $item) {
            $owner = $item->book->author;
            
            // Skip if admin's own product
            if ($owner->role === 'admin') {
                continue;
            }

            // Get or create owner balance
            $balance = OwnerBalance::firstOrCreate(
                ['owner_id' => $owner->id],
                [
                    'available_balance' => 0,
                    'pending_balance' => 0,
                    'total_earned' => 0,
                    'total_withdrawn' => 0,
                ]
            );

            // Add to available balance (since payment is completed)
            $balance->increment('available_balance', $item->owner_earnings);
            $balance->increment('total_earned', $item->owner_earnings);
        }
    }
}
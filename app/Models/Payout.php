<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payout extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'amount',
        'status',
        'payment_method',
        'transaction_reference',
        'payment_proof',
        'notes',
        'processed_by',
        'requested_at',
        'processed_at',
        'is_deleted_by_admin',
        'is_deleted_by_author',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'requested_at' => 'datetime',
        'processed_at' => 'datetime',
        'is_deleted_by_admin' => 'boolean',
        'is_deleted_by_author' => 'boolean',
    ];

    /**
     * Get the owner (user) that owns this payout
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the admin who processed this payout
     */
    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Get the payout items
     */
    public function items()
    {
        return $this->hasMany(PayoutItem::class);
    }

    /**
     * Scope for pending payouts
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for completed payouts
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}

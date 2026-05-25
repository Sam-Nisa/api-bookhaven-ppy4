<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OwnerBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'available_balance',
        'pending_balance',
        'total_earned',
        'total_withdrawn',
    ];

    protected $casts = [
        'available_balance' => 'decimal:2',
        'pending_balance' => 'decimal:2',
        'total_earned' => 'decimal:2',
        'total_withdrawn' => 'decimal:2',
    ];

    /**
     * Get the owner (user) that owns this balance
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Add earnings to pending balance
     */
    public function addPendingEarnings($amount)
    {
        $this->increment('pending_balance', $amount);
        $this->increment('total_earned', $amount);
    }

    /**
     * Move pending balance to available when order is completed
     */
    public function confirmEarnings($amount)
    {
        $this->decrement('pending_balance', $amount);
        $this->increment('available_balance', $amount);
    }

    /**
     * Deduct from available balance when payout is made
     */
    public function deductPayout($amount)
    {
        if ($this->available_balance < $amount) {
            throw new \Exception('Insufficient balance');
        }
        
        $this->decrement('available_balance', $amount);
        $this->increment('total_withdrawn', $amount);
    }
}

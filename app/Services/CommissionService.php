<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OwnerBalance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommissionService
{
    /**
     * Default commission rate (percentage)
     */
    const DEFAULT_COMMISSION_RATE = 10;

    /**
     * Process commission for an order
     */
    public function processOrderCommission(Order $order, $commissionRate = null)
    {
        $rate = $commissionRate ?? self::DEFAULT_COMMISSION_RATE;

        DB::beginTransaction();
        try {
            foreach ($order->items as $item) {
                // Calculate commission for each item
                $item->commission_rate = $rate;
                $item->commission_amount = round(($item->total * $rate) / 100, 2);
                $item->owner_earnings = round($item->total - $item->commission_amount, 2);
                $item->save();

                Log::info("Commission calculated for order item", [
                    'order_item_id' => $item->id,
                    'total' => $item->total,
                    'commission' => $item->commission_amount,
                    'owner_earnings' => $item->owner_earnings,
                ]);
            }

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to process commission: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Distribute earnings to owners when payment is completed
     */
    public function distributeEarnings(Order $order)
    {
        if ($order->payment_status !== 'completed') {
            Log::warning("Cannot distribute earnings - order not completed", ['order_id' => $order->id]);
            return false;
        }

        DB::beginTransaction();
        try {
            foreach ($order->items as $item) {
                $owner = $item->book->author;
                
                if (!$owner) {
                    Log::warning("Skipping commission distribution - author not found", ['book_id' => $item->book_id]);
                    continue;
                }

                // Skip if admin's own product (admin keeps 100%)
                if ($owner->role === 'admin') {
                    Log::info("Skipping admin product", ['book_id' => $item->book_id]);
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

                // Add to available balance
                $balance->increment('available_balance', $item->owner_earnings);
                $balance->increment('total_earned', $item->owner_earnings);

                Log::info("Earnings distributed to owner", [
                    'owner_id' => $owner->id,
                    'amount' => $item->owner_earnings,
                    'new_balance' => $balance->fresh()->available_balance,
                ]);
            }

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to distribute earnings: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get commission summary for an order
     */
    public function getOrderCommissionSummary(Order $order)
    {
        $totalCommission = 0;
        $totalOwnerEarnings = 0;
        $breakdown = [];

        foreach ($order->items as $item) {
            $owner = $item->book->author;
            $ownerId = $owner->id;

            if (!isset($breakdown[$ownerId])) {
                $breakdown[$ownerId] = [
                    'owner_id' => $ownerId,
                    'owner_name' => $owner->name,
                    'owner_role' => $owner->role,
                    'total_sales' => 0,
                    'commission' => 0,
                    'earnings' => 0,
                    'items' => [],
                ];
            }

            $breakdown[$ownerId]['total_sales'] += $item->total;
            $breakdown[$ownerId]['commission'] += $item->commission_amount;
            $breakdown[$ownerId]['earnings'] += $item->owner_earnings;
            $breakdown[$ownerId]['items'][] = [
                'book_title' => $item->book->title,
                'quantity' => $item->quantity,
                'price' => $item->price,
                'total' => $item->total,
                'commission' => $item->commission_amount,
                'earnings' => $item->owner_earnings,
            ];

            $totalCommission += $item->commission_amount;
            $totalOwnerEarnings += $item->owner_earnings;
        }

        return [
            'order_id' => $order->id,
            'order_total' => $order->total_amount,
            'total_commission' => round($totalCommission, 2),
            'total_owner_earnings' => round($totalOwnerEarnings, 2),
            'breakdown' => array_values($breakdown),
        ];
    }

    /**
     * Get owner earnings summary
     */
    public function getOwnerEarningsSummary($ownerId)
    {
        $balance = OwnerBalance::where('owner_id', $ownerId)->first();

        if (!$balance) {
            return [
                'owner_id' => $ownerId,
                'available_balance' => 0,
                'pending_balance' => 0,
                'total_earned' => 0,
                'total_withdrawn' => 0,
            ];
        }

        return [
            'owner_id' => $balance->owner_id,
            'available_balance' => $balance->available_balance,
            'pending_balance' => $balance->pending_balance,
            'total_earned' => $balance->total_earned,
            'total_withdrawn' => $balance->total_withdrawn,
        ];
    }
}

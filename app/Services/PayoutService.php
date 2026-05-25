<?php

namespace App\Services;

use App\Models\Payout;
use App\Models\PayoutItem;
use App\Models\OwnerBalance;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Notifications\PayoutRequestedNotification;

class PayoutService
{
    /**
     * Request a payout for an owner
     */
    public function requestPayout($ownerId, $amount, $paymentMethod = null)
    {
        $balance = OwnerBalance::where('owner_id', $ownerId)->first();

        if (!$balance || $balance->available_balance < $amount) {
            throw new \Exception('Insufficient balance for payout');
        }

        DB::beginTransaction();
        try {
            // Create payout request
            $payout = Payout::create([
                'owner_id' => $ownerId,
                'amount' => $amount,
                'status' => 'pending',
                'payment_method' => $paymentMethod,
                'requested_at' => now(),
            ]);

            // Deduct from available balance
            $balance->deductPayout($amount);

            Log::info("Payout requested", [
                'payout_id' => $payout->id,
                'owner_id' => $ownerId,
                'amount' => $amount,
            ]);

            // Notify all admins
            $admins = User::where('role', 'admin')->get();
            $author = User::find($ownerId);
            foreach ($admins as $admin) {
                $admin->notify(new PayoutRequestedNotification($payout, $author));
            }

            DB::commit();
            return $payout;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to request payout: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process a payout (admin action)
     */
    public function processPayout($payoutId, $adminId, $status, $transactionReference = null, $notes = null)
    {
        $payout = Payout::findOrFail($payoutId);

        if ($payout->status !== 'pending') {
            throw new \Exception('Payout is not in pending status');
        }

        DB::beginTransaction();
        try {
            $payout->update([
                'status' => $status,
                'transaction_reference' => $transactionReference,
                'notes' => $notes,
                'processed_by' => $adminId,
                'processed_at' => now(),
            ]);

            // If failed or cancelled, return money to owner balance
            if (in_array($status, ['failed', 'cancelled'])) {
                $balance = OwnerBalance::where('owner_id', $payout->owner_id)->first();
                $balance->increment('available_balance', $payout->amount);
            }

            Log::info("Payout processed", [
                'payout_id' => $payoutId,
                'status' => $status,
                'admin_id' => $adminId,
            ]);

            DB::commit();
            return $payout;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to process payout: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get pending payouts for admin dashboard
     */
    public function getPendingPayouts()
    {
        return Payout::with(['owner'])
            ->where('status', 'pending')
            ->where('is_deleted_by_admin', false)
            ->orderBy('requested_at', 'asc')
            ->get();
    }

    /**
     * Get all payouts with filters
     */
    public function getPayouts($filters = [])
    {
        $query = Payout::with(['owner', 'processedBy'])
            ->where('is_deleted_by_admin', false);

        if (isset($filters['owner_id'])) {
            $query->where('owner_id', $filters['owner_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['from_date'])) {
            $query->where('requested_at', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $query->where('requested_at', '<=', $filters['to_date']);
        }

        return $query->orderBy('requested_at', 'desc')->paginate(20);
    }

    /**
     * Get payout statistics for admin dashboard
     */
    public function getPayoutStatistics()
    {
        $query = Payout::where('is_deleted_by_admin', false);
        
        return [
            'pending_count' => (clone $query)->where('status', 'pending')->count(),
            'pending_amount' => (clone $query)->where('status', 'pending')->sum('amount'),
            'processing_count' => (clone $query)->where('status', 'processing')->count(),
            'processing_amount' => (clone $query)->where('status', 'processing')->sum('amount'),
            'completed_today' => (clone $query)->where('status', 'completed')
                ->whereDate('processed_at', today())
                ->count(),
            'completed_today_amount' => (clone $query)->where('status', 'completed')
                ->whereDate('processed_at', today())
                ->sum('amount'),
            'total_completed' => (clone $query)->where('status', 'completed')->count(),
            'total_completed_amount' => (clone $query)->where('status', 'completed')->sum('amount'),
        ];
    }

    /**
     * Get owner payout history
     */
    public function getOwnerPayoutHistory($ownerId)
    {
        return Payout::where('owner_id', $ownerId)
            ->where('is_deleted_by_author', false)
            ->orderBy('requested_at', 'desc')
            ->get();
    }
}

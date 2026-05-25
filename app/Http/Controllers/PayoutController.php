<?php

namespace App\Http\Controllers;

use App\Services\PayoutService;
use App\Services\CommissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PayoutController extends Controller
{
    protected $payoutService;
    protected $commissionService;

    public function __construct(PayoutService $payoutService, CommissionService $commissionService)
    {
        $this->payoutService = $payoutService;
        $this->commissionService = $commissionService;
    }

    /**
     * Get owner balance and earnings summary
     */
    public function getBalance(Request $request)
    {
        $user = Auth::user();
        
        // Only authors/owners can view their balance
        if ($user->role !== 'author' && $user->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $summary = $this->commissionService->getOwnerEarningsSummary($user->id);
        
        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    /**
     * Request a payout (Owner/Author action)
     */
    public function requestPayout(Request $request)
    {
        $user = Auth::user();

        if ($user->role !== 'author' && $user->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'nullable|in:bank_transfer,aba,wing,bakong,other',
        ]);

        try {
            $payout = $this->payoutService->requestPayout(
                $user->id,
                $request->amount,
                $request->payment_method
            );

            return response()->json([
                'success' => true,
                'message' => 'Payout request submitted successfully',
                'data' => $payout,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get payout history for logged-in owner
     */
    public function getMyPayouts(Request $request)
    {
        $user = Auth::user();

        if ($user->role !== 'author' && $user->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $payouts = $this->payoutService->getOwnerPayoutHistory($user->id);

        return response()->json([
            'success' => true,
            'data' => $payouts,
        ]);
    }

    /**
     * Delete a payout record (Owner/Author action)
     */
    public function deleteMyPayout(Request $request, $payoutId)
    {
        $user = Auth::user();

        if ($user->role !== 'author' && $user->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $payout = \App\Models\Payout::where('id', $payoutId)
                ->where('owner_id', $user->id)
                ->firstOrFail();

            if (!in_array($payout->status, ['completed', 'cancelled'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Can only delete completed or cancelled payouts',
                ], 400);
            }

            Log::info('Author deleting payout', [
                'payout_id' => $payout->id,
                'author_id' => $payout->owner_id,
                'amount' => $payout->amount,
                'status' => $payout->status,
            ]);

            // Set the author deleted flag
            $payout->is_deleted_by_author = true;
            $payout->save();

            // If both admin and author deleted, we can hard delete
            if ($payout->is_deleted_by_admin && $payout->is_deleted_by_author) {
                $payout->delete();
            }

            return response()->json([
                'success' => true,
                'message' => 'Payout record deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete author payout: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all pending payouts (Admin only)
     */
    public function getPendingPayouts(Request $request)
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized - Admin only'], 403);
        }

        $payouts = $this->payoutService->getPendingPayouts();

        return response()->json([
            'success' => true,
            'data' => $payouts,
        ]);
    }

    /**
     * Get all payouts with filters (Admin only)
     */
    public function getAllPayouts(Request $request)
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized - Admin only'], 403);
        }

        $filters = $request->only(['owner_id', 'status', 'from_date', 'to_date']);
        $payouts = $this->payoutService->getPayouts($filters);

        return response()->json([
            'success' => true,
            'data' => $payouts,
        ]);
    }

    /**
     * Process a payout (Admin only)
     */
    public function processPayout(Request $request, $payoutId)
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized - Admin only'], 403);
        }

        $request->validate([
            'status' => 'required|in:processing,completed,failed,cancelled',
            'transaction_reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        try {
            $payout = $this->payoutService->processPayout(
                $payoutId,
                $user->id,
                $request->status,
                $request->transaction_reference,
                $request->notes
            );

            return response()->json([
                'success' => true,
                'message' => 'Payout processed successfully',
                'data' => $payout,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get payout statistics (Admin only)
     */
    public function getStatistics(Request $request)
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized - Admin only'], 403);
        }

        $stats = $this->payoutService->getPayoutStatistics();

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}

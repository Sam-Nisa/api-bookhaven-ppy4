<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\OwnerBalance;
use App\Models\Payout;
use App\Services\PayoutService;
use App\Services\BakongPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Notification;
use App\Services\ImageKitService;
use App\Notifications\AdminPaymentToAuthorNotification;

class AdminPayoutController extends Controller
{
    protected $payoutService;
    protected $bakongService;
    protected $imageKit;

    public function __construct(PayoutService $payoutService, BakongPaymentService $bakongService, ImageKitService $imageKit)
    {
        $this->payoutService = $payoutService;
        $this->bakongService = $bakongService;
        $this->imageKit = $imageKit;
    }

    /**
     * Get list of all authors with their earnings (for admin to see who needs payment)
     */
    public function getAuthorsWithEarnings(Request $request)
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized - Admin only'], 403);
        }

        try {
            // Get all authors with their balances
            $authors = User::where('role', 'author')
                ->with(['ownerBalance', 'books'])
                ->get()
                ->map(function ($author) {
                    $balance = $author->ownerBalance;
                    
                    return [
                        'author_id' => $author->id,
                        'author_name' => $author->name,
                        'author_email' => $author->email,
                        'bank_name' => $author->bank_name,
                        'bank_account_number' => $author->bank_account_number,
                        'bank_account_name' => $author->bank_account_name,
                        'bakong_account_id' => $author->bakong_account_id,
                        'payment_method' => $author->payment_method,
                        'available_balance' => $balance ? $balance->available_balance : 0,
                        'pending_balance' => $balance ? $balance->pending_balance : 0,
                        'total_earned' => $balance ? $balance->total_earned : 0,
                        'total_withdrawn' => $balance ? $balance->total_withdrawn : 0,
                        'books_count' => $author->books->count(),
                        'has_payment_info' => !empty($author->bank_account_number) || !empty($author->bakong_account_id),
                    ];
                })
                ->filter(function ($author) {
                    // Only show authors who have earnings or have earned before
                    return $author['total_earned'] > 0 || $author['available_balance'] > 0;
                })
                ->values();

            return response()->json([
                'success' => true,
                'data' => $authors,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get authors with earnings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Admin initiates payout to an author (creates payout record)
     */
    public function initiatePayout(Request $request)
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized - Admin only'], 403);
        }

        $request->validate([
            'author_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:bank_transfer,aba,wing,bakong,other',
            'notes' => 'nullable|string',
        ]);

        try {
            $author = User::findOrFail($request->author_id);
            
            if ($author->role !== 'author') {
                return response()->json([
                    'success' => false,
                    'error' => 'Selected user is not an author',
                ], 400);
            }

            $balance = OwnerBalance::where('owner_id', $author->id)->first();
            
            if (!$balance || $balance->available_balance < $request->amount) {
                return response()->json([
                    'success' => false,
                    'error' => 'Insufficient balance',
                ], 400);
            }

            // Create payout record with status "processing" (admin is about to pay)
            $payout = Payout::create([
                'owner_id' => $author->id,
                'amount' => $request->amount,
                'status' => 'processing',
                'payment_method' => $request->payment_method,
                'notes' => $request->notes,
                'processed_by' => $user->id,
                'requested_at' => now(),
            ]);

            // Deduct from available balance
            $balance->decrement('available_balance', $request->amount);

            Log::info('Admin initiated payout', [
                'payout_id' => $payout->id,
                'author_id' => $author->id,
                'amount' => $request->amount,
                'admin_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payout initiated. Please complete the payment and mark as completed.',
                'data' => $payout->load('owner'),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to initiate payout: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Admin confirms payout is completed (after real payment is made)
     */
    public function confirmPayout(Request $request, $payoutId)
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized - Admin only'], 403);
        }

        $request->validate([
            'transaction_reference' => 'required|string|max:255',
            'notes' => 'nullable|string',
            'payment_proof' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
        ]);

        try {
            $payout = Payout::findOrFail($payoutId);

            if ($payout->status === 'completed') {
                return response()->json([
                    'success' => false,
                    'error' => 'Payout already completed',
                ], 400);
            }

            $updateData = [
                'status' => 'completed',
                'transaction_reference' => $request->transaction_reference,
                'notes' => $request->notes ? $payout->notes . "\n" . $request->notes : $payout->notes,
                'processed_by' => $user->id,
                'processed_at' => now(),
            ];

            // Handle payment proof image upload
            if ($request->hasFile('payment_proof')) {
                $file = $request->file('payment_proof');
                $upload = $this->imageKit->upload(
                    $file->getPathname(),
                    time().'_'.$file->getClientOriginalName(),
                    '/payouts/proofs'
                );
                $updateData['payment_proof'] = $upload->result->url;
            }

            $payout->update($updateData);

            // Update total withdrawn
            $balance = OwnerBalance::where('owner_id', $payout->owner_id)->first();
            if ($balance) {
                $balance->increment('total_withdrawn', $payout->amount);
            }

            // Notify Author
            try {
                $author = User::find($payout->owner_id);
                if ($author) {
                    $author->notify(new AdminPaymentToAuthorNotification($payout));
                }
            } catch (\Exception $e) {
                Log::error('Failed to send AdminPaymentToAuthorNotification: ' . $e->getMessage());
            }

            Log::info('Admin confirmed payout', [
                'payout_id' => $payout->id,
                'author_id' => $payout->owner_id,
                'amount' => $payout->amount,
                'transaction_reference' => $request->transaction_reference,
                'has_proof' => $request->hasFile('payment_proof'),
                'admin_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payout confirmed successfully',
                'data' => $payout->load('owner', 'processedBy'),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to confirm payout: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all payout history (for admin)
     */
    public function getPayoutHistory(Request $request)
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized - Admin only'], 403);
        }

        try {
            $query = Payout::with(['owner', 'processedBy'])
                ->where('is_deleted_by_admin', false);

            // Filter by status
            if ($request->status) {
                $query->where('status', $request->status);
            }

            // Filter by author
            if ($request->author_id) {
                $query->where('owner_id', $request->author_id);
            }

            // Filter by date range
            if ($request->from_date) {
                $query->whereDate('requested_at', '>=', $request->from_date);
            }
            if ($request->to_date) {
                $query->whereDate('requested_at', '<=', $request->to_date);
            }

            $payouts = $query->orderBy('requested_at', 'desc')->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $payouts,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get payout history: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel a payout (if not yet completed)
     */
    public function cancelPayout(Request $request, $payoutId)
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized - Admin only'], 403);
        }

        try {
            $payout = Payout::findOrFail($payoutId);

            if ($payout->status === 'completed') {
                return response()->json([
                    'success' => false,
                    'error' => 'Cannot cancel completed payout',
                ], 400);
            }

            // Return money to author's balance
            $balance = OwnerBalance::where('owner_id', $payout->owner_id)->first();
            if ($balance) {
                $balance->increment('available_balance', $payout->amount);
            }

            $payout->update([
                'status' => 'cancelled',
                'notes' => $payout->notes . "\nCancelled by admin: " . ($request->reason ?? 'No reason provided'),
                'processed_by' => $user->id,
                'processed_at' => now(),
            ]);

            Log::info('Admin cancelled payout', [
                'payout_id' => $payout->id,
                'author_id' => $payout->owner_id,
                'amount' => $payout->amount,
                'admin_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payout cancelled and balance restored',
                'data' => $payout,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to cancel payout: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a payout record (Admin only - for completed payouts)
     */
    public function deletePayout(Request $request, $payoutId)
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized - Admin only'], 403);
        }

        try {
            $payout = Payout::findOrFail($payoutId);

            // Only allow deleting completed or cancelled payouts
            if (!in_array($payout->status, ['completed', 'cancelled'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Can only delete completed or cancelled payouts',
                ], 400);
            }

            Log::info('Admin deleting payout', [
                'payout_id' => $payout->id,
                'author_id' => $payout->owner_id,
                'amount' => $payout->amount,
                'status' => $payout->status,
                'admin_id' => $user->id,
            ]);

            // Set the admin deleted flag
            $payout->is_deleted_by_admin = true;
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
            Log::error('Failed to delete payout: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate Bakong QR code for author payout
     */
    public function generatePayoutQR(Request $request, $authorId)
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized - Admin only'], 403);
        }

        $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        try {
            $author = User::findOrFail($authorId);

            if ($author->role !== 'author') {
                return response()->json([
                    'success' => false,
                    'error' => 'Selected user is not an author',
                ], 400);
            }

            if (empty($author->bakong_account_id)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Author has not set up Bakong account',
                ], 400);
            }

            // Create a temporary service instance with author's configuration
            $originalConfig = [
                'services.bakong.account_id' => config('services.bakong.account_id'),
                'services.bakong.merchant_name' => config('services.bakong.merchant_name'),
                'services.bakong.merchant_city' => config('services.bakong.merchant_city'),
                'services.bakong.merchant_id' => config('services.bakong.merchant_id'),
                'services.bakong.acquiring_bank' => config('services.bakong.acquiring_bank'),
                'services.bakong.mobile_number' => config('services.bakong.mobile_number'),
            ];

            // Temporarily set account configuration to Author's
            config([
                'services.bakong.account_id' => $author->bakong_account_id,
                'services.bakong.merchant_name' => $author->bakong_merchant_name ?? ($author->name . ' Store'),
                'services.bakong.merchant_city' => $author->bakong_merchant_city ?? 'Phnom Penh',
                'services.bakong.merchant_id' => $author->bakong_merchant_id ?? '',
                'services.bakong.acquiring_bank' => $author->bakong_acquiring_bank ?? 'Bakong',
                'services.bakong.mobile_number' => $author->bakong_mobile_number ?? '',
            ]);

            // Create new service instance with author's config
            $authorBakongService = new BakongPaymentService();

            // Generate QR code using author's Bakong account
            $qrResult = $authorBakongService->generateQRCode(
                $request->amount,
                'USD',
                'PAYOUT-' . $authorId . '-' . time(),
                'Payout to ' . $author->name
            );

            // Restore original config
            config($originalConfig);

            if (!$qrResult['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $qrResult['message'] ?? 'Failed to generate QR code',
                ], 400);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'qr_string' => $qrResult['qr_string'],
                    'md5' => $qrResult['md5'],
                    'amount' => $qrResult['amount'],
                    'currency' => $qrResult['currency'],
                    'author_bakong_id' => $author->bakong_account_id,
                    'author_name' => $author->name,
                ],
            ]);
        } catch (\Exception $e) {
            // Ensure config is restored even on exception
            if (isset($originalConfig)) {
                config($originalConfig);
            }
            
            Log::error('Failed to generate payout QR: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

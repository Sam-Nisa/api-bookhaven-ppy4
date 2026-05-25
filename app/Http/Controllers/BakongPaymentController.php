<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\BakongPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class BakongPaymentController extends Controller
{
    protected $bakongService;

    public function __construct(BakongPaymentService $bakongService)
    {
        $this->bakongService = $bakongService;
    }

    /**
     * Generate Bakong QR code for an order using author's account or admin account for discount codes
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateQRCode(Request $request)
    {
        try {
            $validated = $request->validate([
                'order_id' => 'required|string', // Now accepts pending order IDs
                'currency' => 'sometimes|in:USD,KHR',
            ]);

            $user = Auth::user();
            $orderId = $validated['order_id'];
            
            // Check if this is a pending order (cached)
            if (str_starts_with($orderId, 'pending_')) {
                $orderData = Cache::get("pending_order_{$orderId}");
                
                if (!$orderData || $orderData['user_id'] !== $user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Pending order not found or expired'
                    ], 404);
                }
                
                $totalAmount = $orderData['total_amount'];
                $orderItems = $orderData['order_items'];
                $hasDiscountCode = !empty($orderData['discount_code']) && $orderData['discount_code'] !== null;
            } else {
                // Regular order lookup (for backward compatibility)
                $order = Order::with('items.book.author')->where('id', $orderId)
                             ->where('user_id', $user->id)
                             ->first();

                if (!$order) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Order not found'
                    ], 404);
                }
                
                $totalAmount = $order->total_amount;
                $orderItems = $order->items->map(function($item) {
                    return [
                        'book_id' => $item->book_id,
                        'book' => $item->book
                    ];
                })->toArray();
                $hasDiscountCode = !empty($order->discount_code) && $order->discount_code !== null;
            }

            // Determine which account to use
            // ALWAYS use admin account for all payments (Admin will handle 10% commission later)
            $bakongAccount = $this->getAdminBakongAccount();
            $accountType = 'admin';
            
            if ($hasDiscountCode) {
                $reason = 'discount_code_applied';
            } else {
                // Check if it's a multi-vendor order or single author order to set the reason
                $authorIds = [];
                foreach ($orderItems as $item) {
                    $bookId = $item['book_id'] ?? null;
                    if ($bookId) {
                        $book = \App\Models\Book::find($bookId);
                        if ($book && $book->author_id) {
                            $authorIds[] = $book->author_id;
                        }
                    }
                }
                $uniqueAuthors = array_unique($authorIds);
                
                if (count($uniqueAuthors) > 1) {
                    $reason = 'multi_vendor_order_admin_payment';
                } else {
                    $reason = 'author_book_admin_payment';
                }
            }
            
            if (!$bakongAccount) {
                $message = 'Bakong account not configured. Please contact support.';
                    
                return response()->json([
                    'success' => false,
                    'message' => $message
                ], 400);
            }

            // Ensure amount is properly rounded to avoid floating-point precision issues
            $totalAmount = round((float) $totalAmount, 2);
            
            // Validate amount is positive and reasonable
            if ($totalAmount <= 0) {
                Log::error('Invalid order amount', [
                    'order_id' => $orderId,
                    'amount' => $totalAmount,
                    'original_amount' => $orderData['total_amount'] ?? 'N/A'
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid order amount'
                ], 400);
            }

            // Reuse existing QR data if still valid (avoid immediate expiration and allow retry)
            $existingQrData = Cache::get("qr_data_{$orderId}");
            if ($existingQrData && now()->lt($existingQrData['expires_at'])) {
                return response()->json([
                    'success' => true,
                    'message' => 'QR code already generated, still valid',
                    'data' => [
                        'qr_string' => $existingQrData['qr_string'],
                        'md5' => $existingQrData['md5'],
                        'amount' => $existingQrData['amount'],
                        'currency' => $existingQrData['currency'],
                        'order_id' => $orderId,
                        'expires_at' => $existingQrData['expires_at']->toISOString(),
                        'bill_number' => $existingQrData['bill_number'],
                        'merchant_name' => $existingQrData['merchant_name'],
                        'author_account' => $existingQrData['account_id'],
                        'account_type' => $existingQrData['account_type'],
                        'reason' => $existingQrData['reason']
                    ]
                ]);
            }

            // Generate QR code using the appropriate account
            $currency = $validated['currency'] ?? 'USD';
            $billNumber = 'ORD-' . str_replace('pending_', '', $orderId);
            $storeLabel = $bakongAccount['merchant_name'];

            $result = $this->generateQRWithSpecificAccount(
                $bakongAccount,
                $totalAmount,
                $currency,
                $billNumber,
                $storeLabel
            );

            if ($result['success']) {
                // Set QR expiration to 15 minutes from now
                $expiresAt = now()->addMinutes(15);
                
                // Store QR info in cache with the pending order ID
                Cache::put("qr_data_{$orderId}", [
                    'qr_string' => $result['qr_string'],
                    'md5' => $result['md5'],
                    'expires_at' => $expiresAt,
                    'amount' => $result['amount'],
                    'currency' => $result['currency'],
                    'account_id' => $bakongAccount['account_id'],
                    'account_type' => $accountType,
                    'reason' => $reason,
                ], $expiresAt);

                return response()->json([
                    'success' => true,
                    'message' => 'QR code generated successfully',
                    'data' => [
                        'qr_string' => $result['qr_string'],
                        'md5' => $result['md5'],
                        'amount' => $result['amount'],
                        'currency' => $result['currency'],
                        'order_id' => $orderId,
                        'expires_at' => $expiresAt->toISOString(),
                        'bill_number' => $billNumber,
                        'merchant_name' => $bakongAccount['merchant_name'],
                        'author_account' => $bakongAccount['account_id'],
                        'account_type' => $accountType,
                        'reason' => $reason
                    ]
                ]);
            }

            // Log the error for debugging
            Log::error('Bakong QR Generation Failed', [
                'result' => $result,
                'order_id' => $orderId,
                'amount' => $totalAmount,
                'account_id' => $bakongAccount['account_id'],
                'account_type' => $accountType
            ]);

            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to generate QR code',
                'error' => $result['error'] ?? 'Unknown error',
                'debug' => config('app.debug') ? $result : null
            ], 500);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('QR Generation Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while generating QR code',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get admin's Bakong account from .env configuration
     */
    private function getAdminBakongAccount()
    {
        try {
            $accountId = config('services.bakong.account_id');
            $merchantName = config('services.bakong.merchant_name');
            
            if (!$accountId || !$merchantName) {
                Log::warning('Admin Bakong account not configured in .env');
                return null;
            }
            
            return [
                'account_id' => $accountId,
                'merchant_name' => $merchantName,
                'merchant_city' => config('services.bakong.merchant_city'),
                'merchant_id' => config('services.bakong.merchant_id'),
                'acquiring_bank' => config('services.bakong.acquiring_bank'),
                'mobile_number' => config('services.bakong.mobile_number'),
            ];
            
        } catch (\Exception $e) {
            Log::error('Error getting admin Bakong account: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get author's Bakong account for the order
     */
    private function getAuthorBakongAccount($orderItems, $orderId)
    {
        try {
            // First, check if this is a multi-vendor order
            $authorIds = [];
            foreach ($orderItems as $item) {
                $bookId = $item['book_id'] ?? null;
                if ($bookId) {
                    $book = \App\Models\Book::find($bookId);
                    if ($book && $book->author_id) {
                        $authorIds[] = $book->author_id;
                    }
                }
            }
            
            $uniqueAuthors = array_unique($authorIds);
            
            // If multiple authors detected, use admin account
            if (count($uniqueAuthors) > 1) {
                Log::info('Multi-vendor order detected, using admin account', [
                    'authors' => $uniqueAuthors,
                    'author_count' => count($uniqueAuthors),
                    'order_id' => $orderId
                ]);
                return $this->getAdminBakongAccount();
            }
            
            // Single author order - proceed with existing logic
            $firstBookId = null;
            
            if (str_starts_with($orderId, 'pending_')) {
                // Get book from order items
                $firstBookId = $orderItems[0]['book_id'] ?? null;
            } else {
                // Get book from order items
                $firstBookId = $orderItems[0]['book_id'] ?? null;
            }
            
            if (!$firstBookId) {
                return null;
            }
            
            $book = \App\Models\Book::with('author')->find($firstBookId);
            if (!$book) {
                return null;
            }
            
            // Check if book has an author and if the author is not an admin
            if (!$book->author) {
                Log::warning('Book has no author assigned', [
                    'book_id' => $book->id,
                    'book_title' => $book->title
                ]);
                return null;
            }
            
            // If the book's author is an admin, use admin account from .env
            if ($book->author->role === 'admin') {
                Log::info('Book created by admin, using admin Bakong account', [
                    'book_id' => $book->id,
                    'author_id' => $book->author->id,
                    'author_role' => $book->author->role
                ]);
                return $this->getAdminBakongAccount();
            }
            
            $author = $book->author;
            
            // Check if author has verified Bakong account
            if (!$author->bakong_account_verified || !$author->bakong_account_id) {
                Log::warning('Author Bakong account not verified', [
                    'author_id' => $author->id,
                    'author_name' => $author->name,
                    'verified' => $author->bakong_account_verified
                ]);
                return null;
            }
            
            return [
                'account_id' => $author->bakong_account_id,
                'merchant_name' => $author->bakong_merchant_name,
                'merchant_city' => $author->bakong_merchant_city,
                'merchant_id' => $author->bakong_merchant_id,
                'acquiring_bank' => $author->bakong_acquiring_bank,
                'mobile_number' => $author->bakong_mobile_number,
            ];
            
        } catch (\Exception $e) {
            Log::error('Error getting author Bakong account: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate QR code using specific account (admin or author)
     */
    private function generateQRWithSpecificAccount($account, $amount, $currency, $billNumber, $storeLabel)
    {
        try {
            // Create a temporary service instance with author's configuration
            $originalConfig = [
                'services.bakong.account_id' => config('services.bakong.account_id'),
                'services.bakong.merchant_name' => config('services.bakong.merchant_name'),
                'services.bakong.merchant_city' => config('services.bakong.merchant_city'),
                'services.bakong.merchant_id' => config('services.bakong.merchant_id'),
                'services.bakong.acquiring_bank' => config('services.bakong.acquiring_bank'),
                'services.bakong.mobile_number' => config('services.bakong.mobile_number'),
            ];

            // Temporarily set account configuration
            config([
                'services.bakong.account_id' => $account['account_id'],
                'services.bakong.merchant_name' => $account['merchant_name'],
                'services.bakong.merchant_city' => $account['merchant_city'],
                'services.bakong.merchant_id' => $account['merchant_id'],
                'services.bakong.acquiring_bank' => $account['acquiring_bank'],
                'services.bakong.mobile_number' => $account['mobile_number'],
            ]);

            // Create new service instance with account config
            $bakongService = new BakongPaymentService();
            
            $result = $bakongService->generateQRCode(
                $amount,
                $currency,
                $billNumber,
                $storeLabel
            );

            // Restore original configuration
            config($originalConfig);

            return $result;

        } catch (\Exception $e) {
            // Restore original configuration on error
            if (isset($originalConfig)) {
                config($originalConfig);
            }
            
            Log::error('QR Generation Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to generate QR with account',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check payment status for an order
     * 
     * @param Request $request
     * @param int $orderId
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkPaymentStatus(Request $request, $orderId)
    {
        try {
            $user = Auth::user();
            
            // Check if this is a pending order
            if (str_starts_with($orderId, 'pending_')) {
                $orderData = Cache::get("pending_order_{$orderId}");
                $qrData = Cache::get("qr_data_{$orderId}");
                
                if (!$orderData || $orderData['user_id'] !== $user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Pending order not found or expired',
                        'expired' => true
                    ], 410);
                }
                
                if (!$qrData) {
                    return response()->json([
                        'success' => false,
                        'message' => 'QR code not found or expired',
                        'expired' => true
                    ], 410);
                }
                
                // Check if QR has expired
                if (now()->isAfter($qrData['expires_at'])) {
                    // Keep pending order data available to allow re-generation, but remove expired QR payload
                    Cache::forget("qr_data_{$orderId}");
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'QR code has expired. Please refresh to generate a new QR code.',
                        'expired' => true,
                        'can_refresh' => true
                    ], 410);
                }
                
                // Check transaction status using MD5
                $result = $this->bakongService->checkTransactionByMD5($qrData['md5'], false);
                
                if ($result['success'] && isset($result['transaction'])) {
                    $transaction = $result['transaction'];
                    
                    if (isset($transaction['status']) && $transaction['status'] === 'COMPLETED') {
                        Log::info('Payment COMPLETED! Creating order from pending data', [
                            'pending_order_id' => $orderId,
                            'transaction_id' => $transaction['transactionId'] ?? null,
                            'transaction' => $transaction
                        ]);
                        
                        // Create the actual order
                        $orderController = new OrderController();
                        $order = $orderController->createFromPendingOrder($orderId, $transaction['transactionId'] ?? null);
                        
                        Log::info('Order created successfully', [
                            'order_id' => $order->id,
                            'order_status' => $order->status,
                            'payment_status' => $order->payment_status
                        ]);
                        
                        return response()->json([
                            'success' => true,
                            'message' => 'Payment completed successfully',
                            'data' => [
                                'order_id' => $order->id,
                                'order_status' => 'paid',
                                'payment_status' => 'completed',
                                'transaction' => $transaction
                            ]
                        ]);
                    }
                }
                
                // Payment not completed yet
                return response()->json([
                    'success' => true,
                    'message' => 'Payment not completed yet',
                    'data' => [
                        'payment_found' => false,
                        'expires_at' => $qrData['expires_at']->toISOString(),
                        'is_expired' => false
                    ]
                ], 200);
            }
            
            // Handle regular orders (for backward compatibility)
            $order = Order::where('id', $orderId)
                         ->where('user_id', $user->id)
                         ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            // If payment is already completed, return success
            if ($order->payment_status === 'completed' && $order->payment_transaction_id) {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment already completed',
                    'data' => [
                        'order_status' => 'paid',
                        'payment_status' => 'completed',
                        'transaction_id' => $order->payment_transaction_id
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Order found but payment not completed'
            ], 400);

        } catch (\Exception $e) {
            Log::error('Payment Status Check Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while checking payment status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify Bakong account
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyAccount(Request $request)
    {
        try {
            $validated = $request->validate([
                'account_id' => 'required|string'
            ]);

            $exists = $this->bakongService->checkAccountExists($validated['account_id']);

            return response()->json([
                'success' => true,
                'exists' => $exists,
                'message' => $exists ? 'Account exists' : 'Account not found'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while verifying account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Decode QR code
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function decodeQRCode(Request $request)
    {
        try {
            $validated = $request->validate([
                'qr_string' => 'required|string'
            ]);

            $result = $this->bakongService->decodeQRCode($validated['qr_string']);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'data' => $result['data']
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while decoding QR code',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Renew Bakong API token (Admin only)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function renewToken(Request $request)
    {
        try {
            // Check if user is admin
            $user = Auth::user();
            if ($user->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $validated = $request->validate([
                'email' => 'required|email'
            ]);

            $result = $this->bakongService->renewToken($validated['email']);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'token' => $result['token']
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while renewing token',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
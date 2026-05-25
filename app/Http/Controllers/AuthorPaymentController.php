<?php

namespace App\Http\Controllers;

use App\Services\BakongPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

class AuthorPaymentController extends Controller
{
    protected $bakongService;

    public function __construct(BakongPaymentService $bakongService)
    {
        $this->bakongService = $bakongService;
    }

    /**
     * Get author's payment information
     */
    public function getPaymentInfo(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user || $user->role !== 'author') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Author access required.'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    // Bank information
                    'bank_name' => $user->bank_name,
                    'bank_account_number' => $user->bank_account_number,
                    'bank_account_name' => $user->bank_account_name,
                    'bank_branch' => $user->bank_branch,
                    'payment_method' => $user->payment_method ?? 'bank',
                    'payment_verified' => $user->payment_verified,
                    'payment_verified_at' => $user->payment_verified_at,
                    
                    // Bakong information
                    'bakong_account_id' => $user->bakong_account_id,
                    'bakong_merchant_name' => $user->bakong_merchant_name,
                    'bakong_merchant_city' => $user->bakong_merchant_city,
                    'bakong_merchant_id' => $user->bakong_merchant_id,
                    'bakong_acquiring_bank' => $user->bakong_acquiring_bank,
                    'bakong_mobile_number' => $user->bakong_mobile_number,
                    'bakong_account_verified' => $user->bakong_account_verified,
                    'bakong_verified_at' => $user->bakong_verified_at,
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Get Payment Info Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment information',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update author's bank payment information
     */
    public function updateBankInfo(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user || $user->role !== 'author') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Author access required.'
                ], 403);
            }

            $validated = $request->validate([
                'bank_name' => 'required|string|max:255',
                'bank_account_number' => 'required|string|max:50',
                'bank_account_name' => 'required|string|max:255',
                'bank_branch' => 'nullable|string|max:255',
            ]);

            // Update user's bank information
            $user->update([
                'bank_name' => $validated['bank_name'],
                'bank_account_number' => $validated['bank_account_number'],
                'bank_account_name' => $validated['bank_account_name'],
                'bank_branch' => $validated['bank_branch'] ?? null,
                'payment_method' => 'bank',
                'payment_verified' => false, // Reset verification when info changes
                'payment_verified_at' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bank information updated successfully',
                'data' => [
                    'bank_name' => $user->bank_name,
                    'bank_account_number' => $user->bank_account_number,
                    'bank_account_name' => $user->bank_account_name,
                    'bank_branch' => $user->bank_branch,
                    'payment_method' => $user->payment_method,
                    'payment_verified' => $user->payment_verified,
                    'payment_verified_at' => $user->payment_verified_at,
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Update Bank Info Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update bank information',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update author's Bakong payment information
     */
    public function updateBakongInfo(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user || $user->role !== 'author') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Author access required.'
                ], 403);
            }

            $validated = $request->validate([
                'bakong_account_id' => 'required|string|max:255',
                'bakong_merchant_name' => 'required|string|max:255',
                'bakong_merchant_city' => 'nullable|string|max:255',
                'bakong_merchant_id' => 'nullable|string|max:255',
                'bakong_acquiring_bank' => 'nullable|string|max:255',
                'bakong_mobile_number' => 'nullable|string|max:20',
            ]);

            // If merchant_id is not provided, generate it from account_id
            if (empty($validated['bakong_merchant_id'])) {
                $validated['bakong_merchant_id'] = explode('@', $validated['bakong_account_id'])[0];
            }

            // Update user's Bakong information
            $user->update([
                'bakong_account_id' => $validated['bakong_account_id'],
                'bakong_merchant_name' => $validated['bakong_merchant_name'],
                'bakong_merchant_city' => $validated['bakong_merchant_city'] ?? null,
                'bakong_merchant_id' => $validated['bakong_merchant_id'] ?? null,
                'bakong_acquiring_bank' => $validated['bakong_acquiring_bank'] ?? null,
                'bakong_mobile_number' => $validated['bakong_mobile_number'] ?? null,
                'payment_method' => 'bakong',
                'bakong_account_verified' => false, // Reset verification when info changes
                'bakong_verified_at' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bakong information updated successfully',
                'data' => [
                    'bakong_account_id' => $user->bakong_account_id,
                    'bakong_merchant_name' => $user->bakong_merchant_name,
                    'bakong_merchant_city' => $user->bakong_merchant_city,
                    'bakong_merchant_id' => $user->bakong_merchant_id,
                    'bakong_acquiring_bank' => $user->bakong_acquiring_bank,
                    'bakong_mobile_number' => $user->bakong_mobile_number,
                    'payment_method' => $user->payment_method,
                    'bakong_account_verified' => $user->bakong_account_verified,
                    'bakong_verified_at' => $user->bakong_verified_at,
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Update Bakong Info Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update Bakong information',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify author's Bakong account
     */
    public function verifyBakongAccount(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user || $user->role !== 'author') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Author access required.'
                ], 403);
            }

            if (!$user->bakong_account_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please set up your Bakong account information first'
                ], 400);
            }

            // Check if account exists using Bakong API
            $exists = $this->bakongService->checkAccountExists($user->bakong_account_id);

            if ($exists) {
                // Update verification status
                $user->update([
                    'bakong_account_verified' => true,
                    'bakong_verified_at' => now(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Bakong account verified successfully',
                    'data' => [
                        'bakong_account_verified' => true,
                        'bakong_verified_at' => $user->bakong_verified_at,
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Bakong account not found. Please check your account ID.',
                    'data' => [
                        'bakong_account_verified' => false,
                        'bakong_verified_at' => null,
                    ]
                ]);
            }

        } catch (Exception $e) {
            Log::error('Verify Bakong Account Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify Bakong account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify author's bank account (manual verification for now)
     */
    public function verifyBankAccount(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user || $user->role !== 'author') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Author access required.'
                ], 403);
            }

            if (!$user->bank_account_number || !$user->bank_name) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please set up your bank account information first'
                ], 400);
            }

            // For now, we'll mark as verified (in production, this would involve actual verification)
            $user->update([
                'payment_verified' => true,
                'payment_verified_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bank account marked as verified',
                'data' => [
                    'payment_verified' => true,
                    'payment_verified_at' => $user->payment_verified_at,
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Verify Bank Account Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify bank account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get list of supported banks
     */
    public function getBanks()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'ABA Bank',
                'ACLEDA Bank',
                'Canadia Bank',
                'Cambodia Asia Bank',
                'Foreign Trade Bank of Cambodia',
                'Maybank Cambodia',
                'Prince Bank',
                'SathapanaBank',
                'Vattanac Bank',
                'Wing Bank',
                'Woori Bank Cambodia',
                'Other'
            ]
        ]);
    }

    /**
     * Get list of supported Bakong acquiring banks
     */
    public function getBakongBanks()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'ABA Bank',
                'ACLEDA Bank',
                'Canadia Bank',
                'Cambodia Asia Bank',
                'Foreign Trade Bank of Cambodia',
                'Maybank Cambodia',
                'Prince Bank',
                'SathapanaBank',
                'Vattanac Bank',
                'Wing Bank',
                'Woori Bank Cambodia',
                'Other'
            ]
        ]);
    }

    /**
     * Test QR code generation with author's account
     */
    public function testQRGeneration(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user || $user->role !== 'author') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Author access required.'
                ], 403);
            }

            if ($user->payment_method !== 'bakong' || !$user->bakong_account_verified) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please verify your Bakong account first'
                ], 400);
            }

            $validated = $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'currency' => 'sometimes|in:USD,KHR',
            ]);

            // Temporarily override the service configuration with author's details
            $originalConfig = [
                'account_id' => config('services.bakong.account_id'),
                'merchant_name' => config('services.bakong.merchant_name'),
                'merchant_city' => config('services.bakong.merchant_city'),
                'merchant_id' => config('services.bakong.merchant_id'),
                'acquiring_bank' => config('services.bakong.acquiring_bank'),
                'mobile_number' => config('services.bakong.mobile_number'),
            ];

            // Set author's configuration
            config([
                'services.bakong.account_id' => $user->bakong_account_id,
                'services.bakong.merchant_name' => $user->bakong_merchant_name,
                'services.bakong.merchant_city' => $user->bakong_merchant_city,
                'services.bakong.merchant_id' => $user->bakong_merchant_id,
                'services.bakong.acquiring_bank' => $user->bakong_acquiring_bank,
                'services.bakong.mobile_number' => $user->bakong_mobile_number,
            ]);

            // Create a new service instance with author's config
            $authorBakongService = new BakongPaymentService();
            
            $result = $authorBakongService->generateQRCode(
                (float) $validated['amount'],
                $validated['currency'] ?? 'USD',
                'TEST-' . time(),
                'Test QR'
            );

            // Restore original configuration
            config($originalConfig);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Test QR code generated successfully',
                    'data' => [
                        'qr_string' => $result['qr_string'],
                        'amount' => $result['amount'],
                        'currency' => $result['currency'],
                        'test' => true
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to generate test QR code',
                    'error' => $result['error'] ?? 'Unknown error'
                ], 400);
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Test QR Generation Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to test QR generation',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\DiscountCode;
use App\Models\DiscountCodeUsage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DiscountCodeController extends Controller
{
    /**
     * Get all discount codes (Admin only)
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            
            // Check if user is admin
            if ($user->role !== 'admin') {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $query = DiscountCode::with('creator:id,name');

            // Filter by status (active, inactive, expired)
            if ($request->has('status')) {
                $now = Carbon::now();
                
                if ($request->status === 'active') {
                    // Active: is_active = true AND (not expired OR no expiry date)
                    $query->where('is_active', true)
                        ->where(function($q) use ($now) {
                            $q->whereNull('expires_at')
                              ->orWhere('expires_at', '>=', $now);
                        });
                } 
                elseif ($request->status === 'expired') {
                    // Expired: has expiry date AND expiry date is in the past
                    $query->whereNotNull('expires_at')
                        ->where('expires_at', '<', $now);
                }
            }

            // Filter by type
            if ($request->filled('type')) {
                $query->where('type', $request->type);
            }

            // Search by code or name
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                      ->orWhere('name', 'like', "%{$search}%");
                });
            }

            // Default sort by created_at desc
            $query->orderBy('created_at', 'desc');

            $discountCodes = $query->paginate(15);

            return response()->json([
                'message' => 'Discount codes retrieved successfully',
                'discount_codes' => $discountCodes
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve discount codes',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new discount code (Admin only)
     */
    public function store(Request $request)
    {
        try {
            $user = Auth::user();
            
            // Check if user is admin
            if ($user->role !== 'admin') {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $request->validate([
                'code' => 'required|string|max:50|unique:discount_codes,code',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'type' => 'required|in:percentage,fixed',
                'value' => 'required|numeric|min:0',
                'minimum_amount' => 'nullable|numeric|min:0',
                'maximum_discount' => 'nullable|numeric|min:0',
                'usage_limit' => 'nullable|integer|min:1',
                'usage_limit_per_user' => 'nullable|integer|min:1',
                'starts_at' => 'nullable|date|after_or_equal:today',
                'expires_at' => 'nullable|date|after:starts_at',
                'is_active' => 'boolean',
            ]);

            // Validate percentage value
            if ($request->type === 'percentage' && $request->value > 100) {
                return response()->json([
                    'error' => 'Percentage discount cannot exceed 100%'
                ], 422);
            }

            $discountCode = DiscountCode::create([
                'code' => strtoupper($request->code),
                'name' => $request->name,
                'description' => $request->description,
                'type' => $request->type,
                'value' => $request->value,
                'minimum_amount' => $request->minimum_amount,
                'maximum_discount' => $request->maximum_discount,
                'usage_limit' => $request->usage_limit,
                'usage_limit_per_user' => $request->usage_limit_per_user,
                'starts_at' => $request->starts_at,
                'expires_at' => $request->expires_at,
                'is_active' => $request->is_active ?? true,
                'created_by' => $user->id,
            ]);

            $discountCode->load('creator:id,name');

            return response()->json([
                'message' => 'Discount code created successfully',
                'discount_code' => $discountCode
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create discount code',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific discount code (Admin only)
     */
    public function show($id)
    {
        try {
            $user = Auth::user();
            
            // Check if user is admin
            if ($user->role !== 'admin') {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $discountCode = DiscountCode::with(['creator:id,name', 'usages.user:id,name'])
                ->findOrFail($id);

            return response()->json([
                'message' => 'Discount code retrieved successfully',
                'discount_code' => $discountCode
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Discount code not found'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve discount code',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a discount code (Admin only)
     */
    public function update(Request $request, $id)
    {
        try {
            $user = Auth::user();
            
            // Check if user is admin
            if ($user->role !== 'admin') {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $discountCode = DiscountCode::findOrFail($id);

            $request->validate([
                'code' => 'required|string|max:50|unique:discount_codes,code,' . $id,
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'type' => 'required|in:percentage,fixed',
                'value' => 'required|numeric|min:0',
                'minimum_amount' => 'nullable|numeric|min:0',
                'maximum_discount' => 'nullable|numeric|min:0',
                'usage_limit' => 'nullable|integer|min:1',
                'usage_limit_per_user' => 'nullable|integer|min:1',
                'starts_at' => 'nullable|date',
                'expires_at' => 'nullable|date|after:starts_at',
                'is_active' => 'boolean',
            ]);

            // Validate percentage value
            if ($request->type === 'percentage' && $request->value > 100) {
                return response()->json([
                    'error' => 'Percentage discount cannot exceed 100%'
                ], 422);
            }

            $discountCode->update([
                'code' => strtoupper($request->code),
                'name' => $request->name,
                'description' => $request->description,
                'type' => $request->type,
                'value' => $request->value,
                'minimum_amount' => $request->minimum_amount,
                'maximum_discount' => $request->maximum_discount,
                'usage_limit' => $request->usage_limit,
                'usage_limit_per_user' => $request->usage_limit_per_user,
                'starts_at' => $request->starts_at,
                'expires_at' => $request->expires_at,
                'is_active' => $request->is_active ?? $discountCode->is_active,
            ]);

            $discountCode->load('creator:id,name');

            return response()->json([
                'message' => 'Discount code updated successfully',
                'discount_code' => $discountCode
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Discount code not found'], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update discount code',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a discount code (Admin only)
     */
    public function destroy($id)
    {
        try {
            $user = Auth::user();
            
            // Check if user is admin
            if ($user->role !== 'admin') {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $discountCode = DiscountCode::findOrFail($id);

            // Check if discount code has been used
            if ($discountCode->used_count > 0) {
                return response()->json([
                    'error' => 'Cannot delete discount code that has been used'
                ], 422);
            }

            $discountCode->delete();

            return response()->json([
                'message' => 'Discount code deleted successfully'
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Discount code not found'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete discount code',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate and apply discount code (User)
     */
    public function validateCode(Request $request)
    {
        try {
            $user = Auth::user();

            $request->validate([
                'code' => 'required|string',
                'subtotal' => 'required|numeric|min:0',
            ]);

            $discountCode = DiscountCode::where('code', strtoupper($request->code))->first();

            if (!$discountCode) {
                return response()->json([
                    'error' => 'Invalid discount code'
                ], 404);
            }

            if (!$discountCode->canBeUsedByUser($user->id)) {
                $reasons = [];
                
                if (!$discountCode->is_active) {
                    $reasons[] = 'This discount code is no longer active';
                } elseif ($discountCode->starts_at && now()->lt($discountCode->starts_at)) {
                    $reasons[] = 'This discount code is not yet active';
                } elseif ($discountCode->expires_at && now()->gt($discountCode->expires_at)) {
                    $reasons[] = 'This discount code has expired';
                } elseif ($discountCode->usage_limit && $discountCode->used_count >= $discountCode->usage_limit) {
                    $reasons[] = 'This discount code has reached its usage limit';
                } elseif ($discountCode->usage_limit_per_user) {
                    $userUsageCount = $discountCode->usages()->where('user_id', $user->id)->count();
                    if ($userUsageCount >= $discountCode->usage_limit_per_user) {
                        $reasons[] = 'You have already used this discount code the maximum number of times';
                    }
                }

                return response()->json([
                    'error' => 'Discount code cannot be used',
                    'reasons' => $reasons
                ], 422);
            }

            $discountAmount = $discountCode->calculateDiscount($request->subtotal);

            if ($discountAmount <= 0) {
                $message = 'Discount code is valid but no discount applied';
                if ($discountCode->minimum_amount && $request->subtotal < $discountCode->minimum_amount) {
                    $message = "Minimum order amount of $" . number_format((float)$discountCode->minimum_amount, 2) . " required";
                }

                return response()->json([
                    'error' => $message
                ], 422);
            }

            return response()->json([
                'message' => 'Discount code applied successfully',
                'discount_code' => [
                    'id' => $discountCode->id,
                    'code' => $discountCode->code,
                    'name' => $discountCode->name,
                    'type' => $discountCode->type,
                    'value' => $discountCode->value,
                ],
                'discount_amount' => $discountAmount,
                'new_total' => $request->subtotal - $discountAmount
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to validate discount code',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate a random discount code
     */
    public function generateCode()
    {
        try {
            $user = Auth::user();
            
            // Check if user is admin
            if ($user->role !== 'admin') {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            do {
                $code = strtoupper(Str::random(8));
            } while (DiscountCode::where('code', $code)->exists());

            return response()->json([
                'code' => $code
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate code',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

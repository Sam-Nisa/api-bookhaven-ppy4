<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Cart;
use App\Models\Book;
use App\Models\DiscountCode;
use App\Models\DiscountCodeUsage;
use App\Services\CommissionService;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Models\User;
use App\Notifications\OrderPaidNotification;

class OrderController extends Controller
{
    // Get all orders for current user
    public function index()
    {
        try {
            $user = Auth::user();
            // Only show orders that are paid AND payment is completed
            $orders = Order::with([
                'items.book' => function ($query) {
                    $query->select('id', 'title', 'author_name', 'price', 'images_url');
                }
            ])
                ->where('user_id', $user->id)
                ->where('status', 'paid')
                ->where('payment_status', 'completed')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'message' => 'Orders retrieved successfully',
                'orders' => $orders
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve orders',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // Create a new order from cart
    public function store(Request $request)
    {
        try {
            $user = Auth::user();

            $request->validate([
                'payment_method' => 'required|in:bakong', // Only allow Bakong
                'discount_code' => 'nullable|string',
                'shipping_address' => 'required|array',
                'shipping_address.first_name' => 'required|string|max:255',
                'shipping_address.last_name' => 'required|string|max:255',
                'shipping_address.email' => 'required|email',
                'shipping_address.address' => 'required|string|max:500',
                'shipping_address.city' => 'required|string|max:255',
            ]);

            // Get user's cart
            $cart = Cart::with('items.book')->where('user_id', $user->id)->first();

            if (!$cart || $cart->items->isEmpty()) {
                return response()->json([
                    'error' => 'Cart is empty'
                ], 400);
            }

            DB::beginTransaction();

            // Calculate totals
            $subtotal = 0;
            $orderItems = [];

            foreach ($cart->items as $cartItem) {
                $book = $cartItem->book;

                // Check stock availability
                if ($book->stock < $cartItem->quantity) {
                    DB::rollBack();
                    return response()->json([
                        'error' => "Insufficient stock for {$book->title}",
                        'available_stock' => $book->stock
                    ], 400);
                }

                // Calculate price with discount
                $price = floatval($book->price);
                $discountValue = floatval($book->discount_value ?? 0);
                $discountType = $book->discount_type;

                $finalPrice = $price;
                if ($discountType === 'percentage' && $discountValue > 0) {
                    $finalPrice = $price - ($price * $discountValue / 100);
                    // Round to 2 decimal places to avoid floating-point precision issues
                    $finalPrice = round($finalPrice, 2);
                } elseif ($discountType === 'fixed' && $discountValue > 0) {
                    $finalPrice = max(0, $price - $discountValue);
                    // Round to 2 decimal places to avoid floating-point precision issues
                    $finalPrice = round($finalPrice, 2);
                }

                $itemTotal = round($finalPrice * $cartItem->quantity, 2);
                $subtotal += $itemTotal;

                $orderItems[] = [
                    'book_id' => $book->id,
                    'quantity' => $cartItem->quantity,
                    'price' => round($finalPrice, 2),
                    'total' => round($itemTotal, 2)
                ];
            }

            // Calculate shipping and tax - FREE SHIPPING
            $shippingCost = 0;
            $taxAmount = 0; // No tax

            // Handle discount code if provided
            $discountCode = null;
            $discountAmount = 0;

            if ($request->discount_code) {
                $discountCode = DiscountCode::where('code', strtoupper($request->discount_code))->first();

                if (!$discountCode || !$discountCode->canBeUsedByUser($user->id)) {
                    DB::rollBack();
                    return response()->json([
                        'error' => 'Invalid or expired discount code'
                    ], 422);
                }

                $discountAmount = $discountCode->calculateDiscount($subtotal);

                if ($discountAmount <= 0) {
                    DB::rollBack();
                    $message = 'Discount code is valid but no discount applied';
                    if ($discountCode->minimum_amount && $subtotal < $discountCode->minimum_amount) {
                        $message = "Minimum order amount of $" . number_format((float) $discountCode->minimum_amount, 2) . " required";
                    }
                    return response()->json(['error' => $message], 422);
                }
            }

            $totalAmount = round($subtotal + $shippingCost + $taxAmount - $discountAmount, 2);

            // For Bakong payment - store order data in cache, don't create order yet
            if ($request->payment_method === 'bakong') {
                // Generate a unique pending order ID
                $pendingOrderId = 'pending_' . time() . '_' . $user->id;

                // Store order data in cache for 15 minutes
                $orderData = [
                    'user_id' => $user->id,
                    'total_amount' => $totalAmount,
                    'subtotal' => $subtotal,
                    'shipping_cost' => $shippingCost,
                    'tax_amount' => $taxAmount,
                    'discount_code_id' => $discountCode?->id,
                    'discount_code' => $discountCode?->code,
                    'discount_amount' => $discountAmount,
                    'payment_method' => $request->payment_method,
                    'shipping_address' => $request->shipping_address,
                    'order_items' => $orderItems,
                    'cart_id' => $cart->id,
                ];

                Cache::put("pending_order_{$pendingOrderId}", $orderData, now()->addMinutes(15));

                DB::commit();

                return response()->json([
                    'message' => 'Order prepared for payment',
                    'order' => [
                        'id' => $pendingOrderId,
                        'total_amount' => $totalAmount,
                        'payment_method' => $request->payment_method,
                        'items' => $orderItems
                    ]
                ], 201);
            }

            // For other payment methods - create order immediately (assumed paid)
            $order = Order::create([
                'user_id' => $user->id,
                'total_amount' => $totalAmount,
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'tax_amount' => $taxAmount,
                'discount_code_id' => $discountCode?->id,
                'discount_code' => $discountCode?->code,
                'discount_amount' => $discountAmount,
                'status' => 'paid',
                'payment_method' => $request->payment_method,
                'payment_status' => 'completed',
                'shipping_address' => json_encode($request->shipping_address),
            ]);

            // Create order items and update stock
            foreach ($orderItems as $itemData) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'book_id' => $itemData['book_id'],
                    'quantity' => $itemData['quantity'],
                    'price' => $itemData['price'],
                    'total' => $itemData['total']
                ]);

                // Update book stock
                $book = Book::find($itemData['book_id']);
                $book->decrement('stock', $itemData['quantity']);
            }

            // Process commission and distribute earnings
            $commissionService = new CommissionService();
            $commissionService->processOrderCommission($order);
            $commissionService->distributeEarnings($order);

            // Clear the cart
            $cart->items()->delete();

            // Handle discount code usage
            if ($discountCode && $discountAmount > 0) {
                // Record the usage
                DiscountCodeUsage::create([
                    'discount_code_id' => $discountCode->id,
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'discount_amount' => $discountAmount,
                ]);

                // Update usage count
                $discountCode->increment('used_count');
            }

            DB::commit();

            // Send notification to admins
            try {
                $admins = User::where('role', 'admin')->get();
                Notification::send($admins, new OrderPaidNotification($order));
            } catch (\Exception $e) {
                Log::error('Failed to send OrderPaidNotification to admins: ' . $e->getMessage());
            }

            return response()->json([
                'message' => 'Order created successfully',
                'order' => $order->load('items.book')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Order creation failed: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to create order',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create order from cached data when Bakong payment is completed
     */
    public function createFromPendingOrder($pendingOrderId, $transactionId = null)
    {
        try {
            // Get cached order data
            $orderData = Cache::get("pending_order_{$pendingOrderId}");

            if (!$orderData) {
                throw new \Exception('Pending order data not found or expired');
            }

            DB::beginTransaction();

            // Create the actual order
            $order = Order::create([
                'user_id' => $orderData['user_id'],
                'total_amount' => $orderData['total_amount'],
                'subtotal' => $orderData['subtotal'],
                'shipping_cost' => $orderData['shipping_cost'],
                'tax_amount' => $orderData['tax_amount'],
                'discount_code_id' => $orderData['discount_code_id'],
                'discount_code' => $orderData['discount_code'],
                'discount_amount' => $orderData['discount_amount'],
                'status' => 'paid',
                'payment_method' => $orderData['payment_method'],
                'payment_status' => 'completed',
                'payment_transaction_id' => $transactionId,
                'shipping_address' => json_encode($orderData['shipping_address']),
            ]);

            // Create order items and update stock
            foreach ($orderData['order_items'] as $itemData) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'book_id' => $itemData['book_id'],
                    'quantity' => $itemData['quantity'],
                    'price' => $itemData['price'],
                    'total' => $itemData['total']
                ]);

                // Update book stock
                $book = Book::find($itemData['book_id']);
                if ($book) {
                    $book->decrement('stock', $itemData['quantity']);
                }
            }

            // REFRESH order to load items for commission and telegram
            $order->load('items.book');

            // Process commission and distribute earnings
            $commissionService = new CommissionService();
            $commissionService->processOrderCommission($order);
            $commissionService->distributeEarnings($order);

            Log::info('Commission processed and earnings distributed for pending order', [
                'order_id' => $order->id,
                'pending_order_id' => $pendingOrderId,
                'total_commission' => $order->items->sum('commission_amount'),
                'total_owner_earnings' => $order->items->sum('owner_earnings')
            ]);

            // Clear the cart
            $cart = Cart::find($orderData['cart_id']);
            if ($cart) {
                $cart->items()->delete();
            }

            // Handle discount code usage
            if ($orderData['discount_code_id'] && $orderData['discount_amount'] > 0) {
                DiscountCodeUsage::create([
                    'discount_code_id' => $orderData['discount_code_id'],
                    'user_id' => $orderData['user_id'],
                    'order_id' => $order->id,
                    'discount_amount' => $orderData['discount_amount'],
                ]);

                $discountCode = DiscountCode::find($orderData['discount_code_id']);
                if ($discountCode) {
                    $discountCode->increment('used_count');
                }
            }

            // Remove cached data
            Cache::forget("pending_order_{$pendingOrderId}");

            DB::commit();

            // Send notification to admins
            try {
                $admins = User::where('role', 'admin')->get();
                Notification::send($admins, new OrderPaidNotification($order));
            } catch (\Exception $e) {
                Log::error('Failed to send OrderPaidNotification to admins: ' . $e->getMessage());
            }

            // Send Telegram notification
            try {
                Log::info('Sending Telegram from OrderController', ['order_id' => $order->id]);
                $telegramService = new TelegramService();
                $result = $telegramService->sendPaymentConfirmation($order);
                Log::info('Telegram sent from OrderController', [
                    'order_id' => $order->id,
                    'success' => $result['success'] ?? false,
                    'message' => $result['message'] ?? 'N/A'
                ]);
            } catch (\Exception $e) {
                // Log error but don't fail the order creation
                Log::error('Failed to send Telegram notification from OrderController: ' . $e->getMessage(), [
                    'order_id' => $order->id,
                    'trace' => $e->getTraceAsString()
                ]);
            }

            // Also send a simple quick alert as backup
            try {
                Log::info('Sending quick Telegram alert as backup', ['order_id' => $order->id]);
                $telegramService = new TelegramService();
                $telegramService->sendSimplePaymentNotification(
                    $order->id,
                    (float) $order->total_amount,
                    'USD',
                    ($order->user ? $order->user->name : 'Customer')
                );
                Log::info('Quick Telegram alert sent', ['order_id' => $order->id]);
            } catch (\Exception $e) {
                Log::error('Failed to send quick Telegram alert: ' . $e->getMessage(), [
                    'order_id' => $order->id
                ]);
            }

            return $order->load('items.book');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create order from pending: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Admin: Get all orders with stats for sales tracking
     * Separates admin sales (admin-created books) from author sales
     */
    public function adminIndex(Request $request)
    {
        try {
            $user = Auth::user();

            Log::info('Admin orders endpoint called', [
                'user_id' => $user?->id,
                'user_role' => $user?->role,
                'filters' => $request->all()
            ]);

            // Check if user is admin
            if ($user->role !== 'admin') {
                Log::warning('Unauthorized access to admin orders', ['user_id' => $user->id, 'role' => $user->role]);
                return response()->json([
                    'error' => 'Unauthorized'
                ], 403);
            }

            // Get admin user IDs (users with admin role)
            $adminUserIds = \App\Models\User::where('role', 'admin')->pluck('id');

            // Build query for orders containing books created by admin
            $query = Order::with(['user:id,name,email', 'items.book:id,title,author_name,author_id'])
                ->where('status', 'paid')
                ->where('payment_status', 'completed')
                ->whereHas('items.book', function ($q) use ($adminUserIds) {
                    $q->whereIn('author_id', $adminUserIds);
                });

            // Apply filters
            if ($request->status && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            if ($request->payment_method && $request->payment_method !== 'all') {
                $query->where('payment_method', $request->payment_method);
            }

            if ($request->start_date) {
                $query->whereDate('created_at', '>=', $request->start_date);
            }

            if ($request->end_date) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }

            if ($request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('id', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            }

            // Get paginated orders
            $orders = $query->orderBy('created_at', 'desc')->paginate(20);

            // Calculate stats for admin sales only
            $statsQuery = Order::where('status', 'paid')
                ->where('payment_status', 'completed')
                ->whereHas('items.book', function ($q) use ($adminUserIds) {
                    $q->whereIn('author_id', $adminUserIds);
                });

            // Apply same date filters to stats
            if ($request->start_date) {
                $statsQuery->whereDate('created_at', '>=', $request->start_date);
            }
            if ($request->end_date) {
                $statsQuery->whereDate('created_at', '<=', $request->end_date);
            }

            // Calculate admin-specific revenue (only from admin books)
            $adminRevenue = OrderItem::whereHas('order', function ($q) use ($request) {
                $q->where('status', 'paid')->where('payment_status', 'completed');
                if ($request->start_date) {
                    $q->whereDate('created_at', '>=', $request->start_date);
                }
                if ($request->end_date) {
                    $q->whereDate('created_at', '<=', $request->end_date);
                }
            })
                ->whereHas('book', function ($q) use ($adminUserIds) {
                    $q->whereIn('author_id', $adminUserIds);
                })
                ->sum('total');

            $stats = [
                'total_orders' => $statsQuery->count(),
                'total_revenue' => $adminRevenue,
                'pending_orders' => Order::where('status', 'processing')->count(),
                'completed_orders' => $statsQuery->count(),
            ];

            Log::info('Admin orders response', [
                'orders_count' => $orders->count(),
                'stats' => $stats
            ]);

            return response()->json([
                'message' => 'Admin sales retrieved successfully',
                'orders' => $orders,
                'stats' => $stats
            ], 200);

        } catch (\Exception $e) {
            Log::error('Admin orders fetch error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to retrieve admin sales',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Author: Get sales data for their books
     */
    public function authorSales(Request $request)
    {
        try {
            $user = Auth::user();

            Log::info('Author sales endpoint called', [
                'user_id' => $user?->id,
                'user_role' => $user?->role,
                'filters' => $request->all()
            ]);

            // Check if user is author
            if ($user->role !== 'author') {
                Log::warning('Unauthorized access to author sales', ['user_id' => $user->id, 'role' => $user->role]);
                return response()->json([
                    'error' => 'Unauthorized'
                ], 403);
            }

            // Get author's books
            $authorBooks = Book::where('author_id', $user->id)->pluck('id');

            if ($authorBooks->isEmpty()) {
                return response()->json([
                    'message' => 'No books found',
                    'sales' => ['data' => []],
                    'stats' => [
                        'total_revenue' => 0,
                        'total_books_sold' => 0,
                        'total_orders' => 0
                    ]
                ], 200);
            }

            // Build query for order items of author's books
            $query = OrderItem::with([
                'book:id,title,author_name,price',
                'order' => function ($q) {
                    $q->select('id', 'user_id', 'created_at', 'status', 'payment_status')
                        ->where('status', 'paid')
                        ->where('payment_status', 'completed')
                        ->with('user:id,name,email');
                }
            ])
                ->whereIn('book_id', $authorBooks)
                ->whereHas('order', function ($q) {
                    $q->where('status', 'paid')
                        ->where('payment_status', 'completed');
                });

            // Apply date filters
            if ($request->start_date) {
                $query->whereHas('order', function ($q) use ($request) {
                    $q->whereDate('created_at', '>=', $request->start_date);
                });
            }

            if ($request->end_date) {
                $query->whereHas('order', function ($q) use ($request) {
                    $q->whereDate('created_at', '<=', $request->end_date);
                });
            }

            // Get paginated sales
            $sales = $query->orderBy('created_at', 'desc')->paginate(20);

            // Calculate stats
            $statsQuery = OrderItem::whereIn('book_id', $authorBooks)
                ->whereHas('order', function ($q) {
                    $q->where('status', 'paid')
                        ->where('payment_status', 'completed');
                });

            // Apply same date filters to stats
            if ($request->start_date) {
                $statsQuery->whereHas('order', function ($q) use ($request) {
                    $q->whereDate('created_at', '>=', $request->start_date);
                });
            }
            if ($request->end_date) {
                $statsQuery->whereHas('order', function ($q) use ($request) {
                    $q->whereDate('created_at', '<=', $request->end_date);
                });
            }

            $stats = [
                'total_revenue' => $statsQuery->sum('total'),
                'total_books_sold' => $statsQuery->sum('quantity'),
                'total_orders' => $statsQuery->distinct('order_id')->count('order_id'),
            ];

            Log::info('Author sales response', [
                'sales_count' => $sales->count(),
                'stats' => $stats,
                'author_books_count' => $authorBooks->count()
            ]);

            return response()->json([
                'message' => 'Sales retrieved successfully',
                'sales' => $sales,
                'stats' => $stats
            ], 200);

        } catch (\Exception $e) {
            Log::error('Author sales fetch error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to retrieve sales',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete an order (Admin only)
     */
    public function destroy($id)
    {
        try {
            $user = Auth::user();

            // Check if user is admin
            if ($user->role !== 'admin') {
                return response()->json([
                    'error' => 'Unauthorized. Only admins can delete orders.'
                ], 403);
            }

            $order = Order::with('items')->find($id);

            if (!$order) {
                return response()->json([
                    'error' => 'Order not found'
                ], 404);
            }

            DB::beginTransaction();

            // Restore book stock for each item
            foreach ($order->items as $item) {
                $book = Book::find($item->book_id);
                if ($book) {
                    $book->increment('stock', $item->quantity);
                }
            }

            // Delete order items first (foreign key constraint)
            $order->items()->delete();

            // Delete the order
            $order->delete();

            DB::commit();

            Log::info('Order deleted by admin', [
                'order_id' => $id,
                'admin_id' => $user->id,
                'admin_name' => $user->name
            ]);

            return response()->json([
                'message' => 'Order deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Order deletion failed: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to delete order',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a sale (order item) - Author can delete their own book sales, Admin can delete any
     */
    public function deleteSale($orderItemId)
    {
        try {
            $user = Auth::user();

            $orderItem = OrderItem::with(['order', 'book'])->find($orderItemId);

            if (!$orderItem) {
                return response()->json([
                    'error' => 'Sale not found'
                ], 404);
            }

            // Check authorization
            if ($user->role === 'admin') {
                // Admin can delete any sale
            } elseif ($user->role === 'author') {
                // Author can only delete sales of their own books
                if ($orderItem->book->author_id !== $user->id) {
                    return response()->json([
                        'error' => 'Unauthorized. You can only delete sales of your own books.'
                    ], 403);
                }
            } else {
                return response()->json([
                    'error' => 'Unauthorized'
                ], 403);
            }

            DB::beginTransaction();

            // Restore book stock
            $book = Book::find($orderItem->book_id);
            if ($book) {
                $book->increment('stock', $orderItem->quantity);
            }

            // Update order total
            $order = $orderItem->order;
            $order->total_amount = (float) $order->total_amount - (float) $orderItem->total;
            $order->save();

            // Delete the order item
            $orderItem->delete();

            // If this was the last item in the order, delete the order too
            if ($order->items()->count() === 0) {
                $order->delete();
            }

            DB::commit();

            Log::info('Sale deleted', [
                'order_item_id' => $orderItemId,
                'user_id' => $user->id,
                'user_role' => $user->role,
                'book_id' => $orderItem->book_id
            ]);

            return response()->json([
                'message' => 'Sale deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Sale deletion failed: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to delete sale',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
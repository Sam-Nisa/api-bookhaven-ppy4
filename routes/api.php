<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderItemController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\OrderCouponController;
use App\Http\Controllers\InventoryLogController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\DiscountCodeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AuthorDashboardController;
use App\Http\Controllers\AuthorPaymentController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\BakongPaymentController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\TelegramController;
use App\Http\Controllers\PayoutController;
use App\Http\Controllers\AdminPayoutController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\AuthorRequestController;
use App\Http\Controllers\NotificationController;

// Upload file and image
Route::post('/upload', [UploadController::class, 'upload']);

// Public API routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Forgot Password routes (public)
Route::post('/forgot-password/send-otp', [ForgotPasswordController::class, 'sendOtp']);
Route::post('/forgot-password/verify-otp', [ForgotPasswordController::class, 'verifyOtp']);
Route::post('/forgot-password/reset-password', [ForgotPasswordController::class, 'resetPassword']);
Route::post('/forgot-password/resend-otp', [ForgotPasswordController::class, 'resendOtp']);

// Contact routes (public)
Route::post('/contact', [ContactController::class, 'submitContactForm']);
Route::get('/contact/info', [ContactController::class, 'getContactInfo']);


// books
Route::get('books/best-sellers', [BookController::class, 'bestSellers']); // Public - Get best seller books
Route::get('books/best-sellers/stats', [BookController::class, 'bestSellersStats']); // Public - Get best sellers stats
Route::get('books', [BookController::class, 'index']);       // All logged-in users
Route::get('books/{id}', [BookController::class, 'show']);   // All logged-in users

// genres
Route::get('genres', [GenreController::class, 'index']);       // All logged-in users
Route::get('genres/{id}', [GenreController::class, 'show']);   // All logged-in users

// Public review routes
Route::get('books/{bookId}/reviews', [ReviewController::class, 'getBookReviews']); // Public - Get reviews for a book

// Debug route (remove in production)
Route::get('debug/user/{id}', function($id) {
    $user = App\Models\User::find($id);
    return response()->json([
        'user' => $user,
        'avatar' => $user->avatar ?? 'null',
        'avatar_url_column' => $user->getAttributes()['avatar_url'] ?? 'null',
        'avatar_url_accessor' => $user->avatar_url ?? 'null',
    ]);
});

// Protected routes (JWT)
Route::middleware(['jwt.auth'])->group(function () {

    // Auth
    Route::get('profile', [AuthController::class, 'profile']);
    Route::post('profile', [AuthController::class, 'updateProfile']);
    Route::delete('profile/avatar', [AuthController::class, 'deleteAvatar']);
    Route::post('change-password', [AuthController::class, 'changePassword']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::get('dashboard-stats', [DashboardController::class, 'index']);
    Route::get('author-dashboard-stats', [AuthorDashboardController::class, 'index']);

    // Messages
    Route::get('messages/contacts', [MessageController::class, 'getContacts']);
    Route::get('messages/{contactId}', [MessageController::class, 'getMessages']);
    Route::post('messages', [MessageController::class, 'sendMessage']);
    Route::put('messages/{message}', [MessageController::class, 'updateMessage']);
    Route::delete('messages/conversation/{contactId}', [MessageController::class, 'deleteConversation']);
    Route::delete('messages/{message}', [MessageController::class, 'deleteMessage']);

    // Users
    Route::get('users', [UserController::class, 'index']);
    Route::get('users/{id}', [UserController::class, 'show']);
    Route::put('users/{id}', [UserController::class, 'update']);
    Route::delete('users/{id}', [UserController::class, 'destroy']);
    Route::put('users/{id}/approve', [UserController::class, 'approveToAuthor']);

    // Author Requests
    Route::post('author-requests', [AuthorRequestController::class, 'store']);
    Route::get('author-requests/my', [AuthorRequestController::class, 'myRequest']);
    Route::get('admin/author-requests', [AuthorRequestController::class, 'index']);
    Route::put('admin/author-requests/{id}/status', [AuthorRequestController::class, 'updateStatus']);

     // Genres CRUD

    Route::post('genres', [GenreController::class, 'store']);      // Admin only
    Route::put('genres/{id}', [GenreController::class, 'update']); // Admin only
    Route::delete('genres/{id}', [GenreController::class, 'destroy']); // Admin only

    // Books CRUD

    Route::post('books', [BookController::class, 'store']);      // Admin or author
    Route::put('books/{id}', [BookController::class, 'update']); // Admin or book author
    Route::delete('books/{id}', [BookController::class, 'destroy']); // Admin or book author

    // Admin-specific routes
    Route::get('admin/books', [BookController::class, 'adminIndex']); // Admin: Get all books

    // Author-specific routes
    Route::get('author/books', [BookController::class, 'authorIndex']); // Author: Get own books

    // Reviews (legacy - keep for backward compatibility)
    Route::get('reviews', [ReviewController::class, 'index']);
    Route::post('reviews', [ReviewController::class, 'store']);
    Route::delete('reviews/{id}', [ReviewController::class, 'destroy']); // Owner/Admin

    // Book-specific review routes (protected)
    Route::prefix('books/{bookId}/reviews')->group(function () {
        Route::post('/', [ReviewController::class, 'createReview']); // Create review for a book
        Route::get('/user', [ReviewController::class, 'getUserReview']); // Get current user's review
        Route::put('/{reviewId}', [ReviewController::class, 'updateReview']); // Update review
        Route::delete('/{reviewId}', [ReviewController::class, 'deleteReview']); // Delete review
    });

    // Wishlist
    Route::get('wishlists', [WishlistController::class, 'index']);
    Route::post('wishlists', [WishlistController::class, 'add']);
    Route::delete('wishlists/{book_id}', [WishlistController::class, 'remove']);

    // Notifications
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    // Orders
    Route::get('orders', [OrderController::class, 'index']);
    Route::post('orders', [OrderController::class, 'store']);
    Route::get('orders/{id}', [OrderController::class, 'show']);
    Route::delete('orders/{id}', [OrderController::class, 'destroy']); // Admin only - Delete order

    // Admin: View all orders (sales tracking)
    Route::get('admin/orders', [OrderController::class, 'adminIndex']); // Admin only

    // Author: View their book sales
    Route::get('author/sales', [OrderController::class, 'authorSales']); // Author only

    // Delete sale (order item) - Admin can delete any, Author can delete their own
    Route::delete('sales/{orderItemId}', [OrderController::class, 'deleteSale']);

    // Order Items
    Route::post('order-items', [OrderItemController::class, 'store']);
    Route::get('order-items/{order_id}', [OrderItemController::class, 'index']);

    // Coupons
    Route::post('coupons', [CouponController::class, 'store']); // Admin only
    Route::get('coupons', [CouponController::class, 'index']); // All users
    Route::get('coupons/{id}', [CouponController::class, 'show']);
    Route::delete('coupons/{id}', [CouponController::class, 'destroy']); // Admin only

    // Order Coupons
    Route::post('order-coupons', [OrderCouponController::class, 'store']);
    Route::get('order-coupons', [OrderCouponController::class, 'index']); // Admin only
    Route::get('order-coupons/{id}', [OrderCouponController::class, 'show']);
    Route::delete('order-coupons/{id}', [OrderCouponController::class, 'destroy']); // Admin only

    // Inventory Logs
    Route::get('inventory-logs', [InventoryLogController::class, 'index']);
    Route::post('inventory-logs', [InventoryLogController::class, 'store']);
    Route::get('inventory-logs/{id}', [InventoryLogController::class, 'show']);
    Route::delete('inventory-logs/{id}', [InventoryLogController::class, 'destroy']);


 
    // Cart routes
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'index']);                    // Get cart items
        Route::get('/count', [CartController::class, 'getCartCount']);        // Get cart count
        Route::post('/add', [CartController::class, 'addToCart']);            // Add item to cart
        Route::patch('/item/{bookId}', [CartController::class, 'updateQuantity']); // Update quantity by book ID
        Route::delete('/item/{itemId}', [CartController::class, 'removeItem']); // Remove item by item ID
        Route::delete('/clear', [CartController::class, 'clearCart']);        // Clear entire cart
    });

    // Discount code routes
    Route::prefix('discount-codes')->group(function () {
        // Admin routes
        Route::get('/', [DiscountCodeController::class, 'index']);           // Get all discount codes (Admin)
        Route::post('/', [DiscountCodeController::class, 'store']);          // Create discount code (Admin)
        Route::get('/generate-code', [DiscountCodeController::class, 'generateCode']); // Generate random code (Admin)
        Route::get('/{id}', [DiscountCodeController::class, 'show']);        // Get specific discount code (Admin)
        Route::put('/{id}', [DiscountCodeController::class, 'update']);      // Update discount code (Admin)
        Route::delete('/{id}', [DiscountCodeController::class, 'destroy']);  // Delete discount code (Admin)
        
        // User routes
        Route::post('/validate', [DiscountCodeController::class, 'validateCode']); // Validate discount code (User)
    });

    // Bakong Payment routes
    Route::prefix('bakong')->group(function () {
        Route::post('/generate-qr', [BakongPaymentController::class, 'generateQRCode']);           // Generate QR for order
        Route::get('/payment-status/{orderId}', [BakongPaymentController::class, 'checkPaymentStatus']); // Check payment status
        Route::post('/verify-account', [BakongPaymentController::class, 'verifyAccount']);         // Verify Bakong account
        Route::post('/decode-qr', [BakongPaymentController::class, 'decodeQRCode']);               // Decode QR code
        Route::post('/renew-token', [BakongPaymentController::class, 'renewToken']);               // Renew API token (Admin)
    });

    // Author Payment Management (Bank + Bakong)
    Route::prefix('author/payment')->group(function () {
        Route::get('/info', [AuthorPaymentController::class, 'getPaymentInfo']);                  // Get all payment info
        Route::post('/bank', [AuthorPaymentController::class, 'updateBankInfo']);                 // Update bank info
        Route::post('/bakong', [AuthorPaymentController::class, 'updateBakongInfo']);             // Update Bakong info
        Route::post('/verify-bank', [AuthorPaymentController::class, 'verifyBankAccount']);       // Verify bank account
        Route::post('/verify-bakong', [AuthorPaymentController::class, 'verifyBakongAccount']);   // Verify Bakong account
        Route::post('/test-qr', [AuthorPaymentController::class, 'testQRGeneration']);            // Test QR generation
        Route::get('/banks', [AuthorPaymentController::class, 'getBanks']);                       // Get bank list
        Route::get('/bakong-banks', [AuthorPaymentController::class, 'getBakongBanks']);          // Get Bakong bank list
    });

    // Telegram Notification routes (Admin only)
    Route::prefix('telegram')->group(function () {
        Route::get('/bot-info', [TelegramController::class, 'getBotInfo']);                       // Get bot information
        Route::get('/updates', [TelegramController::class, 'getUpdates']);                        // Get updates (find chat ID)
        Route::post('/test-connection', [TelegramController::class, 'testConnection']);           // Test connection
        Route::post('/test-payment', [TelegramController::class, 'testPaymentNotification']);     // Test payment notification
        Route::post('/send-message', [TelegramController::class, 'sendMessage']);                 // Send custom message
    });

    // Payout routes
    Route::prefix('payouts')->group(function () {
        // Owner/Author routes
        Route::get('/balance', [PayoutController::class, 'getBalance']);                          // Get balance
        Route::post('/request', [PayoutController::class, 'requestPayout']);                      // Request payout
        Route::get('/my-payouts', [PayoutController::class, 'getMyPayouts']);                     // Get payout history
        Route::delete('/{payoutId}', [PayoutController::class, 'deleteMyPayout']);                // Delete my payout
        
        // Admin routes - OLD (keep for backward compatibility)
        Route::get('/pending', [PayoutController::class, 'getPendingPayouts']);                   // Get pending payouts (Admin)
        Route::get('/all', [PayoutController::class, 'getAllPayouts']);                           // Get all payouts (Admin)
        Route::post('/{payoutId}/process', [PayoutController::class, 'processPayout']);           // Process payout (Admin)
        Route::get('/statistics', [PayoutController::class, 'getStatistics']);                    // Get statistics (Admin)
    });

    // Admin Payout Management (NEW - Real-world payout system)
    Route::prefix('admin/payouts')->group(function () {
        Route::get('/authors', [AdminPayoutController::class, 'getAuthorsWithEarnings']);         // Get all authors with earnings
        Route::post('/initiate', [AdminPayoutController::class, 'initiatePayout']);               // Initiate payout to author
        Route::post('/{payoutId}/confirm', [AdminPayoutController::class, 'confirmPayout']);      // Confirm payout completed
        Route::post('/{payoutId}/cancel', [AdminPayoutController::class, 'cancelPayout']);        // Cancel payout
        Route::delete('/{payoutId}', [AdminPayoutController::class, 'deletePayout']);             // Delete payout record
        Route::get('/history', [AdminPayoutController::class, 'getPayoutHistory']);               // Get payout history
        Route::post('/generate-qr/{authorId}', [AdminPayoutController::class, 'generatePayoutQR']); // Generate QR for author payout
    });

});

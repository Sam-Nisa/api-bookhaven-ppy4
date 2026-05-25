<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Book;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    /**
     * Get user's cart with items and book details
     */
    public function index()
    {
        try {
            $user = Auth::user();

            $cart = Cart::with(['items.book' => function($query) {
                $query->select('id', 'title', 'author_name', 'price', 'discount_value', 'discount_type', 'images_url', 'stock');
            }])
            ->where('user_id', $user->id)
            ->first();

            if (!$cart || $cart->items->isEmpty()) {
                return response()->json([
                    'message' => 'Cart is empty',
                    'items' => [],
                    'total_items' => 0,
                    'subtotal' => 0
                ], 200);
            }

            // Calculate totals
            $subtotal = 0;
            $totalItems = 0;

            foreach ($cart->items as $item) {
                $book = $item->book;
                $price = floatval($book->price);
                $discountValue = floatval($book->discount_value ?? 0);
                $discountType = $book->discount_type;

                // Calculate discounted price
                $finalPrice = $price;
                if ($discountType === 'percentage' && $discountValue > 0) {
                    $finalPrice = $price - ($price * $discountValue / 100);
                } elseif ($discountType === 'fixed' && $discountValue > 0) {
                    $finalPrice = max(0, $price - $discountValue);
                }

                $subtotal += $finalPrice * $item->quantity;
                $totalItems += $item->quantity;
            }

            return response()->json([
                'message' => 'Cart retrieved successfully',
                'items' => $cart->items,
                'total_items' => $totalItems,
                'subtotal' => round($subtotal, 2)
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve cart',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add book to cart
     */
    public function addToCart(Request $request)
    {
        try {
            $user = Auth::user();

            $request->validate([
                'book_id' => 'required|exists:books,id',
                'quantity' => 'required|integer|min:1|max:10'
            ]);

            // Check if book is in stock
            $book = Book::findOrFail($request->book_id);
            if ($book->stock < $request->quantity) {
                return response()->json([
                    'error' => 'Insufficient stock',
                    'available_stock' => $book->stock
                ], 400);
            }

            DB::beginTransaction();

            // Get or create a cart for the user
            $cart = Cart::firstOrCreate(['user_id' => $user->id]);

            // Check if the item already exists
            $existingItem = CartItem::where('cart_id', $cart->id)
                                  ->where('book_id', $request->book_id)
                                  ->first();

            if ($existingItem) {
                // Check total quantity doesn't exceed stock
                $newQuantity = $existingItem->quantity + $request->quantity;
                if ($newQuantity > $book->stock) {
                    DB::rollBack();
                    return response()->json([
                        'error' => 'Total quantity exceeds available stock',
                        'current_in_cart' => $existingItem->quantity,
                        'available_stock' => $book->stock
                    ], 400);
                }

                $existingItem->quantity = $newQuantity;
                $existingItem->save();
                $item = $existingItem;
            } else {
                // Create new cart item
                $item = CartItem::create([
                    'cart_id' => $cart->id,
                    'book_id' => $request->book_id,
                    'quantity' => $request->quantity,
                ]);
            }

            DB::commit();

            // Return minimal response for faster performance
            return response()->json([
                'message' => 'Book added to cart successfully',
                'item' => [
                    'id' => $item->id,
                    'book_id' => $item->book_id,
                    'quantity' => $item->quantity
                ]
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to add book to cart',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update cart item quantity
     */
    public function updateQuantity(Request $request, $bookId)
    {
        try {
            $user = Auth::user();

            $request->validate([
                'quantity' => 'required|integer|min:1|max:10'
            ]);

            // Find the cart item
            $cart = Cart::where('user_id', $user->id)->first();
            if (!$cart) {
                return response()->json(['error' => 'Cart not found'], 404);
            }

            $item = CartItem::where('cart_id', $cart->id)
                          ->where('book_id', $bookId)
                          ->first();

            if (!$item) {
                return response()->json(['error' => 'Item not found in cart'], 404);
            }

            // Check stock availability
            $book = Book::findOrFail($bookId);
            if ($book->stock < $request->quantity) {
                return response()->json([
                    'error' => 'Insufficient stock',
                    'available_stock' => $book->stock
                ], 400);
            }

            $item->quantity = $request->quantity;
            $item->save();
            $item->load('book');

            return response()->json([
                'message' => 'Quantity updated successfully',
                'item' => $item
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update quantity',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove item from cart
     */
    public function removeItem($itemId)
    {
        try {
            $user = Auth::user();

            // Find the cart item and ensure it belongs to the user
            $item = CartItem::whereHas('cart', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })->findOrFail($itemId);

            $item->delete();

            return response()->json([
                'message' => 'Item removed from cart successfully'
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Item not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to remove item',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear the entire cart
     */
    public function clearCart()
    {
        try {
            $user = Auth::user();

            $cart = Cart::where('user_id', $user->id)->first();

            if ($cart) {
                $cart->items()->delete();
                return response()->json([
                    'message' => 'Cart cleared successfully'
                ], 200);
            }

            return response()->json([
                'message' => 'Cart was already empty'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to clear cart',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get cart item count
     */
    public function getCartCount()
    {
        try {
            $user = Auth::user();

            $cart = Cart::where('user_id', $user->id)->first();

            if (!$cart) {
                return response()->json(['count' => 0], 200);
            }

            $count = $cart->items()->sum('quantity');

            return response()->json(['count' => $count], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get cart count',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

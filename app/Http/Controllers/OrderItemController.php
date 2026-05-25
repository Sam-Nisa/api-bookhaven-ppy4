<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class OrderItemController extends Controller
{
    // Add item to order
    public function store(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'book_id' => 'required|exists:books,id',
            'quantity' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
        ]);

        // Make sure the order belongs to the user
        $order = $user->orders()->find($request->order_id);
        if (!$order) {
            return response()->json(['error' => 'Order not found or does not belong to you'], 403);
        }

        $item = OrderItem::create([
            'order_id' => $request->order_id,
            'book_id' => $request->book_id,
            'quantity' => $request->quantity,
            'price' => $request->price,
        ]);

        return response()->json([
            'message' => 'Item added to order successfully',
            'item' => $item
        ]);
    }

    // Get items for a specific order
    public function index($order_id)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $order = $user->orders()->find($order_id);
        if (!$order) {
            return response()->json(['error' => 'Order not found or does not belong to you'], 403);
        }

        $items = $order->items()->with('book')->get();
        return response()->json($items);
    }
}

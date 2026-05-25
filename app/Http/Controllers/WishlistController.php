<?php

namespace App\Http\Controllers;

use App\Models\Wishlist;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class WishlistController extends Controller
{
    // Add book to wishlist
    public function add(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $request->validate([
            'book_id' => 'required|exists:books,id',
        ]);

        $wishlist = Wishlist::firstOrCreate([
            'user_id' => $user->id,
            'book_id' => $request->book_id,
        ]);

        return response()->json([
            'message' => 'Book added to wishlist',
            'wishlist' => $wishlist
        ]);
    }

    // Remove book from wishlist
    public function remove($id)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $wishlist = Wishlist::where('user_id', $user->id)->where('book_id', $id)->first();

        if (!$wishlist) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $wishlist->delete();

        return response()->json(['message' => 'Book removed from wishlist']);
    }

    // Get all wishlist items for user
    public function index()
    {
        $user = JWTAuth::parseToken()->authenticate();

        $wishlists = Wishlist::with('book')->where('user_id', $user->id)->get();

        return response()->json($wishlists);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\InventoryLog;
use App\Models\Book;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class InventoryLogController extends Controller
{
    // List logs (Admin sees all, Author sees only their books)
    public function index()
    {
        $user = JWTAuth::parseToken()->authenticate();

        if ($user->role === 'admin') {
            $logs = InventoryLog::with('book')->get();
        } elseif ($user->role === 'author') {
            $logs = InventoryLog::whereHas('book', function($q) use ($user) {
                $q->where('author_id', $user->id);
            })->with('book')->get();
        } else {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json($logs);
    }

    // Create log (Author can only for their book, Admin for any)
    public function store(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $request->validate([
            'book_id' => 'required|exists:books,id',
            'change' => 'required|integer',
            'reason' => 'required|string|max:255',
        ]);

        $book = Book::find($request->book_id);

        // Author can only update their own books
        if ($user->role === 'author' && $book->author_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $log = InventoryLog::create($request->all());

        // Update book stock
        $book->stock += $request->change;
        $book->save();

        return response()->json([
            'message' => 'Inventory log created successfully',
            'log' => $log
        ]);
    }

    // Show single log
    public function show($id)
    {
        $log = InventoryLog::with('book')->find($id);
        if (!$log) return response()->json(['error' => 'Not found'], 404);

        $user = JWTAuth::parseToken()->authenticate();

        if ($user->role === 'author' && $log->book->author_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json($log);
    }

    // Delete log (Admin can delete any, Author can delete their own)
    public function destroy($id)
    {
        $log = InventoryLog::find($id);
        if (!$log) return response()->json(['error' => 'Not found'], 404);

        $user = JWTAuth::parseToken()->authenticate();

        if ($user->role === 'author' && $log->book->author_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $log->delete();
        return response()->json(['message' => 'Inventory log deleted successfully']);
    }
}

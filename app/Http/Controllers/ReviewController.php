<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class ReviewController extends Controller
{
    // Remove the constructor since we're handling auth in routes now

    /**
     * Get reviews for a specific book
     */
    public function getBookReviews(Request $request, $bookId)
    {
        $book = Book::findOrFail($bookId);
        
        // Build query with proper column checks
        $query = $book->reviews()->with(['user' => function($query) {
            // Only select columns that exist
            $query->select('id', 'name');
            
            // Check if avatar_url column exists before selecting it
            if (Schema::hasColumn('users', 'avatar_url')) {
                $query->addSelect('avatar_url');
            }
        }]);

        // Check if status column exists before filtering
        if (Schema::hasColumn('reviews', 'status')) {
            $query->where('status', 'approved');
        }

        $query->orderBy('created_at', 'desc');

        // Filter by rating if specified
        if ($request->has('rating') && $request->rating !== 'all') {
            $query->where('rating', $request->rating);
        }

        // Filter by verified purchases only if column exists
        if ($request->boolean('verified_only') && Schema::hasColumn('reviews', 'is_verified_purchase')) {
            $query->where('is_verified_purchase', true);
        }

        $reviews = $query->paginate(10);

        // Ensure rating stats exist and are properly formatted
        $ratingStats = [
            'average_rating' => (float) ($book->average_rating ?? 0),
            'total_reviews' => (int) ($book->total_reviews ?? 0),
            'rating_distribution' => $book->rating_distribution ?? [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0]
        ];

        return response()->json([
            'reviews' => $reviews,
            'rating_stats' => $ratingStats
        ]);
    }

    /**
     * Store a new review
     */
    public function createReview(Request $request, $bookId)
    {
        $book = Book::findOrFail($bookId);
        $userId = Auth::id();

        // Check if user already reviewed this book
        if ($book->hasUserReviewed($userId)) {
            return response()->json([
                'message' => 'You have already reviewed this book. Use PUT to update your review.'
            ], 409);
        }

        $request->validate([
            'rating' => 'required|integer|min:0|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        if ($request->rating == 0 && empty(trim($request->comment))) {
            return response()->json([
                'message' => 'Please provide a rating or write a comment.'
            ], 422);
        }

        // Check if user has purchased this book (optional verification)
        $isVerifiedPurchase = $this->checkVerifiedPurchase($userId, $bookId);

        // Build review data based on available columns
        $reviewData = [
            'user_id' => $userId,
            'book_id' => $bookId,
            'rating' => $request->rating,
            'comment' => $request->comment,
        ];

        // Add optional columns if they exist
        if (Schema::hasColumn('reviews', 'is_verified_purchase')) {
            $reviewData['is_verified_purchase'] = $isVerifiedPurchase;
        }

        if (Schema::hasColumn('reviews', 'status')) {
            $reviewData['status'] = 'approved'; // Auto-approve for now
        }

        $review = Review::create($reviewData);

        // Update book rating statistics
        $book->updateRatingStats();

        // Load user relationship for response
        $review->load(['user' => function($query) {
            $query->select('id', 'name');
            if (Schema::hasColumn('users', 'avatar_url')) {
                $query->addSelect('avatar_url');
            }
        }]);

        return response()->json([
            'message' => 'Review submitted successfully',
            'review' => $review,
            'rating_stats' => [
                'average_rating' => (float) $book->fresh()->average_rating,
                'total_reviews' => (int) $book->fresh()->total_reviews,
                'rating_distribution' => $book->fresh()->rating_distribution ?? [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0]
            ]
        ], 201);
    }

    /**
     * Update an existing review
     */
    public function updateReview(Request $request, $bookId, $reviewId)
    {
        $book = Book::findOrFail($bookId);
        $review = Review::findOrFail($reviewId);
        $userId = Auth::id();

        // Check ownership and edit window
        if (!$review->canBeEditedBy($userId)) {
            return response()->json([
                'message' => 'You can only edit your own review within 24 hours of posting.'
            ], 403);
        }

        $request->validate([
            'rating' => 'required|integer|min:0|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        if ($request->rating == 0 && empty(trim($request->comment))) {
            return response()->json([
                'message' => 'Please provide a rating or write a comment.'
            ], 422);
        }

        $review->update([
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        // Update book rating statistics
        $book->updateRatingStats();

        $review->load(['user' => function($query) {
            $query->select('id', 'name');
            if (Schema::hasColumn('users', 'avatar_url')) {
                $query->addSelect('avatar_url');
            }
        }]);

        return response()->json([
            'message' => 'Review updated successfully',
            'review' => $review,
            'rating_stats' => [
                'average_rating' => (float) $book->fresh()->average_rating,
                'total_reviews' => (int) $book->fresh()->total_reviews,
                'rating_distribution' => $book->fresh()->rating_distribution ?? [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0]
            ]
        ]);
    }

    /**
     * Delete a review
     */
    public function deleteReview($bookId, $reviewId)
    {
        $book = Book::findOrFail($bookId);
        $review = Review::findOrFail($reviewId);
        $userId = Auth::id();

        // Check ownership
        if (!$review->isOwner($userId)) {
            return response()->json([
                'message' => 'You can only delete your own review.'
            ], 403);
        }

        $review->delete();

        // Update book rating statistics
        $book->updateRatingStats();

        return response()->json([
            'message' => 'Review deleted successfully',
            'rating_stats' => [
                'average_rating' => (float) $book->fresh()->average_rating,
                'total_reviews' => (int) $book->fresh()->total_reviews,
                'rating_distribution' => $book->fresh()->rating_distribution ?? [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0]
            ]
        ]);
    }

    /**
     * Get current user's review for a book
     */
    public function getUserReview($bookId)
    {
        $book = Book::findOrFail($bookId);
        $userId = Auth::id();

        $review = $book->getUserReview($userId);

        if (!$review) {
            return response()->json(['review' => null]);
        }

        $review->load(['user' => function($query) {
            $query->select('id', 'name');
            if (Schema::hasColumn('users', 'avatar_url')) {
                $query->addSelect('avatar_url');
            }
        }]);

        return response()->json(['review' => $review]);
    }

    /**
     * Check if user has purchased the book (for verified purchase badge)
     */
    private function checkVerifiedPurchase($userId, $bookId)
    {
        // This would check your orders/purchases table
        // For now, returning false - implement based on your order system
        return false;
        
        // Example implementation:
        // return Order::where('user_id', $userId)
        //     ->whereHas('items', function($query) use ($bookId) {
        //         $query->where('book_id', $bookId);
        //     })
        //     ->where('status', 'completed')
        //     ->exists();
    }
}
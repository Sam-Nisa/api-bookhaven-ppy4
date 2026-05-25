<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Book;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Services\ImageKitService;

class BookController extends Controller
{
    protected $imageKit;

public function __construct(ImageKitService $imageKit)
{
    $this->imageKit = $imageKit;
}
    // ✅ List all books (any user) - Only show approved books
    public function index()
    {
        $perPage = request('per_page', 12); // Default 12 books per page
        $paginate = request('paginate', 'false'); // Check if pagination is requested
        
        $query = Book::with(['author:id,name,email,role', 'genre:id,name,slug'])
            ->select('id', 'title', 'author_id', 'genre_id', 'price', 'stock', 
                     'cover_image_url', 'images_url', 'description', 'status',
                     'discount_type', 'discount_value', 'average_rating', 'total_reviews',
                     'created_at', 'updated_at')
            ->where('status', 'approved'); // Only show approved books on public interface

        // Search functionality - search by title, description, or author name (case-insensitive)
        if (request()->filled('search')) {
            $search = request('search');
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(title) LIKE ?', ['%' . strtolower($search) . '%'])
                  ->orWhereRaw('LOWER(description) LIKE ?', ['%' . strtolower($search) . '%'])
                  ->orWhereHas('author', function ($authorQuery) use ($search) {
                      $authorQuery->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%']);
                  });
            });
        }

        // Filter by genre name instead of slug
        if (request()->has('genre')) {
            $query->whereHas('genre', function ($q) {
                $q->where('slug', request('genre'));
            });
        }

        // Filter by author_id
        if (request()->filled('author_id')) {
            $query->where('author_id', request('author_id'));
        }

        // Order by created_at desc for newest first
        $query->orderBy('created_at', 'desc');

        // Return paginated or all results
        if ($paginate === 'true') {
            $books = $query->paginate($perPage);
        } else {
            $books = $query->get();
        }

        // Add author_name to each book for easier frontend access
        $books->each(function ($book) {
            $book->author_name = $book->author->name ?? 'Unknown Author';
        });

        return response()->json($books);
    }

    // ✅ Admin: List only admin-created books with filters
    public function adminIndex(Request $request)
    {
        $user = Auth::user();
        
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        // Only show books created by admin users (role = 'admin')
        $query = Book::with(['author', 'genre'])
            ->whereHas('author', function ($q) {
                $q->where('role', 'admin');
            });

        // Filter by search term (book title only)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('title', 'like', "%{$search}%");
        }

        // Filter by genre
        if ($request->filled('genre_id')) {
            $query->where('genre_id', $request->genre_id);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by price range
        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // Default sort by created_at desc
        $query->orderBy('created_at', 'desc');

        $books = $query->get();

        return response()->json($books);
    }

    // ✅ Author: List only author's own books (all statuses) with filters
    public function authorIndex(Request $request)
    {
        $user = Auth::user();
        
        if ($user->role !== 'author') {
            return response()->json(['message' => 'Unauthorized. Author access required.'], 403);
        }

        // Show all books created by this author (including pending)
        $query = Book::with(['author', 'genre'])
            ->where('author_id', $user->id);

        // Filter by search term (book title only)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('title', 'like', "%{$search}%");
        }

        // Filter by genre
        if ($request->filled('genre_id')) {
            $query->where('genre_id', $request->genre_id);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by price range
        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // Default sort by created_at desc
        $query->orderBy('created_at', 'desc');

        $books = $query->get();

        return response()->json($books);
    }

    // ✅ Show a single book (any user) - Only show if approved - Optimized
    public function show($id)
    {
        $book = Book::with(['author:id,name,email,role', 'genre:id,name,slug'])
            ->select('id', 'title', 'author_id', 'genre_id', 'price', 'stock', 
                     'cover_image_url', 'images_url', 'pdf_file_url', 'description', 
                     'status', 'discount_type', 'discount_value', 'average_rating', 
                     'total_reviews', 'created_at', 'updated_at', 'about_author',
                     'publisher', 'page_count', 'publication_date')
            ->where('status', 'approved') // Only show approved books on public interface
            ->find($id);

        if (!$book) {
            return response()->json(['message' => 'Book not found or not approved'], 404);
        }

        // Add author name for easier access
        $book->author_name = $book->author->name ?? 'Unknown Author';

        return response()->json($book);
    }

    // ✅ Create a new book (only admin or author)
    public function store(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->role, ['admin', 'author'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'genre_id' => 'required|exists:genres,id',
            'price' => 'required|numeric',
            'stock' => 'required|integer',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048', // multiple images
            'images_url' => 'nullable|array', // Pre-uploaded image URLs
            'images_url.*' => 'nullable|url',
            'pdf_file' => 'nullable|mimes:pdf|max:10000', // PDF max 10MB
            'pdf_file_url' => 'nullable|url', // Pre-uploaded PDF URL
            'description' => 'nullable|string',
            'status' => 'nullable|in:pending,approved',
            'discount_type' => 'nullable|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'publication_date' => 'nullable|date',
            'page_count' => 'nullable|integer|min:1',
            'about_author' => 'nullable|string',
            'publisher' => 'nullable|string',
            'author_name' => 'nullable|string',
        ]);

        $data = $request->all();
        
        // Each user creates books for themselves only
        $data['author_id'] = $user->id;
        
        // Remove user_id from data as it's not needed
        unset($data['user_id']);
        
        $data['status'] = $data['status'] ?? 'pending';

        // Handle cover image (file upload)
        if ($request->hasFile('cover_image')) {
            $file = $request->file('cover_image');
            $upload = $this->imageKit->upload(
                $file->getPathname(),
                time().'_'.$file->getClientOriginalName(),
                '/books/cover'
            );
            $data['cover_image_url'] = $upload->result->url;
            unset($data['cover_image']);
        }

        // Handle multiple images
        if ($request->hasFile('images')) {
            // Direct file uploads
            $imageUrls = [];
            foreach ($request->file('images') as $image) {
                $upload = $this->imageKit->upload(
                    $image->getPathname(),
                    time().'_'.$image->getClientOriginalName(),
                    '/books/images'
                );
                $imageUrls[] = $upload->result->url;
            }
            $data['images_url'] = $imageUrls;
            unset($data['images']);
        } elseif ($request->has('images_url') && is_array($request->images_url)) {
            // Pre-uploaded URLs
            $data['images_url'] = $request->images_url;
        }

        // Handle PDF
        if ($request->hasFile('pdf_file')) {
            // Direct file upload
            $pdf = $request->file('pdf_file');
            $upload = $this->imageKit->upload(
                $pdf->getPathname(),
                time().'_'.$pdf->getClientOriginalName(),
                '/books/pdfs'
            );
            $data['pdf_file_url'] = $upload->result->url;
            unset($data['pdf_file']);
        } elseif ($request->has('pdf_file_url')) {
            // Pre-uploaded URL
            $data['pdf_file_url'] = $request->pdf_file_url;
        }

        $book = Book::create($data);

        // Load relationships
        $book->load(['author', 'genre']);

        return response()->json($book, 201);
    }

    // ✅ Update a book (only own books)
    public function update(Request $request, $id)
    {
        $book = Book::find($id);
        if (!$book) return response()->json(['message' => 'Book not found'], 404);

        $user = Auth::user();
        
        // Users can only edit their own books (no cross-role editing)
        if ($book->author_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized. You can only edit your own books.'], 403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'genre_id' => 'required|exists:genres,id',
            'price' => 'required|numeric',
            'stock' => 'required|integer',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'cover_image_url' => 'nullable|url',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048', // multiple images
            'images_url' => 'nullable|array', // Pre-uploaded image URLs
            'images_url.*' => 'nullable|url',
            'pdf_file' => 'nullable|mimes:pdf|max:10000', // PDF max 10MB
            'pdf_file_url' => 'nullable|url', // Pre-uploaded PDF URL
            'description' => 'nullable|string',
            'status' => 'nullable|in:pending,approved',
            'discount_type' => 'nullable|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'publication_date' => 'nullable|date',
            'page_count' => 'nullable|integer|min:1',
            'about_author' => 'nullable|string',
            'publisher' => 'nullable|string',
            'author_name' => 'nullable|string',
        ]);

        $data = $request->all();

        // Remove user_id from data as it's not needed (users can only edit their own books)
        unset($data['user_id']);

        // Handle cover image (file upload)
        if ($request->hasFile('cover_image')) {
            $file = $request->file('cover_image');
            $upload = $this->imageKit->upload(
                $file->getPathname(),
                time().'_'.$file->getClientOriginalName(),
                '/books/cover'
            );
            $data['cover_image_url'] = $upload->result->url;
            unset($data['cover_image']);
        } elseif ($request->has('cover_image_url')) {
            // Pre-uploaded URL
            $data['cover_image_url'] = $request->cover_image_url;
        }

        // Handle multiple images
        if ($request->hasFile('images')) {
            // Direct file uploads
            $imageUrls = [];
            foreach ($request->file('images') as $image) {
                $upload = $this->imageKit->upload(
                    $image->getPathname(),
                    time().'_'.$image->getClientOriginalName(),
                    '/books/images'
                );
                $imageUrls[] = $upload->result->url;
            }
            $data['images_url'] = $imageUrls;
            unset($data['images']);
        } elseif ($request->has('images_url') && is_array($request->images_url)) {
            // Pre-uploaded URLs
            $data['images_url'] = $request->images_url;
        }

        // Handle PDF
        if ($request->hasFile('pdf_file')) {
            // Direct file upload
            $pdf = $request->file('pdf_file');
            $upload = $this->imageKit->upload(
                $pdf->getPathname(),
                time().'_'.$pdf->getClientOriginalName(),
                '/books/pdfs'
            );
            $data['pdf_file_url'] = $upload->result->url;
            unset($data['pdf_file']);
        } elseif ($request->has('pdf_file_url')) {
            // Pre-uploaded URL
            $data['pdf_file_url'] = $request->pdf_file_url;
        }

        $book->update($data);

        // Load relationships
        $book->load(['author', 'genre']);

        return response()->json($book);
    }

    // ✅ Delete a book (only own books)
    public function destroy($id)
    {
        $book = Book::find($id);
        if (!$book) return response()->json(['message' => 'Book not found'], 404);

        $user = Auth::user();
        
        // Users can only delete their own books (no cross-role deletion)
        if ($book->author_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized. You can only delete your own books.'], 403);
        }

        // Note: ImageKit files are managed externally, so we don't need to delete them here
        // In a production environment, you might want to implement ImageKit file deletion
        
        $book->delete();

        return response()->json(['message' => 'Book deleted successfully']);
    }

    /**
     * Get best seller books (books with 3+ sales)
     */
    public function bestSellers(Request $request)
    {
        try {
            $limit = $request->get('limit', 20); // Default limit of 20 books
            $perPage = $request->get('per_page', 12); // For pagination
            $paginate = $request->get('paginate', 'false');

            // Use a subquery approach to avoid GROUP BY issues
            $bestSellerIds = DB::table('books')
                ->select('books.id')
                ->join('order_items', 'books.id', '=', 'order_items.book_id')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('orders.status', 'paid')
                ->where('orders.payment_status', 'completed')
                ->where('books.status', 'approved')
                ->groupBy('books.id')
                ->havingRaw('SUM(order_items.quantity) >= 3')
                ->pluck('books.id');

            if ($bestSellerIds->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No best seller books found',
                    'data' => $paginate === 'true' ? ['data' => [], 'total' => 0, 'per_page' => $perPage, 'current_page' => 1, 'last_page' => 1] : []
                ]);
            }

            // Now get the books with their sales data
            $query = Book::whereIn('id', $bestSellerIds)
                ->with(['author:id,name,email,role', 'genre:id,name,slug']);

            // Add sales count using a separate query for each book
            $query->selectRaw('books.*, (
                SELECT SUM(order_items.quantity) 
                FROM order_items 
                INNER JOIN orders ON order_items.order_id = orders.id 
                WHERE order_items.book_id = books.id 
                AND orders.status = "paid" 
                AND orders.payment_status = "completed"
            ) as total_sold');

            // Search functionality
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->whereRaw('LOWER(title) LIKE ?', ['%' . strtolower($search) . '%'])
                      ->orWhereRaw('LOWER(description) LIKE ?', ['%' . strtolower($search) . '%']);
                });
            }

            // Filter by genre
            if ($request->filled('genre')) {
                $query->whereHas('genre', function ($q) use ($request) {
                    $q->where('slug', $request->genre);
                });
            }

            // Order by sales count
            $query->orderByRaw('(
                SELECT SUM(order_items.quantity) 
                FROM order_items 
                INNER JOIN orders ON order_items.order_id = orders.id 
                WHERE order_items.book_id = books.id 
                AND orders.status = "paid" 
                AND orders.payment_status = "completed"
            ) DESC');

            // Return paginated or limited results
            if ($paginate === 'true') {
                $books = $query->paginate($perPage);
            } else {
                if ($limit) {
                    $query->limit($limit);
                }
                $books = $query->get();
            }

            // Add author_name to each book for easier frontend access
            if ($paginate === 'true') {
                $books->getCollection()->each(function ($book) {
                    $book->author_name = $book->author->name ?? 'Unknown Author';
                });
            } else {
                $books->each(function ($book) {
                    $book->author_name = $book->author->name ?? 'Unknown Author';
                });
            }

            return response()->json([
                'success' => true,
                'message' => 'Best seller books retrieved successfully',
                'data' => $books
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve best seller books',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get best sellers stats for dashboard
     */
    public function bestSellersStats()
    {
        try {
            // Count total best sellers using a direct count approach
            $bestSellerIds = DB::table('books')
                ->select('books.id')
                ->join('order_items', 'books.id', '=', 'order_items.book_id')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('orders.status', 'paid')
                ->where('orders.payment_status', 'completed')
                ->where('books.status', 'approved')
                ->groupBy('books.id')
                ->havingRaw('SUM(order_items.quantity) >= 3')
                ->pluck('books.id');

            $totalBestSellers = $bestSellerIds->count();

            // Get top best seller
            $topBestSellerData = DB::table('books')
                ->select('books.id', 'books.title', 'books.author_id')
                ->selectRaw('SUM(order_items.quantity) as total_sold')
                ->join('order_items', 'books.id', '=', 'order_items.book_id')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('orders.status', 'paid')
                ->where('orders.payment_status', 'completed')
                ->where('books.status', 'approved')
                ->groupBy('books.id', 'books.title', 'books.author_id')
                ->havingRaw('SUM(order_items.quantity) >= 3')
                ->orderByRaw('SUM(order_items.quantity) DESC')
                ->first();

            $topBestSeller = null;
            if ($topBestSellerData) {
                $author = \App\Models\User::find($topBestSellerData->author_id);
                $topBestSeller = [
                    'title' => $topBestSellerData->title,
                    'author' => $author ? $author->name : 'Unknown',
                    'total_sold' => $topBestSellerData->total_sold
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'total_best_sellers' => $totalBestSellers,
                    'top_best_seller' => $topBestSeller
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve best sellers stats',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

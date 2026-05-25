<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Book;
use App\Models\Genre;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Exception;

class AuthorDashboardController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user || $user->role !== 'author') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Author access required.'
                ], 403);
            }

            // Get author's books
            $authorBooks = Book::where('author_id', $user->id)->get();
            
            // Basic counts
            $totalBooks = $authorBooks->count();
            $publishedBooks = $authorBooks->where('status', 'approved')->count();
            $pendingBooks = $authorBooks->where('status', 'pending')->count();
            $rejectedBooks = $authorBooks->where('status', 'rejected')->count();

            // Revenue calculation (mock data - replace with actual sales data)
            $totalRevenue = $authorBooks->sum(function($book) {
                return $book->price * rand(0, 50); // Mock sales count
            });

            // Weekly sales trend (last 7 days)
            $weeklySales = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = now()->subDays($i)->format('Y-m-d');
                
                // Mock sales data - replace with actual order data
                $sales = rand(10, 100);
                
                $weeklySales[] = [
                    'day' => now()->subDays($i)->format('D'),
                    'sales' => $sales
                ];
            }

            // Genre distribution for author's books
            $genreData = [];
            try {
                $rawGenreData = DB::table('books')
                    ->join('genres', 'books.genre_id', '=', 'genres.id')
                    ->where('books.author_id', $user->id)
                    ->select('genres.name', DB::raw('count(*) as value'))
                    ->groupBy('genres.name')
                    ->get();

                $colors = ['#137fec', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444'];
                foreach ($rawGenreData as $index => $item) {
                    $genreData[] = [
                        'name' => $item->name,
                        'value' => $item->value,
                        'color' => $colors[$index % count($colors)]
                    ];
                }
            } catch (Exception $e) {
                $genreData = [];
            }

            // Recent books with additional data
            $recentBooks = Book::where('author_id', $user->id)
                ->with('genre')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function($book) {
                    return [
                        'id' => $book->id,
                        'title' => $book->title,
                        'genre' => $book->genre ? $book->genre->name : 'N/A',
                        'price' => $book->price,
                        'status' => $book->status,
                        'stock' => $book->stock,
                        'cover_image_url' => $book->cover_image_url,
                        'sales_count' => rand(0, 50), // Mock data
                        'created_at' => $book->created_at
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'totalBooks' => $totalBooks,
                    'publishedBooks' => $publishedBooks,
                    'pendingBooks' => $pendingBooks,
                    'rejectedBooks' => $rejectedBooks,
                    'totalRevenue' => round($totalRevenue, 2),
                    'averageRating' => 4.2, // Mock data
                    'totalSales' => $authorBooks->sum(function($book) {
                        return rand(0, 50); // Mock sales count
                    }),
                    'weeklySales' => $weeklySales,
                    'genreData' => $genreData,
                    'recentBooks' => $recentBooks,
                    'booksGrowth' => '15%', // Mock growth data
                    'salesGrowth' => '22%',
                    'revenueGrowth' => '18%'
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
}
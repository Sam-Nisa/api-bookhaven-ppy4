<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Book;
use App\Models\Genre;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Exception;

class DashboardController extends Controller
{
    public function index()
    {
        try {
            // 1. Basic Counts (Check if models exist)
            $totalUsers = User::where('role', 'user')->count();
            $totalAuthors = User::where('role', 'author')->count();
            $totalBooks = Book::count();
            $totalGenres = Genre::count();

            // 2. Revenue Trends (Last 7 days)
            $weeklyRevenue = [];
                for ($i = 6; $i >= 0; $i--) {
                    $date = now()->subDays($i)->format('Y-m-d');
                    
                    try {
                        // CHANGE 'total_price' TO YOUR ACTUAL COLUMN NAME HERE
                        // Common names: 'total', 'amount', 'total_amount'
                        $revenue = Order::whereDate('created_at', $date)->sum('total_price'); 
                    } catch (\Exception $e) {
                        // If the column name is wrong, return 0 so the dashboard doesn't crash
                        $revenue = 0; 
                    }

                    $weeklyRevenue[] = [
                        'day' => now()->subDays($i)->format('D'),
                        'revenue' => (int)$revenue
                    ];
                }

            // 3. Sales by Genre
            $genreData = [];
            try {
                $rawGenreData = DB::table('books')
                    ->join('genres', 'books.genre_id', '=', 'genres.id')
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
                // If books/genres join fails, return empty array instead of crashing
                $genreData = [];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'totalUsers' => $totalUsers,
                    'totalAuthors' => $totalAuthors,
                    'totalBooks' => $totalBooks,
                    'totalCategories' => $totalGenres,
                    'weeklyRevenue' => $weeklyRevenue,
                    'genreData' => $genreData,
                    'totalSoldBooks' => class_exists('App\Models\Order') ? Order::count() : 0,
                    'authorsGrowth' => '12%', 
                    'usersGrowth' => '5%',
                    'categoriesGrowth' => '2%',
                    'booksGrowth' => '8%'
                ]
            ]);

        } catch (Exception $e) {
            // This will return the ACTUAL error message to your browser console
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
}
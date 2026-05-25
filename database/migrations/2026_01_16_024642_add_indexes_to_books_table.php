<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            // Single column indexes
            $table->index('status', 'books_status_index');
            $table->index('genre_id', 'books_genre_id_index');
            $table->index('author_id', 'books_author_id_index');
            $table->index('created_at', 'books_created_at_index');
            $table->index('price', 'books_price_index');
            
            // Composite indexes for common queries
            $table->index(['status', 'genre_id'], 'books_status_genre_index');
            $table->index(['status', 'author_id'], 'books_status_author_index');
            $table->index(['status', 'created_at'], 'books_status_created_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropIndex('books_status_index');
            $table->dropIndex('books_genre_id_index');
            $table->dropIndex('books_author_id_index');
            $table->dropIndex('books_created_at_index');
            $table->dropIndex('books_price_index');
            $table->dropIndex('books_status_genre_index');
            $table->dropIndex('books_status_author_index');
            $table->dropIndex('books_status_created_index');
        });
    }
};

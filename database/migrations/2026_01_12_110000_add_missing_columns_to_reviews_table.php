<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            // Add missing columns
            $table->boolean('is_verified_purchase')->default(false)->after('comment')->comment('Did user actually buy this book?');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('approved')->after('is_verified_purchase');
            
            // Add indexes for performance
            $table->index(['book_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index('rating');
            
            // Add unique constraint to ensure one review per user per book
            $table->unique(['user_id', 'book_id']);
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['book_id', 'status']);
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['rating']);
            $table->dropUnique(['user_id', 'book_id']);
            
            // Drop columns
            $table->dropColumn(['is_verified_purchase', 'status']);
        });
    }
};
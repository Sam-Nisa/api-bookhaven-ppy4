<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->decimal('average_rating', 3, 2)->default(0)->after('author_name')->comment('Calculated average rating');
            $table->integer('total_reviews')->default(0)->after('average_rating')->comment('Total number of reviews');
            $table->json('rating_distribution')->nullable()->after('total_reviews')->comment('Distribution of 1-5 star ratings');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['average_rating', 'total_reviews', 'rating_distribution']);
        });
    }
};
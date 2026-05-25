<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('genre_id')->constrained('genres')->cascadeOnDelete();
            $table->decimal('price', 10, 2);
            $table->integer('stock');

            // Main cover image (optional)
            $table->string('cover_image')->nullable();

            // NEW: multiple images (JSON) and PDF
            $table->json('images')->nullable();      // store 1–5 images
            $table->string('pdf_file')->nullable();   // store PDF path

            $table->text('description')->nullable();

            // NEW FIELDS
            $table->date('publication_date')->nullable();
            $table->integer('page_count')->nullable();
            $table->text('about_author')->nullable();
            $table->text('publisher')->nullable();
            $table->text('author_name')->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};

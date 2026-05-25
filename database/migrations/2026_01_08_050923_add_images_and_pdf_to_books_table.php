<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('books', 'images')) {
            Schema::table('books', function (Blueprint $table) {
                $table->json('images')->nullable()->after('cover_image'); // multiple images
            });
        }

        if (!Schema::hasColumn('books', 'pdf_file')) {
            Schema::table('books', function (Blueprint $table) {
                $table->string('pdf_file')->nullable()->after('images');  // PDF file
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('books', 'images')) {
            Schema::table('books', function (Blueprint $table) {
                $table->dropColumn('images');
            });
        }

        if (Schema::hasColumn('books', 'pdf_file')) {
            Schema::table('books', function (Blueprint $table) {
                $table->dropColumn('pdf_file');
            });
        }
    }
};

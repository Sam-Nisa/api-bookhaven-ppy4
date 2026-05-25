<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->json('images')->nullable()->after('cover_image'); // multiple images
            $table->string('pdf_file')->nullable()->after('images');  // PDF file
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn('images');
            $table->dropColumn('pdf_file');
        });
    }
};

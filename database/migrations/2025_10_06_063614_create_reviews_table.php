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
    Schema::create('reviews', function (Blueprint $table) {
        $table->id(); // auto-incrementing primary key
        $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
        $table->foreignId('book_id')->constrained('books')->cascadeOnDelete();
        $table->integer('rating')->comment('1–5 stars');
        $table->text('comment');
        $table->timestamps(); // includes created_at and updated_at
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};

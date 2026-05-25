<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('genres', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique(); 
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('image')->nullable();
            $table->timestamps();

            // $table->foreign('parent_id')
            //       ->references('id')
            //       ->on('genres')
            //       ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('genres');
    }
};
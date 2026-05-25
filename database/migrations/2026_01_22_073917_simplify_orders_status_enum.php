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
        Schema::table('orders', function (Blueprint $table) {
            // Simplify status enum - only keep essential statuses
            $table->enum('status', ['paid', 'processing', 'shipped', 'delivered', 'cancelled'])
                  ->default('paid')
                  ->change();
            
            // Simplify payment_status enum - only keep essential statuses  
            $table->enum('payment_status', ['completed', 'failed'])
                  ->default('completed')
                  ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Revert to previous enum values
            $table->enum('status', ['pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled', 'temporary'])
                  ->default('pending')
                  ->change();
                  
            $table->enum('payment_status', ['pending', 'completed'])
                  ->default('pending')
                  ->change();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, update all existing 'paid' orders to 'processing'
        DB::table('orders')->where('status', 'paid')->update(['status' => 'processing']);
        
        // Update enum to have 'processing' as default (no 'pending' or 'paid')
        DB::statement("ALTER TABLE `orders` MODIFY COLUMN `status` ENUM('processing', 'completed', 'shipped', 'delivered', 'cancelled') DEFAULT 'processing'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to previous enum
        DB::statement("ALTER TABLE `orders` MODIFY COLUMN `status` ENUM('paid', 'processing', 'completed', 'shipped', 'delivered', 'cancelled') DEFAULT 'paid'");
    }
};

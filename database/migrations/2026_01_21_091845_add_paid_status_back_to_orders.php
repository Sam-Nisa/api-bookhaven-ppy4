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
        // Add 'paid' status back to enum and set as default
        DB::statement("ALTER TABLE `orders` MODIFY COLUMN `status` ENUM('paid', 'processing', 'completed', 'shipped', 'delivered', 'cancelled') DEFAULT 'paid'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'paid' status
        DB::statement("ALTER TABLE `orders` MODIFY COLUMN `status` ENUM('processing', 'completed', 'shipped', 'delivered', 'cancelled') DEFAULT 'processing'");
    }
};

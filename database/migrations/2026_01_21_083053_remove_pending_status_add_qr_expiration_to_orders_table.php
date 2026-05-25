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
        // First, update all existing 'pending' orders to 'paid'
        DB::table('orders')->where('status', 'pending')->update(['status' => 'paid']);
        
        Schema::table('orders', function (Blueprint $table) {
            // Add QR expiration timestamp only if it doesn't exist
            if (!Schema::hasColumn('orders', 'qr_expires_at')) {
                $table->timestamp('qr_expires_at')->nullable()->after('payment_qr_md5');
            }
        });

        // Remove 'pending' status from enum, keep only 'paid' and other statuses
        DB::statement("ALTER TABLE `orders` MODIFY COLUMN `status` ENUM('paid', 'processing', 'completed', 'shipped', 'delivered', 'cancelled') DEFAULT 'paid'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Remove QR expiration timestamp
            $table->dropColumn('qr_expires_at');
        });

        // Restore original enum with 'pending'
        DB::statement("ALTER TABLE `orders` MODIFY COLUMN `status` ENUM('pending', 'paid', 'processing', 'completed', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending'");
    }
};

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
        // Update existing order statuses to match new enum values
        DB::table('orders')->where('status', 'pending')->update(['status' => 'paid']);
        DB::table('orders')->where('status', 'temporary')->update(['status' => 'paid']);
        
        // Update payment_status to match new enum values
        DB::table('orders')->where('payment_status', 'pending')->update(['payment_status' => 'completed']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is irreversible as we don't know the original values
        // But we can set them back to a default state if needed
    }
};

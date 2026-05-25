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
            // Add payment_status field if it doesn't exist
            if (!Schema::hasColumn('orders', 'payment_status')) {
                $table->enum('payment_status', ['pending', 'completed', 'failed'])
                      ->default('pending')
                      ->after('payment_method');
            }
            
            // Add payment_transaction_id field if it doesn't exist
            if (!Schema::hasColumn('orders', 'payment_transaction_id')) {
                $table->string('payment_transaction_id')->nullable()->after('payment_status');
            }
            
            // Add payment_qr_code field if it doesn't exist
            if (!Schema::hasColumn('orders', 'payment_qr_code')) {
                $table->text('payment_qr_code')->nullable()->after('payment_transaction_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'payment_transaction_id', 'payment_qr_code']);
        });
    }
};
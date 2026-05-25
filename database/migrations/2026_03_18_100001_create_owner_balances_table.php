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
        Schema::create('owner_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');
            $table->decimal('available_balance', 12, 2)->default(0)->comment('Available for payout');
            $table->decimal('pending_balance', 12, 2)->default(0)->comment('From unpaid orders');
            $table->decimal('total_earned', 12, 2)->default(0)->comment('Lifetime earnings');
            $table->decimal('total_withdrawn', 12, 2)->default(0)->comment('Total paid out');
            $table->timestamps();
            
            $table->index('owner_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('owner_balances');
    }
};

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
        Schema::table('users', function (Blueprint $table) {
            // Bakong payment information for authors
            $table->string('bakong_account_id')->nullable()->after('avatar_url');
            $table->string('bakong_merchant_name')->nullable()->after('bakong_account_id');
            $table->string('bakong_merchant_city')->nullable()->after('bakong_merchant_name');
            $table->string('bakong_merchant_id')->nullable()->after('bakong_merchant_city');
            $table->string('bakong_acquiring_bank')->nullable()->after('bakong_merchant_id');
            $table->string('bakong_mobile_number')->nullable()->after('bakong_acquiring_bank');
            $table->boolean('bakong_account_verified')->default(false)->after('bakong_mobile_number');
            $table->timestamp('bakong_verified_at')->nullable()->after('bakong_account_verified');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'bakong_account_id',
                'bakong_merchant_name',
                'bakong_merchant_city',
                'bakong_merchant_id',
                'bakong_acquiring_bank',
                'bakong_mobile_number',
                'bakong_account_verified',
                'bakong_verified_at'
            ]);
        });
    }
};
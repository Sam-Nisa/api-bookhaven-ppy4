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
            // Check if columns don't exist before adding them
            if (!Schema::hasColumn('users', 'bank_name')) {
                $table->string('bank_name')->nullable()->after('avatar_url');
            }
            if (!Schema::hasColumn('users', 'bank_account_number')) {
                $table->string('bank_account_number')->nullable()->after('bank_name');
            }
            if (!Schema::hasColumn('users', 'bank_account_name')) {
                $table->string('bank_account_name')->nullable()->after('bank_account_number');
            }
            if (!Schema::hasColumn('users', 'bank_branch')) {
                $table->string('bank_branch')->nullable()->after('bank_account_name');
            }
            if (!Schema::hasColumn('users', 'payment_method')) {
                $table->string('payment_method')->default('bank')->after('bank_branch');
            }
            if (!Schema::hasColumn('users', 'payment_verified')) {
                $table->boolean('payment_verified')->default(false)->after('payment_method');
            }
            if (!Schema::hasColumn('users', 'payment_verified_at')) {
                $table->timestamp('payment_verified_at')->nullable()->after('payment_verified');
            }
            
            // Bakong fields
            if (!Schema::hasColumn('users', 'bakong_account_id')) {
                $table->string('bakong_account_id')->nullable()->after('payment_verified_at');
            }
            if (!Schema::hasColumn('users', 'bakong_merchant_name')) {
                $table->string('bakong_merchant_name')->nullable()->after('bakong_account_id');
            }
            if (!Schema::hasColumn('users', 'bakong_merchant_city')) {
                $table->string('bakong_merchant_city')->nullable()->after('bakong_merchant_name');
            }
            if (!Schema::hasColumn('users', 'bakong_merchant_id')) {
                $table->string('bakong_merchant_id')->nullable()->after('bakong_merchant_city');
            }
            if (!Schema::hasColumn('users', 'bakong_acquiring_bank')) {
                $table->string('bakong_acquiring_bank')->nullable()->after('bakong_merchant_id');
            }
            if (!Schema::hasColumn('users', 'bakong_mobile_number')) {
                $table->string('bakong_mobile_number')->nullable()->after('bakong_acquiring_bank');
            }
            if (!Schema::hasColumn('users', 'bakong_account_verified')) {
                $table->boolean('bakong_account_verified')->default(false)->after('bakong_mobile_number');
            }
            if (!Schema::hasColumn('users', 'bakong_verified_at')) {
                $table->timestamp('bakong_verified_at')->nullable()->after('bakong_account_verified');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = [
                'bank_name',
                'bank_account_number',
                'bank_account_name',
                'bank_branch',
                'payment_method',
                'payment_verified',
                'payment_verified_at',
                'bakong_account_id',
                'bakong_merchant_name',
                'bakong_merchant_city',
                'bakong_merchant_id',
                'bakong_acquiring_bank',
                'bakong_mobile_number',
                'bakong_account_verified',
                'bakong_verified_at',
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

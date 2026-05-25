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
        Schema::create('discount_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // The discount code (e.g., "SAVE10")
            $table->string('name'); // Display name (e.g., "10% Off Sale")
            $table->text('description')->nullable(); // Description of the discount
            $table->enum('type', ['percentage', 'fixed']); // percentage or fixed amount
            $table->decimal('value', 10, 2); // discount value (10 for 10% or $10)
            $table->decimal('minimum_amount', 10, 2)->nullable(); // minimum cart amount to apply
            $table->decimal('maximum_discount', 10, 2)->nullable(); // maximum discount amount (for percentage)
            $table->integer('usage_limit')->nullable(); // total usage limit (null = unlimited)
            $table->integer('usage_limit_per_user')->nullable(); // per user usage limit
            $table->integer('used_count')->default(0); // how many times it's been used
            $table->datetime('starts_at')->nullable(); // when discount becomes active
            $table->datetime('expires_at')->nullable(); // when discount expires
            $table->boolean('is_active')->default(true); // admin can enable/disable
            $table->unsignedBigInteger('created_by'); // admin who created it
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->index(['code', 'is_active']);
            $table->index(['starts_at', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discount_codes');
    }
};

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
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('commission_rate', 5, 2)->default(10.00)->after('total')->comment('Commission percentage');
            $table->decimal('commission_amount', 10, 2)->default(0)->after('commission_rate')->comment('Commission amount in currency');
            $table->decimal('owner_earnings', 10, 2)->default(0)->after('commission_amount')->comment('Amount owner receives after commission');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['commission_rate', 'commission_amount', 'owner_earnings']);
        });
    }
};

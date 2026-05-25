<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DiscountCode;
use App\Models\User;

class DiscountCodeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Find an admin user to assign as creator
        $admin = User::where('role', 'admin')->first();
        
        if (!$admin) {
            $this->command->warn('No admin user found. Please create an admin user first.');
            return;
        }

        $discountCodes = [
            [
                'code' => 'WELCOME10',
                'name' => 'Welcome 10% Off',
                'description' => 'Welcome discount for new customers - 10% off your first order',
                'type' => 'percentage',
                'value' => 10.00,
                'minimum_amount' => 25.00,
                'maximum_discount' => 50.00,
                'usage_limit' => 100,
                'usage_limit_per_user' => 1,
                'starts_at' => now(),
                'expires_at' => now()->addMonths(3),
                'is_active' => true,
                'created_by' => $admin->id,
            ],
            [
                'code' => 'SAVE20',
                'name' => '20% Off Sale',
                'description' => 'Limited time 20% discount on all books',
                'type' => 'percentage',
                'value' => 20.00,
                'minimum_amount' => 50.00,
                'maximum_discount' => 100.00,
                'usage_limit' => 50,
                'usage_limit_per_user' => 2,
                'starts_at' => now(),
                'expires_at' => now()->addWeeks(2),
                'is_active' => true,
                'created_by' => $admin->id,
            ],
            [
                'code' => 'FREESHIP',
                'name' => 'Free Shipping',
                'description' => 'Free shipping on orders over $30',
                'type' => 'fixed',
                'value' => 5.00,
                'minimum_amount' => 30.00,
                'maximum_discount' => null,
                'usage_limit' => null, // unlimited
                'usage_limit_per_user' => null, // unlimited per user
                'starts_at' => now(),
                'expires_at' => now()->addMonth(),
                'is_active' => true,
                'created_by' => $admin->id,
            ],
            [
                'code' => 'BIGDEAL',
                'name' => '$15 Off Big Orders',
                'description' => '$15 off orders over $100',
                'type' => 'fixed',
                'value' => 15.00,
                'minimum_amount' => 100.00,
                'maximum_discount' => null,
                'usage_limit' => 25,
                'usage_limit_per_user' => 1,
                'starts_at' => now(),
                'expires_at' => now()->addWeeks(4),
                'is_active' => true,
                'created_by' => $admin->id,
            ],
            [
                'code' => 'EXPIRED',
                'name' => 'Expired Test Code',
                'description' => 'This code is expired for testing purposes',
                'type' => 'percentage',
                'value' => 25.00,
                'minimum_amount' => null,
                'maximum_discount' => null,
                'usage_limit' => 10,
                'usage_limit_per_user' => 1,
                'starts_at' => now()->subWeeks(2),
                'expires_at' => now()->subWeek(),
                'is_active' => true,
                'created_by' => $admin->id,
            ],
        ];

        foreach ($discountCodes as $codeData) {
            DiscountCode::create($codeData);
        }

        $this->command->info('Sample discount codes created successfully!');
        $this->command->info('Available codes: WELCOME10, SAVE20, FREESHIP, BIGDEAL');
    }
}

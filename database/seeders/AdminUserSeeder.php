<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // ======================
        // Create Admin
        // ======================
        if (!User::where('email', 'admin@gmail.com')->exists()) {
            User::create([
                'name' => 'Admin User',
                'email' => 'admin@gmail.com',
                'password_hash' => Hash::make('12345678'),
                'role' => 'admin',
                'avatar' => 'https://example.com/avatars/default.png',
            ]);
        }

        // ======================
        // Create Author
        // ======================
        if (!User::where('email', 'author@gmail.com')->exists()) {
            User::create([
                'name' => 'Author User',
                'email' => 'author@gmail.com',
                'password_hash' => Hash::make('12345678'),
                'role' => 'author',
                'avatar' => 'https://example.com/avatars/default.png',
            ]);
        }
    }
}

<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Check if admin exists
        if (!User::where('email', 'admin@apex.com')->exists()) {
            User::factory()->create([
                'name' => 'Admin User',
                'email' => 'admin@apex.com',
                'password' => Hash::make('password'),
            ]);
        }
    }
}

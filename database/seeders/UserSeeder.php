<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Constants\Role;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Admin User
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'role' => Role::ADMIN,
                'is_active' => true,
            ]
        );

        // Uploader User
        User::firstOrCreate(
            ['email' => 'uploader@example.com'],
            [
                'name' => 'Uploader User',
                'password' => Hash::make('password'),
                'role' => Role::UPLOADER,
                'is_active' => true,
            ]
        );

        // Viewer User
        User::firstOrCreate(
            ['email' => 'viewer@example.com'],
            [
                'name' => 'Viewer User',
                'password' => Hash::make('password'),
                'role' => Role::VIEWER,
                'is_active' => true,
            ]
        );
    }
}

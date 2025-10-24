<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DeveloperUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create developer user if not exists
        User::firstOrCreate(
            ['email' => 'uozmui.engineer@gmail.com'],
            [
                'name' => 'Developer',
                'password' => Hash::make('developer123'),
                'email_verified_at' => now(),
                'role' => 'developer',
            ]
        );
    }
}

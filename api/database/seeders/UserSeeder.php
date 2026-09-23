<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'organizer@atlas.test'],
            [
                'name' => 'Atlas Organizer',
                'role' => UserRole::ORGANIZER,
                'password' => Hash::make('Password123!'),
            ]
        );

        User::updateOrCreate(
            ['email' => 'buyer@atlas.test'],
            [
                'name' => 'Atlas Buyer',
                'role' => UserRole::BUYER,
                'password' => Hash::make('Password123!'),
            ]
        );
    }
}

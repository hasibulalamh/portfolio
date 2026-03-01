<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;


class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

       User::create([
        'name' => 'Hasibul Alam',
        'email' => 'hasibulalam108@gmail.com',
        'password' => Hash::make('password123'),
        'email_verified_at' => now(),
       ]);
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $email = env('SEED_USER_EMAIL');
        $password = env('SEED_USER_PASSWORD');

        if ($email && $password) {
            User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => 'Demo User',
                    'password' => Hash::make($password),
                ]
            );
        }
    }
}

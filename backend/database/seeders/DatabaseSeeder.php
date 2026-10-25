<?php

namespace Database\Seeders;

use App\Models\Gadget;
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
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => 'Demo User',
                    'password' => Hash::make($password),
                ]
            );

            if ($user->gadgets()->count() === 0) {
                $gadgets = [
                    [
                        'name' => 'Sony WH-1000XM5',
                        'brand' => 'Sony',
                        'category' => 'audio',
                        'store' => 'Tokopedia',
                        'product_url' => 'https://tokopedia.com',
                        'target_price' => 4500000,
                        'current_price' => 4200000,
                        'priority' => 'high',
                        'status' => 'wishlist',
                        'notes' => 'Target tercapai saat promo',
                    ],
                    [
                        'name' => 'MacBook Air M3 16GB',
                        'brand' => 'Apple',
                        'category' => 'laptop',
                        'store' => 'iBox',
                        'product_url' => 'https://ibox.co.id',
                        'target_price' => 17000000,
                        'current_price' => 18999000,
                        'priority' => 'medium',
                        'status' => 'wishlist',
                        'notes' => 'Tunggu diskon akhir tahun',
                    ],
                    [
                        'name' => 'Fujifilm X-T50',
                        'brand' => 'Fujifilm',
                        'category' => 'camera',
                        'store' => 'Shopee',
                        'product_url' => 'https://shopee.co.id',
                        'target_price' => 20000000,
                        'current_price' => 21500000,
                        'priority' => 'low',
                        'status' => 'wishlist',
                        'notes' => 'Incar kit lens',
                    ],
                ];

                foreach ($gadgets as $data) {
                    $gadget = $user->gadgets()->create($data);
                    $gadget->priceHistories()->create([
                        'price' => $data['current_price'],
                    ]);
                }
            }
        }
    }
}

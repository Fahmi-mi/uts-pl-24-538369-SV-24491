<?php

namespace Database\Factories;

use App\Models\Gadget;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GadgetFactory extends Factory
{
    protected $model = Gadget::class;

    public function definition(): array
    {
        $targetPrice = fake()->numberBetween(1000000, 20000000);
        $currentPrice = fake()->numberBetween(1000000, 25000000);

        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'brand' => fake()->randomElement(['Apple', 'Samsung', 'Sony', 'Asus', 'Xiaomi', 'Dell', 'Logitech']),
            'category' => fake()->randomElement(['smartphone', 'laptop', 'audio', 'wearable', 'camera', 'accessory', 'other']),
            'store' => fake()->randomElement(['Tokopedia', 'Shopee', 'iBox', 'Eraspace', 'Official Store']),
            'product_url' => fake()->url(),
            'target_price' => $targetPrice,
            'current_price' => $currentPrice,
            'priority' => fake()->randomElement(['low', 'medium', 'high']),
            'status' => fake()->randomElement(['wishlist', 'purchased', 'dropped']),
            'notes' => fake()->sentence(),
        ];
    }
}

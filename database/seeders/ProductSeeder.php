<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            [
                'name' => 'iPhone 15 Pro',
                'description' => 'Latest iPhone with A17 Pro chip, titanium design, and advanced camera system.',
                'price' => 15999000,
                'stock' => 50,
                'image' => 'iphone-15-pro.jpg',
                'is_active' => true,
            ],
            [
                'name' => 'MacBook Air M3',
                'description' => '13-inch MacBook Air with M3 chip, 8GB RAM, 256GB SSD.',
                'price' => 18999000,
                'stock' => 30,
                'image' => 'macbook-air-m3.jpg',
                'is_active' => true,
            ],
            [
                'name' => 'AirPods Pro (2nd Gen)',
                'description' => 'Active Noise Cancellation, Adaptive Audio, and personalized spatial audio.',
                'price' => 3999000,
                'stock' => 100,
                'image' => 'airpods-pro-2.jpg',
                'is_active' => true,
            ],
            [
                'name' => 'iPad Pro 11-inch',
                'description' => 'M2 chip, Liquid Retina display, Wi-Fi 6E, 128GB storage.',
                'price' => 13999000,
                'stock' => 40,
                'image' => 'ipad-pro-11.jpg',
                'is_active' => true,
            ],
            [
                'name' => 'Apple Watch Series 9',
                'description' => 'Advanced health features, always-on Retina display, GPS + Cellular.',
                'price' => 6999000,
                'stock' => 60,
                'image' => 'apple-watch-9.jpg',
                'is_active' => true,
            ],
            [
                'name' => 'Magic Keyboard for iPad Pro',
                'description' => 'Backlit keyboard with trackpad, perfect for iPad Pro.',
                'price' => 4999000,
                'stock' => 25,
                'image' => 'magic-keyboard.jpg',
                'is_active' => true,
            ],
            [
                'name' => 'Apple Pencil (2nd Gen)',
                'description' => 'Pixel-perfect precision, wireless charging and pairing.',
                'price' => 1999000,
                'stock' => 75,
                'image' => 'apple-pencil-2.jpg',
                'is_active' => true,
            ],
            [
                'name' => 'HomePod mini',
                'description' => 'Smart speaker with Siri, amazing sound, works with Apple Music.',
                'price' => 1499000,
                'stock' => 80,
                'image' => 'homepod-mini.jpg',
                'is_active' => true,
            ],
            [
                'name' => 'MagSafe Charger',
                'description' => 'Wireless charging for iPhone 12 and later, perfectly aligned magnets.',
                'price' => 599000,
                'stock' => 150,
                'image' => 'magsafe-charger.jpg',
                'is_active' => true,
            ],
            [
                'name' => 'AirTag 4 Pack',
                'description' => 'Keep track of your items with precision finding and privacy built in.',
                'price' => 1499000,
                'stock' => 90,
                'image' => 'airtag-4pack.jpg',
                'is_active' => true,
            ],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }

        $this->command->info('✅ Products seeded successfully!');
    }
}

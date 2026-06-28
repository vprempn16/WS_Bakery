<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Api\V1\Product\Models\Product;
use App\Modules\Api\V1\Organization\Models\Organization;
use Illuminate\Support\Str;

class SampleProductsSeeder extends Seeder
{
    public function run()
    {
        $org = Organization::first();
        
        if (!$org) {
            echo "No organization found! Cannot create products.\n";
            return;
        }

        $products = [
            // Breads
            ['name' => 'White Bread', 'category' => 'Bread', 'price' => 45, 'unit' => 'Piece', 'description' => 'Classic white bread loaf'],
            ['name' => 'Whole Wheat Bread', 'category' => 'Bread', 'price' => 55, 'unit' => 'Piece', 'description' => 'Healthy whole wheat bread'],
            ['name' => 'Garlic Bread', 'category' => 'Bread', 'price' => 70, 'unit' => 'Piece', 'description' => 'Freshly baked garlic bread'],
            
            // Sweets
            ['name' => 'Gulab Jamun', 'category' => 'Sweet', 'price' => 250, 'unit' => 'Kg', 'description' => 'Delicious syrupy dessert'],
            ['name' => 'Rasgulla', 'category' => 'Sweet', 'price' => 200, 'unit' => 'Kg', 'description' => 'Spongy milk sweet'],
            ['name' => 'Kaju Katli', 'category' => 'Sweet', 'price' => 800, 'unit' => 'Kg', 'description' => 'Premium cashew sweet'],
            ['name' => 'Assorted Sweets Box', 'category' => 'Sweet', 'price' => 500, 'unit' => 'Box', 'description' => 'Mixed traditional sweets'],

            // Cakes
            ['name' => 'Black Forest Cake', 'category' => 'Cake', 'price' => 600, 'unit' => 'Kg', 'description' => 'Classic chocolate and cherry cake'],
            ['name' => 'Red Velvet Cake', 'category' => 'Cake', 'price' => 750, 'unit' => 'Kg', 'description' => 'Rich red velvet layered cake'],
            ['name' => 'Vanilla Cupcake', 'category' => 'Cake', 'price' => 40, 'unit' => 'Piece', 'description' => 'Simple and sweet vanilla cupcake'],

            // Snacks
            ['name' => 'Potato Chips', 'category' => 'Snack', 'price' => 20, 'unit' => 'Packet', 'description' => 'Crispy potato chips'],
            ['name' => 'Mixture', 'category' => 'Snack', 'price' => 60, 'unit' => 'Gram', 'description' => 'Spicy traditional mixture (per 100g base)'],
            ['name' => 'Veg Puff', 'category' => 'Snack', 'price' => 25, 'unit' => 'Piece', 'description' => 'Flaky puff pastry with mixed veg filling'],

            // Beverages
            ['name' => 'Cold Coffee', 'category' => 'Beverage', 'price' => 80, 'unit' => 'Piece', 'description' => 'Refreshing iced coffee'],
            ['name' => 'Fresh Lemon Soda', 'category' => 'Beverage', 'price' => 40, 'unit' => 'Piece', 'description' => 'Sweet and salty lime soda'],
            ['name' => 'Mineral Water', 'category' => 'Beverage', 'price' => 20, 'unit' => 'Liter', 'description' => '1L bottled water'],

            // Other
            ['name' => 'Birthday Candles', 'category' => 'Other', 'price' => 30, 'unit' => 'Packet', 'description' => 'Pack of 10 colored candles'],
            ['name' => 'Party Poppers', 'category' => 'Other', 'price' => 50, 'unit' => 'Piece', 'description' => 'Small confetti popper'],
        ];

        foreach ($products as $key => $p) {
            Product::create([
                'organization_id' => $org->id,
                'product_number' => 'PROD-S' . str_pad($key + 1, 3, '0', STR_PAD_LEFT),
                'name' => $p['name'],
                'description' => $p['description'],
                'category' => $p['category'],
                'price' => $p['price'],
                'unit' => $p['unit'],
                'tier' => 'tier_2',
                'shelf_life_days' => 5,
                'current_stock' => 50
            ]);
        }
        
        echo "Successfully seeded " . count($products) . " sample products!\n";
    }
}

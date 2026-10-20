<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Seed the application's products.
     */
    public function run(): void
    {
        $products = [
            ['name' => 'Camiseta de algodón', 'slug' => 'camiseta-de-algodon', 'sku' => 'CAM-001', 'price' => 19.99, 'stock' => 50],
            ['name' => 'Zapatillas deportivas', 'slug' => 'zapatillas-deportivas', 'sku' => 'ZAP-001', 'price' => 79.50, 'stock' => 25],
            ['name' => 'Mochila urbana', 'slug' => 'mochila-urbana', 'sku' => 'MOC-001', 'price' => 45.00, 'stock' => 15],
            ['name' => 'Gorra deportiva', 'slug' => 'gorra-deportiva', 'sku' => 'GOR-001', 'price' => 12.75, 'stock' => 0],
            ['name' => 'Bufanda de lana', 'slug' => 'bufanda-de-lana', 'sku' => 'BUF-001', 'price' => 24.00, 'stock' => 30],
        ];

        foreach ($products as $product) {
            Product::factory()->create($product);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Category;
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
            ['name' => 'Camiseta de algodón', 'slug' => 'camiseta-de-algodon', 'sku' => 'CAM-001', 'price' => 19.99, 'stock' => 50, 'category' => 'ropa'],
            ['name' => 'Bufanda de lana', 'slug' => 'bufanda-de-lana', 'sku' => 'BUF-001', 'price' => 24.00, 'stock' => 30, 'category' => 'ropa'],
            ['name' => 'Zapatillas deportivas', 'slug' => 'zapatillas-deportivas', 'sku' => 'ZAP-001', 'price' => 79.50, 'stock' => 25, 'category' => 'calzado'],
            ['name' => 'Gorra deportiva', 'slug' => 'gorra-deportiva', 'sku' => 'GOR-001', 'price' => 12.75, 'stock' => 0, 'category' => 'accesorios'],
            ['name' => 'Mochila urbana', 'slug' => 'mochila-urbana', 'sku' => 'MOC-001', 'price' => 45.00, 'stock' => 15, 'category' => 'accesorios'],
        ];

        foreach ($products as $product) {
            $category = Category::where('slug', $product['category'])->firstOrFail();

            Product::factory()->create([
                'name' => $product['name'],
                'slug' => $product['slug'],
                'sku' => $product['sku'],
                'price' => $product['price'],
                'stock' => $product['stock'],
                'category_id' => $category->id,
            ]);
        }
    }
}

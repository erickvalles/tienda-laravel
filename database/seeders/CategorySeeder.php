<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Seed the application's categories.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Ropa', 'slug' => 'ropa', 'description' => 'Prendas de vestir'],
            ['name' => 'Calzado', 'slug' => 'calzado', 'description' => 'Zapatos y botas'],
            ['name' => 'Accesorios', 'slug' => 'accesorios', 'description' => 'Complementos y accesorios'],
        ];

        foreach ($categories as $category) {
            Category::factory()->create($category);
        }
    }
}

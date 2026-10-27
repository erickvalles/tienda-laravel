<?php

use App\Models\Category;
use App\Models\Product;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

function categoryPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Calzado',
        'slug' => 'calzado',
        'description' => 'Zapatos y botas',
    ], $overrides);
}

it('lists categories', function () {
    Category::factory()->count(3)->create();

    $response = getJson('/api/categories');

    $response->assertOk()->assertJsonCount(3, 'data');
});

it('shows a single category with its products', function () {
    $category = Category::factory()->create(['name' => 'Verano']);
    Product::factory()->count(2)->for($category)->create();

    $response = getJson("/api/categories/{$category->id}");

    $response->assertOk()
        ->assertJsonPath('data.name', 'Verano')
        ->assertJsonCount(2, 'data.products')
        ->assertJsonPath('data.products.0.category_id', $category->id);
});

it('returns 404 for a missing category', function () {
    getJson('/api/categories/9999')->assertNotFound();
});

it('creates a category', function () {
    $response = postJson('/api/categories', categoryPayload(['name' => 'Accesorios']));

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Accesorios')
        ->assertJsonPath('data.slug', 'calzado');

    $this->assertDatabaseHas('categories', ['name' => 'Accesorios']);
});

it('validates the create payload', function () {
    $response = postJson('/api/categories', ['name' => '']);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'slug']);
});

it('rejects a duplicated slug', function () {
    Category::factory()->create(['slug' => 'slug-duplicado']);

    postJson('/api/categories', categoryPayload(['slug' => 'slug-duplicado']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['slug']);
});

it('updates a category', function () {
    $category = Category::factory()->create(['name' => 'Nombre viejo']);

    $response = putJson("/api/categories/{$category->id}", categoryPayload(['name' => 'Nombre nuevo']));

    $response->assertOk()->assertJsonPath('data.name', 'Nombre nuevo');

    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
        'name' => 'Nombre nuevo',
    ]);
});

it('deletes a category and nullifies its products', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create();

    deleteJson("/api/categories/{$category->id}")->assertNoContent();

    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    $this->assertNull($product->refresh()->category_id);
});

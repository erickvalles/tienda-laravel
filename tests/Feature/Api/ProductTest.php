<?php

use App\Models\Product;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

function productPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Camiseta Azul',
        'slug' => 'camiseta-azul',
        'sku' => 'SKU-'.fake()->unique()->numberBetween(1, 999999),
        'description' => 'Camiseta de algodón',
        'price' => 19.99,
        'stock' => 10,
    ], $overrides);
}

it('lists products', function () {
    Product::factory()->count(3)->create();

    $response = getJson('/api/products');

    $response->assertOk()->assertJsonCount(3, 'data');
});

it('shows a single product', function () {
    $product = Product::factory()->create(['name' => 'Gorra deportiva']);

    $response = getJson("/api/products/{$product->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $product->id)
        ->assertJsonPath('data.name', 'Gorra deportiva');
});

it('returns 404 for a missing product', function () {
    getJson('/api/products/9999')->assertNotFound();
});

it('creates a product', function () {
    $response = postJson('/api/products', productPayload(['name' => 'Bufanda de lana']));

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Bufanda de lana')
        ->assertJsonPath('data.slug', 'camiseta-azul')
        ->assertJsonPath('data.stock', 10);

    $this->assertDatabaseHas('products', [
        'name' => 'Bufanda de lana',
        'slug' => 'camiseta-azul',
        'stock' => 10,
    ]);
});

it('validates the create payload', function () {
    $response = postJson('/api/products', ['name' => '', 'price' => 'abc']);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'slug', 'sku', 'price', 'stock']);
});

it('rejects a duplicated sku', function () {
    Product::factory()->create(['sku' => 'SKU-DUP']);

    postJson('/api/products', productPayload(['sku' => 'SKU-DUP']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['sku']);
});

it('updates a product', function () {
    $product = Product::factory()->create(['name' => 'Nombre viejo', 'stock' => 3]);

    $response = putJson("/api/products/{$product->id}", ['name' => 'Nombre nuevo', 'stock' => 25]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Nombre nuevo')
        ->assertJsonPath('data.stock', 25);

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'name' => 'Nombre nuevo',
        'stock' => 25,
    ]);
});

it('updates a product partially with patch', function () {
    $product = Product::factory()->create(['name' => 'Nombre viejo', 'stock' => 3]);

    $response = patchJson("/api/products/{$product->id}", ['stock' => 7]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Nombre viejo')
        ->assertJsonPath('data.stock', 7);
});

it('validates the update payload', function () {
    $product = Product::factory()->create();

    putJson("/api/products/{$product->id}", ['price' => -5])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['price']);
});

it('deletes a product', function () {
    $product = Product::factory()->create();

    deleteJson("/api/products/{$product->id}")->assertNoContent();

    $this->assertDatabaseMissing('products', ['id' => $product->id]);
});

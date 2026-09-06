<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_products(): void
    {
        Product::factory()->count(3)->for(Category::factory())->create();

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_it_creates_a_product_with_variants(): void
    {
        $category = Category::factory()->create();

        $response = $this->postJson('/api/products', [
            'category_id' => $category->id,
            'name' => 'New Shirt',
            'base_price' => 29.99,
            'variants' => [
                ['size' => 'M', 'color' => 'Black', 'stock_quantity' => 10],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'New Shirt')
            ->assertJsonCount(1, 'data.variants');

        $this->assertDatabaseHas('products', ['name' => 'New Shirt']);
        $this->assertDatabaseHas('product_variants', ['color' => 'Black', 'stock_quantity' => 10]);
    }

    public function test_it_updates_a_product(): void
    {
        $product = Product::factory()->for(Category::factory())->create();

        $this->putJson("/api/products/{$product->id}", [
            'category_id' => $product->category_id,
            'name' => 'Updated Name',
            'base_price' => 50,
        ])->assertOk()->assertJsonPath('data.name', 'Updated Name');
    }

    public function test_it_deletes_a_product(): void
    {
        $product = Product::factory()->for(Category::factory())->create();

        $this->deleteJson("/api/products/{$product->id}")->assertNoContent();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }
}

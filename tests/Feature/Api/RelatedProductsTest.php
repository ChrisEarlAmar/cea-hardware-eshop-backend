<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelatedProductsTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_up_to_four_other_products_in_the_same_category(): void
    {
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Hex Bolt', 'slug' => 'hex-bolt']);

        foreach (range(1, 5) as $i) {
            Product::create(['category_id' => $category->id, 'name' => "Bolt {$i}", 'slug' => "bolt-{$i}"]);
        }

        $response = $this->getJson("/api/products/{$product->id}/related");

        $response->assertStatus(200);
        $this->assertCount(4, $response->json('data'));
    }

    public function test_excludes_the_product_itself(): void
    {
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Hex Bolt', 'slug' => 'hex-bolt']);
        Product::create(['category_id' => $category->id, 'name' => 'Carriage Bolt', 'slug' => 'carriage-bolt']);

        $response = $this->getJson("/api/products/{$product->id}/related");

        $slugs = collect($response->json('data'))->pluck('slug')->all();
        $this->assertNotContains('hex-bolt', $slugs);
        $this->assertSame(['carriage-bolt'], $slugs);
    }

    public function test_only_returns_products_from_the_same_category(): void
    {
        $bolts = Category::create(['name' => 'Bolts', 'slug' => 'bolts']);
        $bearings = Category::create(['name' => 'Bearings', 'slug' => 'bearings']);

        $product = Product::create(['category_id' => $bolts->id, 'name' => 'Hex Bolt', 'slug' => 'hex-bolt']);
        Product::create(['category_id' => $bolts->id, 'name' => 'Carriage Bolt', 'slug' => 'carriage-bolt']);
        Product::create(['category_id' => $bearings->id, 'name' => 'Ball Bearing', 'slug' => 'ball-bearing']);

        $response = $this->getJson("/api/products/{$product->id}/related");

        $slugs = collect($response->json('data'))->pluck('slug')->all();
        $this->assertSame(['carriage-bolt'], $slugs);
    }

    public function test_a_product_with_no_siblings_returns_an_empty_list_not_an_error(): void
    {
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Hex Bolt', 'slug' => 'hex-bolt']);

        $response = $this->getJson("/api/products/{$product->id}/related");

        $response->assertStatus(200);
        $this->assertSame([], $response->json('data'));
    }

    public function test_returns_basic_fields_only(): void
    {
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Hex Bolt', 'slug' => 'hex-bolt']);
        Product::create([
            'category_id' => $category->id,
            'name' => 'Carriage Bolt',
            'slug' => 'carriage-bolt',
            'image_url' => 'https://example.com/carriage-bolt.jpg',
        ]);

        $response = $this->getJson("/api/products/{$product->id}/related");

        $response->assertStatus(200)
            ->assertJsonPath('data.0.slug', 'carriage-bolt')
            ->assertJsonPath('data.0.name', 'Carriage Bolt')
            ->assertJsonPath('data.0.image_url', 'https://example.com/carriage-bolt.jpg');
    }
}

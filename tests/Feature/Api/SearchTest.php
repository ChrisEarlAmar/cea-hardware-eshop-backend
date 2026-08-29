<?php

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Synonym;
use App\Models\VariantAttributeValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_requires_a_query_parameter(): void
    {
        $this->getJson('/api/search')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_search_does_not_require_authentication(): void
    {
        $this->getJson('/api/search?q=anything')->assertStatus(200);
    }

    public function test_search_returns_matching_products(): void
    {
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts-'.uniqid()]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Findable Widget', 'slug' => 'widget-'.uniqid()]);

        $response = $this->getJson('/api/search?q=Findable');

        $response->assertStatus(200)
            ->assertJsonPath('data.query', 'Findable');

        $slugs = collect($response->json('data.products'))->pluck('slug')->all();
        $this->assertContains($product->slug, $slugs);
    }

    public function test_search_via_synonym_and_canonical_term_return_the_same_product_list(): void
    {
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts-'.uniqid()]);
        $attribute = Attribute::create(['name' => 'Material', 'key' => 'material-'.uniqid(), 'data_type' => 'string']);
        CategoryAttribute::create([
            'category_id' => $category->id,
            'attribute_id' => $attribute->id,
            'is_variant_defining' => true,
        ]);

        $product = Product::create(['category_id' => $category->id, 'name' => 'Test Product', 'slug' => 'test-'.uniqid()]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'SKU-ALPHA', 'stock' => 10]);
        VariantAttributeValue::create(['product_variant_id' => $variant->id, 'attribute_id' => $attribute->id, 'value' => 'Stainless Steel']);

        Synonym::create(['attribute_id' => $attribute->id, 'term' => 'ss', 'value' => 'Stainless Steel']);

        $viaSynonym = $this->getJson('/api/search?q=ss');
        $viaCanonical = $this->getJson('/api/search?q=Stainless Steel');

        $viaSynonym->assertStatus(200);
        $viaCanonical->assertStatus(200);

        $synonymSlugs = collect($viaSynonym->json('data.products'))->pluck('slug')->sort()->values();
        $canonicalSlugs = collect($viaCanonical->json('data.products'))->pluck('slug')->sort()->values();

        $this->assertEquals($canonicalSlugs, $synonymSlugs);
        $this->assertTrue($synonymSlugs->contains($product->slug));
    }

    public function test_search_results_are_paginated(): void
    {
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts-'.uniqid()]);

        foreach (range(1, 3) as $i) {
            Product::create(['category_id' => $category->id, 'name' => "Common Widget {$i}", 'slug' => "widget-{$i}-".uniqid()]);
        }

        $response = $this->getJson('/api/search?q=Common&per_page=2');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.products'));
        $this->assertSame(3, $response->json('meta.total'));
    }

    public function test_an_unrelated_search_returns_an_empty_product_list(): void
    {
        Category::create(['name' => 'Bolts', 'slug' => 'bolts-'.uniqid()]);

        $response = $this->getJson('/api/search?q=zzz-totally-unrelated-'.uniqid());

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data.products'));
    }
}

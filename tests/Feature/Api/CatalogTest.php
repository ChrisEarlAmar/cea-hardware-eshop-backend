<?php

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantAttributeValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_categories_index_lists_all_categories(): void
    {
        Category::create(['name' => 'Bolts', 'slug' => 'bolts']);
        Category::create(['name' => 'Bearings', 'slug' => 'bearings']);

        $response = $this->getJson('/api/categories');

        $response->assertStatus(200);
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertEqualsCanonicalizing(['Bolts', 'Bearings'], $names);
    }

    public function test_category_products_lists_products_in_that_category(): void
    {
        $bolts = Category::create(['name' => 'Bolts', 'slug' => 'bolts']);
        $bearings = Category::create(['name' => 'Bearings', 'slug' => 'bearings']);

        Product::create(['category_id' => $bolts->id, 'name' => 'Hex Bolt', 'slug' => 'hex-bolt']);
        Product::create(['category_id' => $bolts->id, 'name' => 'Carriage Bolt', 'slug' => 'carriage-bolt']);
        Product::create(['category_id' => $bearings->id, 'name' => 'Ball Bearing', 'slug' => 'ball-bearing']);

        $response = $this->getJson('/api/categories/bolts/products');

        $response->assertStatus(200)
            ->assertJsonPath('data.category.slug', 'bolts');

        $slugs = collect($response->json('data.products'))->pluck('slug')->all();
        $this->assertEqualsCanonicalizing(['hex-bolt', 'carriage-bolt'], $slugs);
    }

    public function test_category_products_are_paginated(): void
    {
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts']);

        foreach (range(1, 5) as $i) {
            Product::create(['category_id' => $category->id, 'name' => "Bolt {$i}", 'slug' => "bolt-{$i}"]);
        }

        $response = $this->getJson('/api/categories/bolts/products?per_page=2');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.products'));
        $this->assertSame(5, $response->json('meta.total'));
        $this->assertSame(3, $response->json('meta.last_page'));
    }

    public function test_category_products_can_be_sorted_by_price(): void
    {
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts']);

        $cheap = Product::create(['category_id' => $category->id, 'name' => 'Cheap Bolt', 'slug' => 'cheap-bolt']);
        $cheapVariant = ProductVariant::create(['product_id' => $cheap->id, 'sku' => 'CHEAP', 'stock' => 10]);
        PriceTier::create(['product_variant_id' => $cheapVariant->id, 'min_quantity' => 1, 'unit_price' => '1.00']);

        $pricey = Product::create(['category_id' => $category->id, 'name' => 'Pricey Bolt', 'slug' => 'pricey-bolt']);
        $priceyVariant = ProductVariant::create(['product_id' => $pricey->id, 'sku' => 'PRICEY', 'stock' => 10]);
        PriceTier::create(['product_variant_id' => $priceyVariant->id, 'min_quantity' => 1, 'unit_price' => '99.00']);

        $ascending = $this->getJson('/api/categories/bolts/products?sort=price_asc');
        $descending = $this->getJson('/api/categories/bolts/products?sort=price_desc');

        $this->assertSame(['cheap-bolt', 'pricey-bolt'], collect($ascending->json('data.products'))->pluck('slug')->all());
        $this->assertSame(['pricey-bolt', 'cheap-bolt'], collect($descending->json('data.products'))->pluck('slug')->all());
        $this->assertSame('1.00', collect($ascending->json('data.products'))->firstWhere('slug', 'cheap-bolt')['starting_price']);
    }

    public function test_category_products_returns_404_for_unknown_slug(): void
    {
        $this->getJson('/api/categories/does-not-exist/products')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'not_found');
    }

    public function test_product_show_returns_404_for_unknown_slug(): void
    {
        $this->getJson('/api/products/does-not-exist')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'not_found');
    }

    public function test_product_show_returns_full_detail_with_correctly_flagged_attributes(): void
    {
        [$product, $material, $length, $weight] = $this->seedBoltWithOneVariant();

        $response = $this->getJson("/api/products/{$product->slug}");

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Hex Bolt')
            ->assertJsonPath('data.category.slug', 'bolts');

        $attributesByKey = collect($response->json('data.attributes'))->keyBy('key');

        $this->assertTrue($attributesByKey['material']['is_variant_defining']);
        $this->assertTrue($attributesByKey['length']['is_variant_defining']);
        $this->assertFalse($attributesByKey['weight']['is_variant_defining']);
        $this->assertSame('mm', $attributesByKey['length']['unit']);
    }

    public function test_product_show_scopes_each_variants_values_to_itself(): void
    {
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts']);
        $material = Attribute::create(['name' => 'Material', 'key' => 'material', 'data_type' => 'string']);
        $length = Attribute::create(['name' => 'Length', 'key' => 'length', 'data_type' => 'decimal', 'unit' => 'mm']);

        CategoryAttribute::create(['category_id' => $category->id, 'attribute_id' => $material->id, 'is_variant_defining' => true]);
        CategoryAttribute::create(['category_id' => $category->id, 'attribute_id' => $length->id, 'is_variant_defining' => true]);

        $product = Product::create(['category_id' => $category->id, 'name' => 'Hex Bolt', 'slug' => 'hex-bolt']);

        $variantA = ProductVariant::create(['product_id' => $product->id, 'sku' => 'A', 'stock' => 10]);
        VariantAttributeValue::create(['product_variant_id' => $variantA->id, 'attribute_id' => $material->id, 'value' => 'Stainless Steel']);
        VariantAttributeValue::create(['product_variant_id' => $variantA->id, 'attribute_id' => $length->id, 'value' => '20']);

        $variantB = ProductVariant::create(['product_id' => $product->id, 'sku' => 'B', 'stock' => 5]);
        VariantAttributeValue::create(['product_variant_id' => $variantB->id, 'attribute_id' => $material->id, 'value' => 'Zinc-Plated Steel']);
        VariantAttributeValue::create(['product_variant_id' => $variantB->id, 'attribute_id' => $length->id, 'value' => '30']);

        $response = $this->getJson("/api/products/{$product->slug}");

        $variantsBySku = collect($response->json('data.variants'))->keyBy('sku');

        $this->assertSame('Stainless Steel', $variantsBySku['A']['values']['material']);
        $this->assertSame('20', $variantsBySku['A']['values']['length']);
        $this->assertSame('Zinc-Plated Steel', $variantsBySku['B']['values']['material']);
        $this->assertSame('30', $variantsBySku['B']['values']['length']);
    }

    public function test_products_in_different_categories_have_different_attribute_sets(): void
    {
        $bolts = Category::create(['name' => 'Bolts', 'slug' => 'bolts']);
        $bearings = Category::create(['name' => 'Bearings', 'slug' => 'bearings']);

        $material = Attribute::create(['name' => 'Material', 'key' => 'material', 'data_type' => 'string']);
        $boreDiameter = Attribute::create(['name' => 'Bore Diameter', 'key' => 'bore_diameter', 'data_type' => 'decimal', 'unit' => 'mm']);

        CategoryAttribute::create(['category_id' => $bolts->id, 'attribute_id' => $material->id, 'is_variant_defining' => true]);
        CategoryAttribute::create(['category_id' => $bearings->id, 'attribute_id' => $boreDiameter->id, 'is_variant_defining' => true]);

        $bolt = Product::create(['category_id' => $bolts->id, 'name' => 'Hex Bolt', 'slug' => 'hex-bolt']);
        $bearing = Product::create(['category_id' => $bearings->id, 'name' => 'Ball Bearing', 'slug' => 'ball-bearing']);

        $boltKeys = collect($this->getJson("/api/products/{$bolt->slug}")->json('data.attributes'))->pluck('key')->all();
        $bearingKeys = collect($this->getJson("/api/products/{$bearing->slug}")->json('data.attributes'))->pluck('key')->all();

        $this->assertSame(['material'], $boltKeys);
        $this->assertSame(['bore_diameter'], $bearingKeys);
    }

    /**
     * @return array{0: Product, 1: Attribute, 2: Attribute, 3: Attribute}
     */
    private function seedBoltWithOneVariant(): array
    {
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts']);

        $material = Attribute::create(['name' => 'Material', 'key' => 'material', 'data_type' => 'string']);
        $length = Attribute::create(['name' => 'Length', 'key' => 'length', 'data_type' => 'decimal', 'unit' => 'mm']);
        $weight = Attribute::create(['name' => 'Weight', 'key' => 'weight', 'data_type' => 'decimal', 'unit' => 'g']);

        CategoryAttribute::create(['category_id' => $category->id, 'attribute_id' => $material->id, 'is_variant_defining' => true]);
        CategoryAttribute::create(['category_id' => $category->id, 'attribute_id' => $length->id, 'is_variant_defining' => true]);
        CategoryAttribute::create(['category_id' => $category->id, 'attribute_id' => $weight->id, 'is_variant_defining' => false]);

        $product = Product::create(['category_id' => $category->id, 'name' => 'Hex Bolt', 'slug' => 'hex-bolt']);

        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'BOLT-1', 'stock' => 10]);
        VariantAttributeValue::create(['product_variant_id' => $variant->id, 'attribute_id' => $material->id, 'value' => 'Stainless Steel']);
        VariantAttributeValue::create(['product_variant_id' => $variant->id, 'attribute_id' => $length->id, 'value' => '20']);
        VariantAttributeValue::create(['product_variant_id' => $variant->id, 'attribute_id' => $weight->id, 'value' => '9.5']);

        return [$product, $material, $length, $weight];
    }
}

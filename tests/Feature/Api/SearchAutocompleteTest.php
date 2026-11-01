<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchAutocompleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_autocomplete_does_not_require_authentication(): void
    {
        $this->getJson('/api/search/autocomplete?q=any')->assertStatus(200);
    }

    public function test_autocomplete_returns_matching_product_and_sku_suggestions(): void
    {
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts-'.uniqid()]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Hex Bolt M8', 'slug' => 'hex-bolt-m8-'.uniqid()]);
        ProductVariant::create(['product_id' => $product->id, 'sku' => 'HEXM8-STEEL', 'stock' => 10]);

        $byName = $this->getJson('/api/search/autocomplete?q=Hex+Bolt');
        $byName->assertStatus(200);
        $nameLabels = collect($byName->json('data.suggestions'))->pluck('label')->all();
        $this->assertContains('Hex Bolt M8', $nameLabels);

        $bySku = $this->getJson('/api/search/autocomplete?q=HEXM8');
        $bySku->assertStatus(200);
        $skuSuggestions = collect($bySku->json('data.suggestions'));
        $this->assertTrue($skuSuggestions->contains(fn ($s) => $s['label'] === 'HEXM8-STEEL' && $s['product_slug'] === $product->slug));
    }

    public function test_autocomplete_caps_suggestions_at_eight(): void
    {
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts-'.uniqid()]);

        foreach (range(1, 12) as $i) {
            Product::create([
                'category_id' => $category->id,
                'name' => "Common Widget {$i}",
                'slug' => "widget-{$i}-".uniqid(),
            ]);
        }

        $response = $this->getJson('/api/search/autocomplete?q=Common');

        $response->assertStatus(200);
        $this->assertCount(8, $response->json('data.suggestions'));
    }

    public function test_an_empty_query_returns_an_empty_suggestion_list(): void
    {
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts-'.uniqid()]);
        Product::create(['category_id' => $category->id, 'name' => 'Findable Widget', 'slug' => 'widget-'.uniqid()]);

        $response = $this->getJson('/api/search/autocomplete?q=');

        $response->assertStatus(200);
        $this->assertSame([], $response->json('data.suggestions'));
    }

    public function test_a_very_short_query_returns_an_empty_suggestion_list_rather_than_the_whole_catalog(): void
    {
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts-'.uniqid()]);
        Product::create(['category_id' => $category->id, 'name' => 'A Widget', 'slug' => 'a-widget-'.uniqid()]);

        $response = $this->getJson('/api/search/autocomplete?q=a');

        $response->assertStatus(200);
        $this->assertSame([], $response->json('data.suggestions'));
    }
}

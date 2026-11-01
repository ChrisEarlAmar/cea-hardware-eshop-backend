<?php

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantAttributeValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacetedSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_facet_filter_narrows_results_to_matching_products_only(): void
    {
        $material = Attribute::create(['name' => 'Material', 'key' => 'material-'.uniqid(), 'data_type' => 'string']);

        $steel = $this->productWithAttributeValues([$material->id => 'Stainless Steel']);
        $zinc = $this->productWithAttributeValues([$material->id => 'Zinc-Plated']);

        $response = $this->getJson('/api/search?q=Bolt&'.$material->key.'='.urlencode('Stainless Steel'));

        $response->assertStatus(200);
        $slugs = collect($response->json('data.products'))->pluck('slug')->all();

        $this->assertContains($steel->slug, $slugs);
        $this->assertNotContains($zinc->slug, $slugs);
    }

    public function test_combining_two_facets_is_an_and_not_an_or(): void
    {
        $material = Attribute::create(['name' => 'Material', 'key' => 'material-'.uniqid(), 'data_type' => 'string']);
        $thread = Attribute::create(['name' => 'Thread Size', 'key' => 'thread_size-'.uniqid(), 'data_type' => 'string']);

        $both = $this->productWithAttributeValues([$material->id => 'Stainless Steel', $thread->id => 'M8']);
        $materialOnly = $this->productWithAttributeValues([$material->id => 'Stainless Steel', $thread->id => 'M6']);

        $query = '/api/search?q=Bolt'
            .'&'.$material->key.'='.urlencode('Stainless Steel')
            .'&'.$thread->key.'='.urlencode('M8');

        $response = $this->getJson($query);

        $response->assertStatus(200);
        $slugs = collect($response->json('data.products'))->pluck('slug')->all();

        $this->assertContains($both->slug, $slugs);
        $this->assertNotContains($materialOnly->slug, $slugs);
    }

    public function test_facets_endpoint_returns_accurate_counts_for_the_current_result_set(): void
    {
        $material = Attribute::create(['name' => 'Material', 'key' => 'material-'.uniqid(), 'data_type' => 'string']);

        $this->productWithAttributeValues([$material->id => 'Stainless Steel']);
        $this->productWithAttributeValues([$material->id => 'Stainless Steel']);
        $this->productWithAttributeValues([$material->id => 'Zinc-Plated']);

        $response = $this->getJson('/api/search/facets?q=Bolt');

        $response->assertStatus(200);

        $facets = collect($response->json('data.facets'));
        $materialFacet = $facets->firstWhere('name', 'Material');
        $this->assertNotNull($materialFacet);

        $counts = collect($materialFacet['values'])->pluck('count', 'value');
        $this->assertSame(2, $counts['Stainless Steel']);
        $this->assertSame(1, $counts['Zinc-Plated']);
    }

    public function test_two_different_keys_sharing_a_display_name_are_merged_into_one_facet(): void
    {
        // Mirrors the real catalog: a generic "material" attribute plus a
        // category-specific one (e.g. "wrench_material") that both display
        // as "Material" - the sidebar should show one merged group, not two.
        $genericMaterial = Attribute::create(['name' => 'Material', 'key' => 'material-'.uniqid(), 'data_type' => 'string']);
        $wrenchMaterial = Attribute::create(['name' => 'Material', 'key' => 'wrench_material-'.uniqid(), 'data_type' => 'string']);

        $this->productWithAttributeValues([$genericMaterial->id => 'Stainless Steel']);
        $this->productWithAttributeValues([$wrenchMaterial->id => 'Chrome Vanadium Steel']);

        $response = $this->getJson('/api/search/facets?q=Bolt');

        $response->assertStatus(200);

        $facets = collect($response->json('data.facets'));
        $materialFacets = $facets->where('name', 'Material');
        $this->assertCount(1, $materialFacets, 'Material should appear as a single merged facet, not one per underlying attribute key.');

        $values = collect($materialFacets->first()['values'])->keyBy('value');
        $this->assertSame(1, $values['Stainless Steel']['count']);
        $this->assertSame(1, $values['Chrome Vanadium Steel']['count']);
        $this->assertSame($genericMaterial->key, $values['Stainless Steel']['key']);
        $this->assertSame($wrenchMaterial->key, $values['Chrome Vanadium Steel']['key']);
    }

    public function test_facets_endpoint_scopes_counts_to_already_selected_facets(): void
    {
        $material = Attribute::create(['name' => 'Material', 'key' => 'material-'.uniqid(), 'data_type' => 'string']);
        $thread = Attribute::create(['name' => 'Thread Size', 'key' => 'thread_size-'.uniqid(), 'data_type' => 'string']);

        $this->productWithAttributeValues([$material->id => 'Stainless Steel', $thread->id => 'M8']);
        $this->productWithAttributeValues([$material->id => 'Zinc-Plated', $thread->id => 'M8']);

        // Once material=Stainless+Steel is already selected, thread_size's
        // counts should reflect only that narrowed (steel-only) result set.
        $response = $this->getJson('/api/search/facets?q=Bolt&'.$material->key.'='.urlencode('Stainless Steel'));

        $response->assertStatus(200);

        $facets = collect($response->json('data.facets'));
        $threadFacet = $facets->firstWhere('name', 'Thread Size');
        $counts = collect($threadFacet['values'])->pluck('count', 'value');

        $this->assertSame(1, $counts['M8']);
    }

    /**
     * @param  array<int, string>  $attributeValues  attribute_id => value
     */
    private function productWithAttributeValues(array $attributeValues): Product
    {
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts-'.uniqid()]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Test Bolt', 'slug' => 'test-bolt-'.uniqid()]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'SKU-'.uniqid(), 'stock' => 10]);

        foreach ($attributeValues as $attributeId => $value) {
            VariantAttributeValue::create([
                'product_variant_id' => $variant->id,
                'attribute_id' => $attributeId,
                'value' => $value,
            ]);
        }

        return $product;
    }
}

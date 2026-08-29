<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Synonym;
use App\Models\VariantAttributeValue;
use App\Services\SearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The core proof this stage exists for: an alias and its canonical
     * value must resolve to exactly the same products.
     */
    public function test_searching_a_synonym_returns_the_same_products_as_the_canonical_term(): void
    {
        [$attribute, $matchingProduct, $otherProduct] = $this->twoProductsOneMatchingMaterial('Stainless Steel');

        Synonym::create(['attribute_id' => $attribute->id, 'term' => 'ss', 'value' => 'Stainless Steel']);

        $viaSynonym = app(SearchService::class)->search('ss')->pluck('id')->sort()->values();
        $viaCanonical = app(SearchService::class)->search('Stainless Steel')->pluck('id')->sort()->values();

        $this->assertEquals($viaCanonical, $viaSynonym);
        $this->assertTrue($viaSynonym->contains($matchingProduct->id));
        $this->assertFalse($viaSynonym->contains($otherProduct->id));
    }

    public function test_searching_a_unit_variant_synonym_returns_the_same_results_as_the_bare_value(): void
    {
        [$attribute, $matchingProduct, $otherProduct] = $this->twoProductsOneMatchingMaterial('20', attributeName: 'Length');

        Synonym::create(['attribute_id' => $attribute->id, 'term' => '20mm', 'value' => '20']);

        $viaSynonym = app(SearchService::class)->search('20mm')->pluck('id')->sort()->values();
        $viaBareValue = app(SearchService::class)->search('20')->pluck('id')->sort()->values();

        $this->assertEquals($viaBareValue, $viaSynonym);
        $this->assertTrue($viaSynonym->contains($matchingProduct->id));
        $this->assertFalse($viaSynonym->contains($otherProduct->id));
    }

    public function test_searching_matches_product_name(): void
    {
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts-'.uniqid()]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Unique Widget Name', 'slug' => 'widget-'.uniqid()]);

        $results = app(SearchService::class)->search('Widget');

        $this->assertTrue($results->pluck('id')->contains($product->id));
    }

    public function test_searching_matches_sku(): void
    {
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts-'.uniqid()]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Test Product', 'slug' => 'test-'.uniqid()]);
        $uniqueSkuFragment = 'ZQPX'.uniqid();
        ProductVariant::create(['product_id' => $product->id, 'sku' => "SKU-{$uniqueSkuFragment}", 'stock' => 10]);

        $results = app(SearchService::class)->search($uniqueSkuFragment);

        $this->assertTrue($results->pluck('id')->contains($product->id));
    }

    public function test_search_is_case_insensitive(): void
    {
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts-'.uniqid()]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'CaseSensitiveName', 'slug' => 'case-'.uniqid()]);

        $results = app(SearchService::class)->search('casesensitivename');

        $this->assertTrue($results->pluck('id')->contains($product->id));
    }

    public function test_an_unrelated_term_returns_no_results(): void
    {
        Category::create(['name' => 'Bolts', 'slug' => 'bolts-'.uniqid()]);

        $results = app(SearchService::class)->search('zzz-totally-unrelated-'.uniqid());

        $this->assertCount(0, $results);
    }

    /**
     * @return array{0: Attribute, 1: Product, 2: Product}
     */
    private function twoProductsOneMatchingMaterial(string $matchingValue, string $attributeName = 'Material'): array
    {
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts-'.uniqid()]);
        $attribute = Attribute::create([
            'name' => $attributeName,
            'key' => strtolower($attributeName).'-'.uniqid(),
            'data_type' => 'string',
        ]);
        CategoryAttribute::create([
            'category_id' => $category->id,
            'attribute_id' => $attribute->id,
            'is_variant_defining' => true,
        ]);

        $matchingProduct = Product::create(['category_id' => $category->id, 'name' => 'Matching Product', 'slug' => 'matching-'.uniqid()]);
        $matchingVariant = ProductVariant::create(['product_id' => $matchingProduct->id, 'sku' => 'SKU-'.$this->randomLetters(), 'stock' => 10]);
        VariantAttributeValue::create(['product_variant_id' => $matchingVariant->id, 'attribute_id' => $attribute->id, 'value' => $matchingValue]);

        $otherProduct = Product::create(['category_id' => $category->id, 'name' => 'Other Product', 'slug' => 'other-'.uniqid()]);
        $otherVariant = ProductVariant::create(['product_id' => $otherProduct->id, 'sku' => 'SKU-'.$this->randomLetters(), 'stock' => 10]);
        VariantAttributeValue::create(['product_variant_id' => $otherVariant->id, 'attribute_id' => $attribute->id, 'value' => 'Something Else']);

        return [$attribute, $matchingProduct, $otherProduct];
    }

    /**
     * Letters only (not uniqid()'s hex digits) so a SKU can never
     * accidentally contain a short numeric search term like "20" as a
     * substring and skew the "same results" assertions above.
     */
    private function randomLetters(int $length = 10): string
    {
        $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $letters[random_int(0, 25)];
        }

        return $result;
    }
}

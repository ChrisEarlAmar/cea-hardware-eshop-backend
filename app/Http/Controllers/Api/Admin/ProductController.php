<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Staff-only catalog management for products themselves - deliberately in
 * the Admin namespace so it does not collide with the public, read-only
 * App\Http\Controllers\Api\ProductController (single `show` action).
 * Variant/SKU/stock management lives in ProductVariantController and
 * StockAdjustmentController instead of being crammed into this payload.
 * Reachable only via the `staff` middleware (see routes/api.php).
 */
class ProductController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['sometimes', 'integer'],
            'search' => ['sometimes', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = Product::query()
            ->with('category:id,name,slug')
            ->withCount('variants')
            ->when($validated['category_id'] ?? null, fn ($q, $categoryId) => $q->where('category_id', $categoryId))
            ->when($validated['search'] ?? null, fn ($q, $search) => $q->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')
            ->paginate($validated['per_page'] ?? 25);

        return ApiResponse::success(
            $paginator->getCollection()->map(fn (Product $product) => $this->payload($product)),
            meta: [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    /**
     * Full detail for the admin product page - variants with their price
     * tiers included, which the public ProductResource deliberately omits.
     */
    public function show(Product $product): JsonResponse
    {
        $product->load(['category:id,name,slug', 'variants.priceTiers']);

        return ApiResponse::success($this->detailPayload($product));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'alpha_dash', 'unique:products,slug'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'is_featured' => ['sometimes', 'boolean'],
            'sale_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sale_ends_at' => ['nullable', 'date'],
        ]);

        $product = Product::create([
            'category_id' => $validated['category_id'],
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? $this->uniqueSlug($validated['name']),
            'description' => $validated['description'] ?? null,
            'image_url' => $validated['image_url'] ?? null,
            'is_featured' => $validated['is_featured'] ?? false,
            'sale_discount_percent' => $validated['sale_discount_percent'] ?? null,
            'sale_ends_at' => $validated['sale_ends_at'] ?? null,
        ]);

        $this->auditLogger->log($request->user(), 'product.create', $product, ['name' => $product->name]);

        return ApiResponse::success($this->payload($product->load('category:id,name,slug')->loadCount('variants')), status: 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes', 'string', 'max:255', 'alpha_dash',
                Rule::unique('products', 'slug')->ignore($product->id),
            ],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'is_featured' => ['sometimes', 'boolean'],
            'sale_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sale_ends_at' => ['nullable', 'date'],
        ]);

        $product->update($validated);

        $this->auditLogger->log($request->user(), 'product.update', $product, $validated);

        return ApiResponse::success(
            $this->payload($product->fresh()->load('category:id,name,slug')->loadCount('variants'))
        );
    }

    /**
     * Refused with a 422 if any of this product's variants already appear
     * on a real order - order_items.product_variant_id is a restrict (not
     * cascade) foreign key, by design (see create_order_items_table), so a
     * past order line must never be able to lose the variant it points at.
     * Everything else about a product (variants, price tiers, images)
     * cascades on delete.
     */
    public function destroy(Request $request, Product $product): JsonResponse
    {
        $variantIds = $product->variants()->pluck('id');

        if ($variantIds->isNotEmpty() && DB::table('order_items')->whereIn('product_variant_id', $variantIds)->exists()) {
            return ApiResponse::error(
                'product_has_orders',
                'This product has variants that appear in existing orders and cannot be deleted.',
                422,
            );
        }

        $this->auditLogger->log($request->user(), 'product.delete', $product, ['name' => $product->name]);

        $product->delete();

        return ApiResponse::success(['deleted' => true]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (Product::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function payload(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description,
            'image_url' => $product->image_url,
            'is_featured' => (bool) $product->is_featured,
            'sale_discount_percent' => $product->sale_discount_percent,
            'sale_ends_at' => $product->sale_ends_at?->toIso8601String(),
            'category' => $product->relationLoaded('category') && $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
                'slug' => $product->category->slug,
            ] : null,
            'variant_count' => $product->variants_count ?? $product->variants()->count(),
            'created_at' => $product->created_at->toIso8601String(),
        ];
    }

    private function detailPayload(Product $product): array
    {
        return [
            ...$this->payload($product),
            'variants' => $product->variants->map(fn ($variant) => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'stock' => $variant->stock,
                'price_tiers' => $variant->priceTiers->map(fn ($tier) => [
                    'id' => $tier->id,
                    'min_quantity' => $tier->min_quantity,
                    'unit_price' => $tier->unit_price,
                ])->values(),
            ])->values(),
        ];
    }
}

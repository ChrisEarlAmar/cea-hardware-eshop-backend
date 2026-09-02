<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Staff-only catalog management for the top level of the catalog tree -
 * see App\Http\Controllers\Api\CategoryController for the public,
 * read-only browsing surface. Reachable only via the `staff` middleware
 * (see routes/api.php).
 */
class CategoryController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function index(): JsonResponse
    {
        $categories = Category::withCount('products')->orderBy('name')->get();

        return ApiResponse::success($categories->map(fn (Category $category) => $this->payload($category)));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'alpha_dash', 'unique:categories,slug'],
        ]);

        $category = Category::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? $this->uniqueSlug($validated['name']),
        ]);

        $this->auditLogger->log($request->user(), 'category.create', $category, ['name' => $category->name]);

        return ApiResponse::success($this->payload($category->loadCount('products')), status: 201);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes', 'string', 'max:255', 'alpha_dash',
                Rule::unique('categories', 'slug')->ignore($category->id),
            ],
        ]);

        $category->update($validated);

        $this->auditLogger->log($request->user(), 'category.update', $category, $validated);

        return ApiResponse::success($this->payload($category->fresh()->loadCount('products')));
    }

    /**
     * A category still holding products is refused with a 422 rather than
     * left to fail as a foreign key constraint violation at the database -
     * products.category_id has no cascade/null-on-delete behavior (see the
     * create_products_table migration), so an unguarded delete here would
     * otherwise surface as an ugly 500.
     */
    public function destroy(Request $request, Category $category): JsonResponse
    {
        if ($category->products()->exists()) {
            return ApiResponse::error(
                'category_has_products',
                'This category still has products assigned to it. Move or delete them first.',
                422,
            );
        }

        $this->auditLogger->log($request->user(), 'category.delete', $category, ['name' => $category->name]);

        $category->delete();

        return ApiResponse::success(['deleted' => true]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (Category::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function payload(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'product_count' => $category->products_count ?? $category->products()->count(),
            'created_at' => $category->created_at->toIso8601String(),
        ];
    }
}

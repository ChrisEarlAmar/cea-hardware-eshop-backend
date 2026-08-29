<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'images' => $this->images->map(fn ($image) => [
                'url' => $image->url,
                'alt' => $image->alt,
            ])->values(),
            'category' => new CategoryResource($this->category),
            // Which attributes apply to this product, and which of them
            // define a purchasable variant vs. are purely descriptive -
            // driven by the category, not the product itself.
            'attributes' => $this->category->categoryAttributes
                ->map(fn ($categoryAttribute) => [
                    'id' => $categoryAttribute->attribute->id,
                    'key' => $categoryAttribute->attribute->key,
                    'name' => $categoryAttribute->attribute->name,
                    'unit' => $categoryAttribute->attribute->unit,
                    'data_type' => $categoryAttribute->attribute->data_type,
                    'is_variant_defining' => $categoryAttribute->is_variant_defining,
                ])
                ->values(),
            'variants' => $this->variants
                ->map(fn ($variant) => [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'stock' => $variant->stock,
                    'values' => $variant->attributeValues
                        ->mapWithKeys(fn ($value) => [$value->attribute->key => $value->value]),
                ])
                ->values(),
        ];
    }
}

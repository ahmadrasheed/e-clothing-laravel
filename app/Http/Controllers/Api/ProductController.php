<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $products = Product::query()
            ->with(['category', 'variants'])
            ->when($request->query('category_id'), fn ($q, $id) => $q->where('category_id', $id))
            ->when($request->query('search'), fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->latest()
            ->paginate((int) $request->query('per_page', 15));

        return response()->json([
            'data' => ProductResource::collection($products->items()),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function show(Product $product): JsonResponse
    {
        $product->load(['category', 'variants']);

        return response()->json(['data' => new ProductResource($product)]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateProduct($request);

        $product = DB::transaction(function () use ($validated) {
            $product = Product::create([
                'category_id' => $validated['category_id'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'base_price' => $validated['base_price'],
                'image_url' => $validated['image_url'] ?? null,
            ]);

            foreach ($validated['variants'] ?? [] as $variant) {
                $product->variants()->create($variant);
            }

            return $product;
        });

        $product->load(['category', 'variants']);

        return response()->json(['data' => new ProductResource($product)], 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $this->validateProduct($request, $product->id);

        DB::transaction(function () use ($validated, $product) {
            $product->update([
                'category_id' => $validated['category_id'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'base_price' => $validated['base_price'],
                'image_url' => $validated['image_url'] ?? null,
            ]);

            if (array_key_exists('variants', $validated)) {
                $keepIds = [];

                foreach ($validated['variants'] as $variant) {
                    $saved = $product->variants()->updateOrCreate(
                        ['id' => $variant['id'] ?? null],
                        [
                            'size' => $variant['size'],
                            'color' => $variant['color'],
                            'stock_quantity' => $variant['stock_quantity'],
                        ]
                    );
                    $keepIds[] = $saved->id;
                }

                $product->variants()->whereNotIn('id', $keepIds)->delete();
            }
        });

        $product->load(['category', 'variants']);

        return response()->json(['data' => new ProductResource($product)]);
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(null, 204);
    }

    private function validateProduct(Request $request, ?int $productId = null): array
    {
        return $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'image_url' => ['nullable', 'url'],
            'variants' => ['sometimes', 'array'],
            'variants.*.id' => [
                'sometimes',
                'nullable',
                Rule::exists('product_variants', 'id')->where('product_id', $productId),
            ],
            'variants.*.size' => ['required_with:variants', 'in:S,M,L,XL'],
            'variants.*.color' => ['required_with:variants', 'string', 'max:50'],
            'variants.*.stock_quantity' => ['required_with:variants', 'integer', 'min:0'],
        ]);
    }
}

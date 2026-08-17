<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CatalogIndexRequest;
use App\Http\Requests\Api\ProductImportRequest;
use App\Http\Requests\Api\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Imports\ProductImport;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;

class ProductController extends Controller
{
    public function index(CatalogIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $products = Product::query()
            ->where('business_id', $this->businessId($request))
            ->with('category')
            ->when($filters['search'] ?? null, function ($query, $search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->when($filters['category_id'] ?? null, fn ($query, $categoryId) => $query->where('category_id', $categoryId))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('name')
            ->paginate((int) ($filters['per_page'] ?? 15));

        return ProductResource::collection($products);
    }

    public function store(ProductRequest $request): ProductResource
    {
        $product = Product::query()->create([
            ...$request->validated(),
            'business_id' => $this->businessId($request),
        ]);

        return new ProductResource($product->load('category'));
    }

    public function import(ProductImportRequest $request): JsonResource
    {
        $import = new ProductImport($this->businessId($request));

        Excel::import($import, $request->file('file'));

        return new JsonResource($import->summary());
    }

    public function show(Request $request, Product $product): ProductResource
    {
        abort_unless($product->business_id === $this->businessId($request), 404);

        return new ProductResource($product->load('category'));
    }

    public function update(ProductRequest $request, Product $product): ProductResource
    {
        abort_unless($product->business_id === $this->businessId($request), 404);

        $product->update($request->validated());

        return new ProductResource($product->load('category'));
    }

    public function destroy(Request $request, Product $product): Response
    {
        abort_unless($product->business_id === $this->businessId($request), 404);

        $product->update(['status' => 'inactive']);

        return response()->noContent();
    }
}

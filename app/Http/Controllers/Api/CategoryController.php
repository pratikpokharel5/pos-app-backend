<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CatalogIndexRequest;
use App\Http\Requests\Api\CategoryImportRequest;
use App\Http\Requests\Api\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Imports\CategoryImport;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;

class CategoryController extends Controller
{
    public function index(CatalogIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $categories = Category::query()
            ->where('business_id', $this->businessId($request))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('name')
            ->paginate((int) ($filters['per_page'] ?? 15));

        return CategoryResource::collection($categories);
    }

    public function store(CategoryRequest $request): CategoryResource
    {
        return new CategoryResource(Category::query()->create([
            ...$request->validated(),
            'business_id' => $this->businessId($request),
        ]));
    }

    public function import(CategoryImportRequest $request): JsonResource
    {
        $import = new CategoryImport($this->businessId($request));

        Excel::import($import, $request->file('file'));

        return new JsonResource($import->summary());
    }

    public function show(Request $request, Category $category): CategoryResource
    {
        abort_unless($category->business_id === $this->businessId($request), 404);

        return new CategoryResource($category);
    }

    public function update(CategoryRequest $request, Category $category): CategoryResource
    {
        abort_unless($category->business_id === $this->businessId($request), 404);

        $category->update($request->validated());

        return new CategoryResource($category);
    }

    public function destroy(Request $request, Category $category): Response
    {
        abort_unless($category->business_id === $this->businessId($request), 404);

        $category->update(['status' => 'inactive']);

        return response()->noContent();
    }
}

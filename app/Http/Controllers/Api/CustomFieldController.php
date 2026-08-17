<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CatalogIndexRequest;
use App\Http\Requests\Api\CustomFieldRequest;
use App\Http\Resources\CustomFieldResource;
use App\Models\CustomField;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CustomFieldController extends Controller
{
    public function index(CatalogIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $fields = CustomField::query()
            ->where('business_id', $this->businessId($request))
            ->when($filters['applies_to'] ?? null, fn ($query, $appliesTo) => $query->where('applies_to', $appliesTo))
            ->when(array_key_exists('is_active', $filters), fn ($query) => $query->where('is_active', $filters['is_active']))
            ->orderBy('sort_order')
            ->orderBy('label')
            ->paginate((int) ($filters['per_page'] ?? 50));

        return CustomFieldResource::collection($fields);
    }

    public function store(CustomFieldRequest $request): CustomFieldResource
    {
        return new CustomFieldResource(CustomField::query()->create([
            ...$request->validated(),
            'business_id' => $this->businessId($request),
        ]));
    }

    public function show(Request $request, CustomField $customField): CustomFieldResource
    {
        abort_unless($customField->business_id === $this->businessId($request), 404);

        return new CustomFieldResource($customField);
    }

    public function update(CustomFieldRequest $request, CustomField $customField): CustomFieldResource
    {
        abort_unless($customField->business_id === $this->businessId($request), 404);

        $customField->update($request->validated());

        return new CustomFieldResource($customField);
    }

    public function destroy(Request $request, CustomField $customField): Response
    {
        abort_unless($customField->business_id === $this->businessId($request), 404);

        $customField->update(['is_active' => false]);

        return response()->noContent();
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CatalogIndexRequest;
use App\Http\Requests\Api\CustomerRequest;
use App\Http\Requests\Api\CustomerStatusRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerController extends Controller
{
    public function index(CatalogIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $customers = Customer::query()
            ->where('business_id', $this->businessId($request))
            ->filter($filters)
            ->latest()
            ->paginate((int) ($filters['per_page'] ?? 15));

        return CustomerResource::collection($customers);
    }

    public function store(CustomerRequest $request): CustomerResource
    {
        return new CustomerResource(Customer::query()->create([
            ...$request->validated(),
            'business_id' => $this->businessId($request),
        ]));
    }

    public function show(Request $request, Customer $customer): CustomerResource
    {
        abort_unless($customer->business_id === $this->businessId($request), 404);

        return new CustomerResource($customer);
    }

    public function update(CustomerRequest $request, Customer $customer): CustomerResource
    {
        abort_unless($customer->business_id === $this->businessId($request), 404);

        $customer->update($request->validated());

        return new CustomerResource($customer);
    }

    public function updateStatus(CustomerStatusRequest $request, Customer $customer): CustomerResource
    {
        abort_unless($customer->business_id === $this->businessId($request), 404);

        $customer->update($request->validated());

        return new CustomerResource($customer);
    }
}

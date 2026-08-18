<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SaleIndexRequest;
use App\Http\Requests\Api\StoreSaleRequest;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SaleController extends Controller
{
    public function index(SaleIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $sales = Sale::query()
            ->where('business_id', $this->businessId($request))
            ->with(['customer', 'user', 'payments'])
            ->when($filters['search'] ?? null, function ($query, $search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($customerQuery) use ($search): void {
                            $customerQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        })
                        ->orWhereHas('items', fn ($itemQuery) => $itemQuery->where('item_name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['payment_method'] ?? null, function ($query, $method): void {
                $query->whereHas('payments', fn ($paymentQuery) => $paymentQuery->where('method', $method));
            })
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('sold_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('sold_at', '<=', $to))
            ->latest('sold_at')
            ->paginate((int) ($filters['per_page'] ?? 15));

        return SaleResource::collection($sales);
    }

    public function store(StoreSaleRequest $request, SaleService $saleService): SaleResource
    {
        $sale = $saleService->create(
            $request->validated(),
            $this->businessId($request),
            $request->user()?->id,
        );

        return new SaleResource($sale);
    }

    public function show(Request $request, Sale $sale): SaleResource
    {
        abort_unless($sale->business_id === $this->businessId($request), 404);

        return new SaleResource($sale->load([
            'customer',
            'user',
            'items.product.category',
            'items.customFieldValues.customField',
            'payments',
            'customFieldValues.customField',
        ]));
    }

    public function invoice(Request $request, Sale $sale): SaleResource
    {
        return $this->show($request, $sale);
    }

    public function void(Request $request, Sale $sale): JsonResponse
    {
        abort_unless($sale->business_id === $this->businessId($request), 404);

        if ($sale->status !== 'completed') {
            return response()->json([
                'message' => 'Only completed sales can be voided.',
            ], 422);
        }

        $sale->update(['status' => 'voided']);

        return response()->json([
            'message' => 'Sale voided successfully.',
            'sale' => new SaleResource($sale->refresh()->load(['customer', 'items', 'payments'])),
        ]);
    }

    public function unhold(Request $request, Sale $sale): JsonResponse
    {
        abort_unless($sale->business_id === $this->businessId($request), 404);

        if ($sale->status !== 'held') {
            return response()->json([
                'message' => 'Only held sales can be unheld.',
            ], 422);
        }

        $sale->delete();

        return response()->json([
            'message' => 'Held sale removed successfully.',
        ]);
    }
}

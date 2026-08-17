<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function summary(Request $request, ReportService $reportService): JsonResponse
    {
        $today = now()->toDateString();
        $businessId = $this->businessId($request);
        $recentSales = Sale::query()
            ->where('business_id', $businessId)
            ->with(['customer', 'payments'])
            ->latest('sold_at')
            ->limit(5)
            ->get();

        return response()->json([
            'today' => $reportService->salesSummary($businessId, $today, $today),
            'payment_breakdown' => $reportService->paymentSummary($businessId, $today, $today),
            'recent_sales' => SaleResource::collection($recentSales),
        ]);
    }
}

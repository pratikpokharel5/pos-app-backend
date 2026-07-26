<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function summary(ReportService $reportService): JsonResponse
    {
        $today = now()->toDateString();
        $recentSales = Sale::query()
            ->with(['customer', 'payments'])
            ->latest('sold_at')
            ->limit(5)
            ->get();

        return response()->json([
            'today' => $reportService->salesSummary($today, $today),
            'payment_breakdown' => $reportService->paymentSummary($today, $today),
            'recent_sales' => SaleResource::collection($recentSales),
        ]);
    }
}

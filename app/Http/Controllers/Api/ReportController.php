<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ReportRequest;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function salesSummary(ReportRequest $request, ReportService $reportService): JsonResponse
    {
        $filters = $request->validated();

        return response()->json($reportService->salesSummary(
            $this->businessId($request),
            $filters['from'] ?? null,
            $filters['to'] ?? null,
        ));
    }

    public function paymentSummary(ReportRequest $request, ReportService $reportService): JsonResponse
    {
        $filters = $request->validated();

        return response()->json($reportService->paymentSummary(
            $this->businessId($request),
            $filters['from'] ?? null,
            $filters['to'] ?? null,
        ));
    }

    public function topProducts(ReportRequest $request, ReportService $reportService): JsonResponse
    {
        $filters = $request->validated();

        return response()->json($reportService->topProducts(
            $this->businessId($request),
            $filters['from'] ?? null,
            $filters['to'] ?? null,
            (int) ($filters['limit'] ?? 10),
        ));
    }
}

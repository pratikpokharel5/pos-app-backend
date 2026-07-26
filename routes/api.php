<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessSettingController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomFieldController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('auth/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::get('dashboard/summary', [DashboardController::class, 'summary']);

    Route::get('settings', [BusinessSettingController::class, 'show']);
    Route::put('settings', [BusinessSettingController::class, 'update'])
        ->middleware('role:admin');

    Route::apiResource('categories', CategoryController::class)
        ->only(['index', 'show']);
    Route::apiResource('categories', CategoryController::class)
        ->except(['index', 'show'])
        ->middleware('role:admin');

    Route::apiResource('products', ProductController::class)
        ->only(['index', 'show']);
    Route::apiResource('products', ProductController::class)
        ->except(['index', 'show'])
        ->middleware('role:admin');

    Route::apiResource('customers', CustomerController::class)
        ->only(['index', 'show']);
    Route::apiResource('customers', CustomerController::class)
        ->except(['index', 'show'])
        ->middleware('role:admin');

    Route::apiResource('custom-fields', CustomFieldController::class)
        ->parameters(['custom-fields' => 'customField'])
        ->middleware('role:admin');

    Route::get('sales/{sale}/invoice', [SaleController::class, 'invoice']);
    Route::post('sales/{sale}/void', [SaleController::class, 'void'])
        ->middleware('role:admin');
    Route::apiResource('sales', SaleController::class)->only(['index', 'store', 'show']);

    Route::middleware('role:admin')->group(function (): void {
        Route::apiResource('users', UserController::class)
            ->except(['show']);

        Route::get('reports/sales-summary', [ReportController::class, 'salesSummary']);
        Route::get('reports/payment-summary', [ReportController::class, 'paymentSummary']);
        Route::get('reports/top-products', [ReportController::class, 'topProducts']);
    });
});

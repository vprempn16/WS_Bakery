<?php

use App\Modules\Api\V1\Organization\Controllers\OrganizationController;
use App\Modules\Api\V1\User\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // 1. Publicly accessible routes
    Route::post('Organization/new', [OrganizationController::class, 'store']);
    Route::post('auth/login', [\App\Modules\Api\V1\User\Controllers\AuthController::class, 'login']);

    // 2. Protected routes requiring authentication AND organization context checking
    Route::middleware(['auth:sanctum', 'check.org'])->group(function () {
        // Logout endpoint
        Route::post('auth/logout', [\App\Modules\Api\V1\User\Controllers\AuthController::class, 'logout']);

        // Global Search endpoint
        Route::get('search/{fieldname}', [\App\Modules\Api\V1\GlobalSearch\Controllers\GlobalSearchController::class, 'searchByField']);

        // Header endpoints (filter field definitions)
        Route::get('{module}/new', [\App\Modules\Api\V1\SavedFilter\Controllers\HeaderController::class, 'getCreateFields']);
        Route::get('{module}/headers', [\App\Modules\Api\V1\SavedFilter\Controllers\HeaderController::class, 'show']);
        Route::get('{module}/headers/{filterId}', [\App\Modules\Api\V1\SavedFilter\Controllers\HeaderController::class, 'show']);

        // Global Inline Edit endpoint
        Route::patch('{module}/{id}/inline-edit', [\App\Http\Controllers\GlobalInlineEditController::class, 'update']);

        // Global Audit Log endpoint
        Route::get('{module}/{id}/audit-log', [\App\Http\Controllers\GlobalAuditLogController::class, 'index']);

        // Organization endpoints
        Route::prefix('Organization')->group(function () {
            Route::get('search', [OrganizationController::class, 'search']);
            Route::get('{id}', [OrganizationController::class, 'show']);
            Route::post('{id}', [OrganizationController::class, 'update']);
            Route::delete('{id}', [OrganizationController::class, 'destroy']);
        });

        // Branch endpoints
        Route::prefix('Branch')->group(function () {
            Route::get('', [\App\Modules\Api\V1\Branch\Controllers\BranchController::class, 'index']);
            Route::post('new', [\App\Modules\Api\V1\Branch\Controllers\BranchController::class, 'store']);
            Route::get('{id}', [\App\Modules\Api\V1\Branch\Controllers\BranchController::class, 'show']);
            Route::post('{id}', [\App\Modules\Api\V1\Branch\Controllers\BranchController::class, 'update']);
            Route::delete('{id}', [\App\Modules\Api\V1\Branch\Controllers\BranchController::class, 'destroy']);
        });

        // Branch Transfer endpoints
        Route::prefix('BranchTransfer')->group(function () {
            Route::get('', [\App\Modules\Api\V1\BranchTransfer\Controllers\BranchTransferController::class, 'index']);
            Route::post('new', [\App\Modules\Api\V1\BranchTransfer\Controllers\BranchTransferController::class, 'store']);
            Route::get('{id}', [\App\Modules\Api\V1\BranchTransfer\Controllers\BranchTransferController::class, 'show']);
            Route::post('{id}', [\App\Modules\Api\V1\BranchTransfer\Controllers\BranchTransferController::class, 'update']);
            Route::delete('{id}', [\App\Modules\Api\V1\BranchTransfer\Controllers\BranchTransferController::class, 'destroy']);
        });

        // Branch Stock endpoint
        Route::get('BranchStock', [\App\Modules\Api\V1\BranchTransfer\Controllers\BranchStockController::class, 'index']);

        // Reports endpoints
        Route::prefix('Reports')->group(function () {
            Route::get('ExpiringBatches', [\App\Modules\Api\V1\Reports\Controllers\ExpiryReportController::class, 'expiringBatches']);
        });

        // Dashboard endpoint
        Route::get('Dashboard/Summary', [\App\Modules\Api\V1\Reports\Controllers\DashboardController::class, 'summary']);

        // Billing
        Route::prefix('Billing')->group(function () {
            Route::get('pos-products/category', [\App\Modules\Api\V1\Billing\Controllers\BillingController::class, 'getPosCategories']);
            Route::get('pos-products', [\App\Modules\Api\V1\Billing\Controllers\BillingController::class, 'getPosProducts']);
            Route::get('new', [\App\Modules\Api\V1\Billing\Controllers\BillingController::class, 'createForm']);
            Route::get('headers', [\App\Modules\Api\V1\Billing\Controllers\BillingController::class, 'headerfields']);
            Route::get('{id}', [\App\Modules\Api\V1\Billing\Controllers\BillingController::class, 'show']);
            Route::get('/', [\App\Modules\Api\V1\Billing\Controllers\BillingController::class, 'index']);
            Route::post('new', [\App\Modules\Api\V1\Billing\Controllers\BillingController::class, 'store']);
            Route::post('{id}', [\App\Modules\Api\V1\Billing\Controllers\BillingController::class, 'update']);
        });

        // Branch Daily Report (Sales & Returns)
        Route::prefix('BranchDailyReport')->group(function () {
            Route::get('', [\App\Modules\Api\V1\BranchSales\Controllers\BranchDailyReportController::class, 'index']);
            Route::post('new', [\App\Modules\Api\V1\BranchSales\Controllers\BranchDailyReportController::class, 'store']);
            Route::get('{id}', [\App\Modules\Api\V1\BranchSales\Controllers\BranchDailyReportController::class, 'show']);
        });

        // Saved Filter endpoints
        Route::prefix('filters')->group(function () {
            Route::get('', [\App\Modules\Api\V1\SavedFilter\Controllers\SavedFilterController::class, 'index']);
            Route::post('new', [\App\Modules\Api\V1\SavedFilter\Controllers\SavedFilterController::class, 'store']);
            Route::delete('{id}', [\App\Modules\Api\V1\SavedFilter\Controllers\SavedFilterController::class, 'destroy']);
        });


        // Vendor endpoints
        Route::prefix('Vendor')->group(function () {
            Route::get('', [\App\Modules\Api\V1\Vendor\Controllers\VendorController::class, 'index']);
            Route::post('new', [\App\Modules\Api\V1\Vendor\Controllers\VendorController::class, 'store']);
            Route::get('{id}', [\App\Modules\Api\V1\Vendor\Controllers\VendorController::class, 'show']);
            Route::post('{id}', [\App\Modules\Api\V1\Vendor\Controllers\VendorController::class, 'update']);
            Route::delete('{id}', [\App\Modules\Api\V1\Vendor\Controllers\VendorController::class, 'destroy']);
        });

        // Ingredient endpoints
        Route::prefix('Ingredient')->group(function () {
            Route::get('', [\App\Modules\Api\V1\Ingredient\Controllers\IngredientController::class, 'index']);
            Route::get('low-stock', [\App\Modules\Api\V1\Ingredient\Controllers\IngredientController::class, 'lowStock']);
            Route::post('new', [\App\Modules\Api\V1\Ingredient\Controllers\IngredientController::class, 'store']);
            Route::get('{id}', [\App\Modules\Api\V1\Ingredient\Controllers\IngredientController::class, 'show']);
            Route::post('{id}', [\App\Modules\Api\V1\Ingredient\Controllers\IngredientController::class, 'update']);
            Route::delete('{id}', [\App\Modules\Api\V1\Ingredient\Controllers\IngredientController::class, 'destroy']);
        });

        // Inventory Transaction endpoints
        Route::prefix('InventoryTransaction')->group(function () {
            Route::get('', [\App\Modules\Api\V1\InventoryTransaction\Controllers\InventoryTransactionController::class, 'index']);
            Route::post('new', [\App\Modules\Api\V1\InventoryTransaction\Controllers\InventoryTransactionController::class, 'store']);
            Route::get('{id}', [\App\Modules\Api\V1\InventoryTransaction\Controllers\InventoryTransactionController::class, 'show']);
        });

        // Product and Recipe endpoints
        Route::prefix('Product')->group(function () {
            Route::get('', [\App\Modules\Api\V1\Product\Controllers\ProductController::class, 'index']);
            Route::post('new', [\App\Modules\Api\V1\Product\Controllers\ProductController::class, 'store']);
            Route::get('{id}', [\App\Modules\Api\V1\Product\Controllers\ProductController::class, 'show']);
            Route::post('{id}', [\App\Modules\Api\V1\Product\Controllers\ProductController::class, 'update']);
            Route::delete('{id}', [\App\Modules\Api\V1\Product\Controllers\ProductController::class, 'destroy']);

            // Recipe endpoints (nested under product)
            Route::get('{productId}/recipe', [\App\Modules\Api\V1\Recipe\Controllers\RecipeController::class, 'index']);
            Route::post('{productId}/recipe/new', [\App\Modules\Api\V1\Recipe\Controllers\RecipeController::class, 'store']);
            Route::get('{productId}/recipe/{ingredientId}', [\App\Modules\Api\V1\Recipe\Controllers\RecipeController::class, 'show']);
            Route::delete('{productId}/recipe/{ingredientId}', [\App\Modules\Api\V1\Recipe\Controllers\RecipeController::class, 'destroy']);
        });

        // Production Batch endpoints
        Route::prefix('ProductionBatch')->group(function () {
            Route::get('', [\App\Modules\Api\V1\ProductionBatch\Controllers\ProductionBatchController::class, 'index']);
            Route::post('new', [\App\Modules\Api\V1\ProductionBatch\Controllers\ProductionBatchController::class, 'store']);
            Route::get('{id}', [\App\Modules\Api\V1\ProductionBatch\Controllers\ProductionBatchController::class, 'show']);
            Route::post('{id}', [\App\Modules\Api\V1\ProductionBatch\Controllers\ProductionBatchController::class, 'update']);
            Route::delete('{id}', [\App\Modules\Api\V1\ProductionBatch\Controllers\ProductionBatchController::class, 'destroy']);
        });

        // User endpoints under settings
        Route::prefix('settings/User')->group(function () {
            Route::get('', [UserController::class, 'index']);
            Route::post('new', [UserController::class, 'store']);
            Route::get('{id}', [UserController::class, 'show']);
            Route::post('{id}', [UserController::class, 'update']);
            Route::delete('{id}', [UserController::class, 'destroy']);
        });
    });
});


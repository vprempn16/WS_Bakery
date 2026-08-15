<?php

use App\Modules\Api\V1\Organization\Controllers\OrganizationController;
use App\Modules\Api\V1\User\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // 1. Publicly accessible routes (PascalCase + lowercase alias for clients)
    Route::post('Organization/new', [OrganizationController::class, 'store'])
        ->middleware('throttle:auth-register');
    Route::post('organization/new', [OrganizationController::class, 'store'])
        ->middleware('throttle:auth-register');
    Route::post('auth/login', [\App\Modules\Api\V1\User\Controllers\AuthController::class, 'login'])
        ->middleware('throttle:auth-login');

    // 2. Protected routes requiring authentication AND organization context checking
    Route::middleware(['auth:sanctum', 'check.org'])->group(function () {
        // Session / profile
        Route::get('auth/me', [\App\Modules\Api\V1\User\Controllers\AuthController::class, 'me']);

        // Logout endpoint
        Route::post('auth/logout', [\App\Modules\Api\V1\User\Controllers\AuthController::class, 'logout']);
        Route::post('auth/change-password', [\App\Modules\Api\V1\User\Controllers\AuthController::class, 'changePassword'])
            ->middleware('throttle:writes');

        // Allowed modules endpoint
        Route::get('allowed_modules', [\App\Modules\Api\V1\Settings\Controllers\ModuleController::class, 'allowedModules']);

        // Global Search endpoint
        Route::get('search/{fieldname}', [\App\Modules\Api\V1\GlobalSearch\Controllers\GlobalSearchController::class, 'searchByField']);

        // Header endpoints (filter field definitions)
        Route::get('{module}/new', [\App\Modules\Api\V1\SavedFilter\Controllers\HeaderController::class, 'getCreateFields']);
        Route::get('{module}/headers', [\App\Modules\Api\V1\SavedFilter\Controllers\HeaderController::class, 'show']);
	    Route::get('{module}/headers/default', [\App\Modules\Api\V1\SavedFilter\Controllers\HeaderController::class, 'show']);
        Route::get('{module}/headers/{filterId}', [\App\Modules\Api\V1\SavedFilter\Controllers\HeaderController::class, 'show']);

        // Global Inline Edit endpoint
        Route::patch('{module}/{id}/inline-edit', [\App\Http\Controllers\GlobalInlineEditController::class, 'update'])
            ->middleware('throttle:writes');

        // Global Audit Log endpoint
        Route::get('{module}/{id}/audit-log', [\App\Http\Controllers\GlobalAuditLogController::class, 'index']);

        // Organization endpoints
        Route::prefix('Organization')->group(function () {
            Route::get('search', [OrganizationController::class, 'search']);
            Route::get('{id}', [OrganizationController::class, 'show']);
            Route::post('{id}', [OrganizationController::class, 'update'])
                ->middleware('throttle:writes');
            Route::delete('{id}', [OrganizationController::class, 'destroy'])
                ->middleware('throttle:writes');
        });

        // Branch endpoints
        Route::prefix('Branch')->group(function () {
            Route::get('', [\App\Modules\Api\V1\Branch\Controllers\BranchController::class, 'index']);
            Route::post('new', [\App\Modules\Api\V1\Branch\Controllers\BranchController::class, 'store'])
                ->middleware('throttle:writes');
            Route::get('{id}/transfer-history', [\App\Modules\Api\V1\Related\Controllers\RelatedRecordsController::class, 'branchTransferHistory']);
            Route::get('{id}/inventory', [\App\Modules\Api\V1\Related\Controllers\RelatedRecordsController::class, 'branchInventory']);
            Route::get('{id}', [\App\Modules\Api\V1\Branch\Controllers\BranchController::class, 'show']);
            Route::post('{id}', [\App\Modules\Api\V1\Branch\Controllers\BranchController::class, 'update'])
                ->middleware('throttle:writes');
            Route::delete('{id}', [\App\Modules\Api\V1\Branch\Controllers\BranchController::class, 'destroy'])
                ->middleware('throttle:writes');
        });

        // Branch Transfer endpoints
        Route::prefix('BranchTransfer')->group(function () {
            Route::get('', [\App\Modules\Api\V1\BranchTransfer\Controllers\BranchTransferController::class, 'index']);
            Route::post('new', [\App\Modules\Api\V1\BranchTransfer\Controllers\BranchTransferController::class, 'store'])
                ->middleware('throttle:writes');
            Route::get('{id}', [\App\Modules\Api\V1\BranchTransfer\Controllers\BranchTransferController::class, 'show']);
            Route::get('{id}/invoice', [\App\Modules\Api\V1\BranchTransfer\Controllers\BranchTransferController::class, 'invoice']);
            Route::post('{id}', [\App\Modules\Api\V1\BranchTransfer\Controllers\BranchTransferController::class, 'update'])
                ->middleware('throttle:writes');
            Route::delete('{id}', [\App\Modules\Api\V1\BranchTransfer\Controllers\BranchTransferController::class, 'destroy'])
                ->middleware('throttle:writes');
        });

        // Branch Stock endpoint
        Route::get('BranchStock', [\App\Modules\Api\V1\BranchTransfer\Controllers\BranchStockController::class, 'index']);

        // Reports endpoints
        Route::prefix('Reports')->middleware('throttle:expensive')->group(function () {
            Route::get('ExpiringBatches', [\App\Modules\Api\V1\Reports\Controllers\ExpiryReportController::class, 'expiringBatches']);
        });

        // Dashboard endpoint
        Route::get('Dashboard/Summary', [\App\Modules\Api\V1\Reports\Controllers\DashboardController::class, 'summary'])
            ->middleware('throttle:expensive');

        // Billing
        Route::prefix('Billing')->group(function () {
            Route::get('pos-products/category', [\App\Modules\Api\V1\Billing\Controllers\BillingController::class, 'getPosCategories']);
            Route::get('pos-products', [\App\Modules\Api\V1\Billing\Controllers\BillingController::class, 'getPosProducts']);
            Route::get('drafts', [\App\Modules\Api\V1\Billing\Controllers\BillingController::class, 'drafts']);
            Route::get('new', [\App\Modules\Api\V1\Billing\Controllers\BillingController::class, 'createForm']);
            Route::get('headers', [\App\Modules\Api\V1\Billing\Controllers\BillingController::class, 'headerfields']);
            Route::get('{id}', [\App\Modules\Api\V1\Billing\Controllers\BillingController::class, 'show']);
            Route::get('/', [\App\Modules\Api\V1\Billing\Controllers\BillingController::class, 'index']);
            Route::post('new', [\App\Modules\Api\V1\Billing\Controllers\BillingController::class, 'store'])
                ->middleware('throttle:writes');
            Route::post('{id}', [\App\Modules\Api\V1\Billing\Controllers\BillingController::class, 'update'])
                ->middleware('throttle:writes');
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
            Route::get('{id}/ingredients', [\App\Modules\Api\V1\Related\Controllers\RelatedRecordsController::class, 'vendorIngredients']);
            Route::get('{id}/purchase-history', [\App\Modules\Api\V1\Related\Controllers\RelatedRecordsController::class, 'vendorPurchaseHistory']);
            Route::get('{id}/contact', [\App\Modules\Api\V1\Related\Controllers\RelatedRecordsController::class, 'vendorContact']);
            Route::get('{id}', [\App\Modules\Api\V1\Vendor\Controllers\VendorController::class, 'show']);
            Route::post('{id}', [\App\Modules\Api\V1\Vendor\Controllers\VendorController::class, 'update']);
            Route::delete('{id}', [\App\Modules\Api\V1\Vendor\Controllers\VendorController::class, 'destroy']);
        });

        // Ingredient endpoints
        Route::prefix('Ingredient')->group(function () {
            Route::get('', [\App\Modules\Api\V1\Ingredient\Controllers\IngredientController::class, 'index']);
            Route::get('low-stock', [\App\Modules\Api\V1\Ingredient\Controllers\IngredientController::class, 'lowStock']);
            Route::post('new', [\App\Modules\Api\V1\Ingredient\Controllers\IngredientController::class, 'store']);
            Route::get('{id}/usage-trend', [\App\Modules\Api\V1\Ingredient\Controllers\IngredientController::class, 'usageTrend']);
            Route::get('{id}/stock-history', [\App\Modules\Api\V1\Related\Controllers\RelatedRecordsController::class, 'ingredientStockHistory']);
            Route::get('{id}/vendors', [\App\Modules\Api\V1\Related\Controllers\RelatedRecordsController::class, 'ingredientVendors']);
            Route::get('{id}/usage-in-products', [\App\Modules\Api\V1\Related\Controllers\RelatedRecordsController::class, 'ingredientUsageInProducts']);
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
            Route::get('check-product-number', [\App\Modules\Api\V1\Product\Controllers\ProductController::class, 'checkProductNumber']);
            Route::post('new', [\App\Modules\Api\V1\Product\Controllers\ProductController::class, 'store']);
            Route::get('{id}/sales-trend', [\App\Modules\Api\V1\Product\Controllers\ProductController::class, 'salesTrend']);
            Route::get('{id}/production-history', [\App\Modules\Api\V1\Related\Controllers\RelatedRecordsController::class, 'productProductionHistory']);
            Route::get('{id}/sales-history', [\App\Modules\Api\V1\Related\Controllers\RelatedRecordsController::class, 'productSalesHistory']);
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
            Route::get('{id}/ingredients-used', [\App\Modules\Api\V1\Related\Controllers\RelatedRecordsController::class, 'productionIngredientsUsed']);
            Route::get('{id}/quality-summary', [\App\Modules\Api\V1\Related\Controllers\RelatedRecordsController::class, 'productionQualitySummary']);
            Route::get('{id}/dispatch', [\App\Modules\Api\V1\Related\Controllers\RelatedRecordsController::class, 'productionDispatch']);
            Route::get('{id}', [\App\Modules\Api\V1\ProductionBatch\Controllers\ProductionBatchController::class, 'show']);
            Route::post('{id}', [\App\Modules\Api\V1\ProductionBatch\Controllers\ProductionBatchController::class, 'update']);
            Route::delete('{id}', [\App\Modules\Api\V1\ProductionBatch\Controllers\ProductionBatchController::class, 'destroy']);
        });

        /*
         |--------------------------------------------------------------------------
         | Settings island (ALWAYS separate from bakery {Module} CRUD)
         | Shared pattern for User / Profile / Role:
         |   GET    settings/{Module}        → list
         |   GET    settings/{Module}/new    → create fields / empty form
         |   POST   settings/{Module}/new    → create
         |   GET    settings/{Module}/{id}   → show
         |   POST   settings/{Module}/{id}   → update
         |   DELETE settings/{Module}/{id}   → delete
         | Profile extras: modules, info?module=, repair
         |--------------------------------------------------------------------------
         */
        Route::prefix('settings')->middleware('admin')->group(function () {
            Route::prefix('User')->group(function () {
                Route::get('', [UserController::class, 'index']);
                Route::get('new', [UserController::class, 'createForm']);
                Route::post('new', [UserController::class, 'store']);
                Route::get('{id}', [UserController::class, 'show']);
                Route::post('{id}', [UserController::class, 'update']);
                Route::post('{id}/reset-password', [UserController::class, 'resetPassword']);
                Route::delete('{id}', [UserController::class, 'destroy']);
            });

            Route::prefix('Profile')->group(function () {
                Route::get('', [\App\Modules\Api\V1\Profile\Controllers\ProfileController::class, 'index']);
                // Extras BEFORE {id}
                Route::get('modules', [\App\Modules\Api\V1\Profile\Controllers\ProfileController::class, 'portalModules']);
                Route::get('info', [\App\Modules\Api\V1\Profile\Controllers\ProfileController::class, 'profileModuleFields']);
                Route::post('repair', [\App\Modules\Api\V1\Profile\Controllers\ProfileController::class, 'repair']);
                Route::get('new', [\App\Modules\Api\V1\Profile\Controllers\ProfileController::class, 'createForm']);
                Route::post('new', [\App\Modules\Api\V1\Profile\Controllers\ProfileController::class, 'saveAll']);
                Route::get('{id}', [\App\Modules\Api\V1\Profile\Controllers\ProfileController::class, 'details']);
                Route::post('{id}', [\App\Modules\Api\V1\Profile\Controllers\ProfileController::class, 'saveAll']);
                Route::delete('{id}', [\App\Modules\Api\V1\Profile\Controllers\ProfileController::class, 'delete']);
            });

            Route::prefix('Role')->group(function () {
                Route::get('', [\App\Modules\Api\V1\Role\Controllers\RoleController::class, 'index']);
                Route::get('new', [\App\Modules\Api\V1\Role\Controllers\RoleController::class, 'createForm']);
                Route::post('new', [\App\Modules\Api\V1\Role\Controllers\RoleController::class, 'store']);
                Route::get('{id}', [\App\Modules\Api\V1\Role\Controllers\RoleController::class, 'show']);
                Route::post('{id}', [\App\Modules\Api\V1\Role\Controllers\RoleController::class, 'update']);
                Route::delete('{id}', [\App\Modules\Api\V1\Role\Controllers\RoleController::class, 'delete']);
            });

            // Module fields manager (settings only)
            Route::prefix('fields')->group(function () {
                Route::get('view-fields', [\App\Modules\Api\V1\Settings\Controllers\CustomFieldController::class, 'createViewFields']);
                Route::get('', [\App\Modules\Api\V1\Settings\Controllers\CustomFieldController::class, 'list']);
                Route::post('new', [\App\Modules\Api\V1\Settings\Controllers\CustomFieldController::class, 'create']);
                Route::get('{module}/{id}', [\App\Modules\Api\V1\Settings\Controllers\CustomFieldController::class, 'show']);
                Route::post('update-label', [\App\Modules\Api\V1\Settings\Controllers\CustomFieldController::class, 'updateFieldLabel']);
                Route::delete('{id}', [\App\Modules\Api\V1\Settings\Controllers\CustomFieldController::class, 'delete']);
            });
        });
    });
});


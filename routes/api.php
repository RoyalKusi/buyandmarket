<?php

use App\Http\Controllers\Api\V1\Admin\BrandModerationController;
use App\Http\Controllers\Api\V1\Admin\ProductModerationController;
use App\Http\Controllers\Api\V1\Admin\RoleAssignmentController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\Seller\BrandController as SellerBrandController;
use App\Http\Controllers\Api\V1\Seller\ProductController as SellerProductController;
use Illuminate\Support\Facades\Route;

// TDD §7.1: base path /api/v1; breaking changes ship as /api/v2 with v1
// maintained on a published deprecation timeline.
Route::prefix('v1')->name('api.v1.')->group(function () {
    // Public catalogue reads (TDD §7.3).
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/admin/users/{user}/roles', [RoleAssignmentController::class, 'store'])
            ->name('admin.users.roles.store');

        Route::prefix('admin')->name('admin.')->group(function () {
            Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
            Route::post('/brands/{brand}/approve', [BrandModerationController::class, 'approve'])->name('brands.approve');
            Route::post('/brands/{brand}/reject', [BrandModerationController::class, 'reject'])->name('brands.reject');
            Route::post('/products/{product}/approve', [ProductModerationController::class, 'approve'])->name('products.approve');
            Route::post('/products/{product}/reject', [ProductModerationController::class, 'reject'])->name('products.reject');
        });

        // TDD §6.4 rule 4 / §8.5: seller.scope binds every Product/
        // ProductVariant query in this group to the acting seller's own
        // rows for the whole request (App\Http\Middleware\
        // ScopeQueriesToActingSeller).
        Route::prefix('seller')->name('seller.')->middleware('seller.scope')->group(function () {
            Route::post('/brands', [SellerBrandController::class, 'store'])->name('brands.store');
            Route::post('/products', [SellerProductController::class, 'store'])->name('products.store');
            Route::post('/products/{product}/submit', [SellerProductController::class, 'submitForReview'])->name('products.submit');
            Route::post('/products/{product}/archive', [SellerProductController::class, 'archive'])->name('products.archive');
            Route::patch('/products/{product}/price', [SellerProductController::class, 'updatePrice'])->name('products.price');
        });
    });
});

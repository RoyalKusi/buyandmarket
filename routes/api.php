<?php

use App\Http\Controllers\Api\V1\Admin\BrandModerationController;
use App\Http\Controllers\Api\V1\Admin\KycReviewController;
use App\Http\Controllers\Api\V1\Admin\ProductModerationController;
use App\Http\Controllers\Api\V1\Admin\RoleAssignmentController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\KycDocumentController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\Seller\BrandController as SellerBrandController;
use App\Http\Controllers\Api\V1\Seller\OnboardingController as SellerOnboardingController;
use App\Http\Controllers\Api\V1\Seller\ProductController as SellerProductController;
use Illuminate\Support\Facades\Route;

// TDD §7.1: base path /api/v1; breaking changes ship as /api/v2 with v1
// maintained on a published deprecation timeline.
Route::prefix('v1')->name('api.v1.')->group(function () {
    // Public catalogue reads (TDD §7.3).
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');

    // TDD §8.3: the signed download link itself carries its own
    // authorization (time-limited, single-document) — it is deliberately
    // outside auth:sanctum so a seller/admin can open it directly.
    Route::get('/kyc-documents/{document}/download', [KycDocumentController::class, 'download'])
        ->middleware('signed')
        ->name('kyc-documents.download');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/admin/users/{user}/roles', [RoleAssignmentController::class, 'store'])
            ->name('admin.users.roles.store');

        Route::get('/kyc-documents/{document}/download-link', [KycDocumentController::class, 'downloadLink'])
            ->name('kyc-documents.download-link');

        Route::prefix('admin')->name('admin.')->group(function () {
            Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
            Route::post('/brands/{brand}/approve', [BrandModerationController::class, 'approve'])->name('brands.approve');
            Route::post('/brands/{brand}/reject', [BrandModerationController::class, 'reject'])->name('brands.reject');
            Route::post('/products/{product}/approve', [ProductModerationController::class, 'approve'])->name('products.approve');
            Route::post('/products/{product}/reject', [ProductModerationController::class, 'reject'])->name('products.reject');
            Route::post('/sellers/{seller}/kyc-review/approve', [KycReviewController::class, 'approve'])->name('sellers.kyc-review.approve');
            Route::post('/sellers/{seller}/kyc-review/reject', [KycReviewController::class, 'reject'])->name('sellers.kyc-review.reject');
        });

        Route::prefix('seller')->name('seller.')->group(function () {
            // TDD §3.1 module 4: the onboarding stepper. Not behind
            // seller.scope — that middleware requires an existing seller
            // record (register() is how one comes to exist at all), and
            // every other step here authorizes via SellerPolicy::manage()
            // against its own route-bound {seller}.
            Route::post('/register', [SellerOnboardingController::class, 'register'])->name('register');
            Route::post('/{seller}/kyc-documents', [SellerOnboardingController::class, 'submitKycDocuments'])->name('kyc-documents.store');
            Route::post('/{seller}/payout-details', [SellerOnboardingController::class, 'submitPayoutDetails'])->name('payout-details.store');
            Route::post('/{seller}/store', [SellerOnboardingController::class, 'createStore'])->name('store.create');
            Route::post('/{seller}/submit-for-review', [SellerOnboardingController::class, 'submitForReview'])->name('submit-for-review');

            // TDD §6.4 rule 4 / §8.5: seller.scope binds every Product/
            // ProductVariant query in this group to the acting seller's
            // own rows for the whole request (App\Http\Middleware\
            // ScopeQueriesToActingSeller).
            Route::middleware('seller.scope')->group(function () {
                Route::post('/brands', [SellerBrandController::class, 'store'])->name('brands.store');
                Route::post('/products', [SellerProductController::class, 'store'])->name('products.store');
                Route::post('/products/{product}/submit', [SellerProductController::class, 'submitForReview'])->name('products.submit');
                Route::post('/products/{product}/archive', [SellerProductController::class, 'archive'])->name('products.archive');
                Route::patch('/products/{product}/price', [SellerProductController::class, 'updatePrice'])->name('products.price');
            });
        });
    });
});

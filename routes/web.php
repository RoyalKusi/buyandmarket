<?php

use App\Http\Controllers\Dashboard\AdminController;
use App\Http\Controllers\Dashboard\BuyerController;
use App\Http\Controllers\Dashboard\SellerController;
use App\Http\Controllers\Dashboard\ShipperController;
use App\Http\Controllers\Storefront\CategoryController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Storefront\SearchController;
use App\Http\Controllers\Storefront\StoreController;
use Illuminate\Support\Facades\Route;

// Design System §6.1-6.5: the buyer-facing storefront. Named
// storefront.* throughout so a future dashboard/admin web surface never
// collides with these route names.
Route::name('storefront.')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/search', [SearchController::class, 'index'])->name('search');
    Route::get('/categories/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');
    Route::get('/products/{product:slug}', [ProductController::class, 'show'])->name('products.show');
    Route::get('/stores/{store:slug}', [StoreController::class, 'show'])->name('stores.show');
});

// TDD §3.6 modules 27-30 / Design System §6.11: one shared dashboard
// shell across roles (resources/views/components/layouts/dashboard.blade.php)
// — a buyer who is also a seller/shipper/admin sees one product, not
// several bolted together. Login/registration views are Laravel Fortify's
// own routes (App\Providers\FortifyServiceProvider), wired to these
// dashboards via config('fortify.home').
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [BuyerController::class, 'orders'])->name('dashboard');
    Route::get('/dashboard/orders/{order}', [BuyerController::class, 'showOrder'])->name('dashboard.orders.show');
    Route::get('/dashboard/addresses', [BuyerController::class, 'addresses'])->name('dashboard.addresses');
    Route::post('/dashboard/addresses', [BuyerController::class, 'storeAddress'])->name('dashboard.addresses.store');
    Route::delete('/dashboard/addresses/{address}', [BuyerController::class, 'destroyAddress'])->name('dashboard.addresses.destroy');

    // TDD §6.4 rule 4: seller.scope keeps every query here confined to
    // the acting seller's own rows, same guarantee as the API.
    Route::prefix('seller/dashboard')->name('seller.dashboard.')->middleware('seller.scope')->group(function () {
        Route::get('/', [SellerController::class, 'overview'])->name('index');
        Route::get('/products', [SellerController::class, 'products'])->name('products');
        Route::post('/products/{product}/submit', [SellerController::class, 'submitProductForReview'])->name('products.submit');
        Route::post('/products/{product}/archive', [SellerController::class, 'archiveProduct'])->name('products.archive');
        Route::get('/orders', [SellerController::class, 'orders'])->name('orders');
        Route::post('/order-groups/{orderGroup}/shipment', [SellerController::class, 'assignShipment'])->name('orders.shipment');
        Route::get('/delivery', [SellerController::class, 'delivery'])->name('delivery');
        Route::post('/delivery', [SellerController::class, 'storeRateCard'])->name('delivery.store');
    });

    Route::prefix('shipper/dashboard')->name('shipper.dashboard.')->group(function () {
        Route::get('/', [ShipperController::class, 'index'])->name('index');
        Route::post('/shipments/{shipment}/claim', [ShipperController::class, 'claim'])->name('claim');
        Route::post('/shipments/{shipment}/events', [ShipperController::class, 'storeEvent'])->name('events.store');
    });

    Route::prefix('admin/dashboard')->name('admin.dashboard.')->middleware('role:admin')->group(function () {
        Route::get('/sellers', [AdminController::class, 'sellers'])->name('sellers');
        Route::post('/sellers/{seller}/approve', [AdminController::class, 'approveSeller'])->name('sellers.approve');
        Route::post('/sellers/{seller}/reject', [AdminController::class, 'rejectSeller'])->name('sellers.reject');
        Route::get('/products', [AdminController::class, 'products'])->name('products');
        Route::post('/products/{product}/approve', [AdminController::class, 'approveProduct'])->name('products.approve');
        Route::post('/products/{product}/reject', [AdminController::class, 'rejectProduct'])->name('products.reject');
        Route::get('/audit-log', [AdminController::class, 'auditLog'])->name('audit-log');
    });
});

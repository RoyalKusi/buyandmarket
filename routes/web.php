<?php

use App\Http\Controllers\Auth\SocialLoginController;
use App\Http\Controllers\Dashboard\AdminController;
use App\Http\Controllers\Dashboard\BuyerController;
use App\Http\Controllers\Dashboard\SellerController;
use App\Http\Controllers\Dashboard\SellerOnboardingController;
use App\Http\Controllers\Dashboard\ShipperController;
use App\Http\Controllers\Dashboard\ShipperOnboardingController;
use App\Http\Controllers\Storefront\CategoryController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Storefront\ReviewController;
use App\Http\Controllers\Storefront\SearchController;
use App\Http\Controllers\Storefront\StoreController;
use Illuminate\Support\Facades\Route;

// "Continue with Google/Facebook" on the login/register views
// (Fortify::loginView/registerView, FortifyServiceProvider) — the
// server-redirect OAuth flow, sharing App\Services\
// SocialAccountResolver's find-or-create logic with the mobile API's
// token-based equivalent (Api\V1\SocialAuthController). Guest-only,
// like Fortify's own login/register routes.
Route::middleware('guest')->group(function () {
    Route::get('/auth/{provider}/redirect', [SocialLoginController::class, 'redirect'])
        ->whereIn('provider', ['google', 'facebook'])
        ->name('social.redirect');
    Route::get('/auth/{provider}/callback', [SocialLoginController::class, 'callback'])
        ->whereIn('provider', ['google', 'facebook'])
        ->name('social.callback');
});

// Design System §6.1-6.5: the buyer-facing storefront. Named
// storefront.* throughout so a future dashboard/admin web surface never
// collides with these route names.
Route::name('storefront.')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/search', [SearchController::class, 'index'])->name('search');
    Route::get('/categories/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');
    Route::get('/products/{product:slug}', [ProductController::class, 'show'])->name('products.show');
    Route::post('/products/{product:slug}/reviews', [ReviewController::class, 'store'])->middleware('auth')->name('products.reviews.store');
    Route::get('/stores/{store:slug}', [StoreController::class, 'show'])->name('stores.show');

    // Design System §6.6: dedicated checkout page, not a modal. Guest
    // checkout is fully supported (TDD §5.9) — deliberately outside
    // auth middleware, same as the API's guest-session cart/checkout
    // routes (Run 1.5).
    Route::prefix('checkout')->name('checkout.')->group(function () {
        Route::get('/start', [CheckoutController::class, 'start'])->name('start');
        Route::get('/return', [CheckoutController::class, 'return'])->name('return');
        Route::get('/{checkoutSession}/address', [CheckoutController::class, 'showAddress'])->name('address');
        Route::post('/{checkoutSession}/address', [CheckoutController::class, 'storeAddress'])->name('address.store');
        Route::get('/{checkoutSession}/delivery', [CheckoutController::class, 'showDelivery'])->name('delivery');
        Route::post('/{checkoutSession}/delivery', [CheckoutController::class, 'storeDelivery'])->name('delivery.store');
        Route::get('/{checkoutSession}/payment', [CheckoutController::class, 'showPayment'])->name('payment');
        Route::post('/{checkoutSession}/payment', [CheckoutController::class, 'storePayment'])->name('payment.store');
        Route::get('/{checkoutSession}/failed', [CheckoutController::class, 'failed'])->name('failed');
        Route::get('/{checkoutSession}/confirmation', [CheckoutController::class, 'confirmation'])->name('confirmation');
        Route::post('/orders/{order}/upsell', [CheckoutController::class, 'upsell'])->name('upsell');
    });
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
    Route::get('/dashboard/wishlist', [BuyerController::class, 'wishlist'])->name('dashboard.wishlist');
    Route::delete('/dashboard/wishlist/{product}', [BuyerController::class, 'removeFromWishlist'])->name('dashboard.wishlist.destroy');
    Route::view('/dashboard/assistant', 'dashboard.assistant')->name('dashboard.assistant');

    // TDD §8.2: "MFA required for seller ... and all admin/sub-admin
    // roles" — enabling/disabling it is itself a sensitive action, so
    // this page sits behind Fortify's own 'password.confirm' gate (same
    // protection level config/fortify.php already configures for its own
    // two-factor routes).
    Route::view('/dashboard/security', 'dashboard.security')->middleware('password.confirm')->name('dashboard.security');

    // TDD §3.1 module 4 / §3.4 module 23: self-service "become a X"
    // web flows — deliberately outside seller.scope/2fa, which both
    // require the role to already exist. Registering as a seller is the
    // very thing that creates it.
    //
    // Production Readiness Report condition #3: gated behind `verified`
    // (unlike checkout, which only gates the buyer flow) because these
    // onboarding flows collect KYC documents and payout bank details —
    // identity/financial data that should only ever be attached to a
    // confirmed-reachable email address, since that's also where KYC
    // approval/rejection and future payout notices are sent.
    Route::middleware('verified')->group(function () {
        Route::get('/dashboard/become-seller', [SellerOnboardingController::class, 'show'])->name('dashboard.become-seller');
        Route::post('/dashboard/become-seller', [SellerOnboardingController::class, 'register'])->name('dashboard.become-seller.register');
        Route::post('/dashboard/become-seller/kyc-documents', [SellerOnboardingController::class, 'submitKycDocuments'])->name('dashboard.become-seller.kyc-documents');
        Route::post('/dashboard/become-seller/payout-details', [SellerOnboardingController::class, 'submitPayoutDetails'])->name('dashboard.become-seller.payout-details');
        Route::post('/dashboard/become-seller/store', [SellerOnboardingController::class, 'createStore'])->name('dashboard.become-seller.store');
        Route::post('/dashboard/become-seller/submit-for-review', [SellerOnboardingController::class, 'submitForReview'])->name('dashboard.become-seller.submit-for-review');

        Route::get('/dashboard/become-shipper', [ShipperOnboardingController::class, 'show'])->name('dashboard.become-shipper');
        Route::post('/dashboard/become-shipper', [ShipperOnboardingController::class, 'register'])->name('dashboard.become-shipper.register');
    });

    // TDD §6.4 rule 4: seller.scope keeps every query here confined to
    // the acting seller's own rows, same guarantee as the API.
    Route::prefix('seller/dashboard')->name('seller.dashboard.')->middleware(['seller.scope', '2fa'])->group(function () {
        Route::get('/', [SellerController::class, 'overview'])->name('index');
        Route::get('/products', [SellerController::class, 'products'])->name('products');
        Route::get('/products/create', [SellerController::class, 'createProduct'])->name('products.create');
        Route::post('/products/suggest-categorization', [SellerController::class, 'suggestCategorization'])->name('products.suggest-categorization');
        Route::post('/products/suggest-brand', [SellerController::class, 'suggestBrand'])->name('products.suggest-brand');
        Route::post('/products', [SellerController::class, 'storeProduct'])->name('products.store');
        Route::post('/products/{product}/submit', [SellerController::class, 'submitProductForReview'])->name('products.submit');
        Route::post('/products/{product}/archive', [SellerController::class, 'archiveProduct'])->name('products.archive');
        Route::get('/products/{product}/images', [SellerController::class, 'manageImages'])->name('products.images');
        Route::post('/products/{product}/images', [SellerController::class, 'storeImage'])->name('products.images.store');
        Route::delete('/products/{product}/images/{image}', [SellerController::class, 'destroyImage'])->name('products.images.destroy');
        Route::post('/products/{product}/images/{image}/primary', [SellerController::class, 'makeImagePrimary'])->name('products.images.primary');
        Route::post('/products/{product}/images/{image}/alt-text', [SellerController::class, 'generateImageAltText'])->name('products.images.alt-text');
        Route::post('/products/{product}/ai-description', [SellerController::class, 'suggestDescription'])->name('products.ai-description.suggest');
        Route::post('/products/{product}/ai-description/accept', [SellerController::class, 'acceptDescription'])->name('products.ai-description.accept');
        Route::post('/products/{product}/ai-description/discard', [SellerController::class, 'discardDescription'])->name('products.ai-description.discard');
        Route::get('/orders', [SellerController::class, 'orders'])->name('orders');
        Route::post('/order-groups/{orderGroup}/shipment', [SellerController::class, 'assignShipment'])->name('orders.shipment');
        Route::get('/delivery', [SellerController::class, 'delivery'])->name('delivery');
        Route::post('/delivery', [SellerController::class, 'storeRateCard'])->name('delivery.store');
        Route::get('/sponsored-campaigns', [SellerController::class, 'sponsoredCampaigns'])->name('sponsored-campaigns');
        Route::post('/sponsored-campaigns', [SellerController::class, 'storeSponsoredCampaign'])->name('sponsored-campaigns.store');
    });

    Route::prefix('shipper/dashboard')->name('shipper.dashboard.')->group(function () {
        Route::get('/', [ShipperController::class, 'index'])->name('index');
        Route::post('/shipments/{shipment}/claim', [ShipperController::class, 'claim'])->name('claim');
        Route::post('/shipments/{shipment}/events', [ShipperController::class, 'storeEvent'])->name('events.store');
    });

    Route::prefix('admin/dashboard')->name('admin.dashboard.')->middleware(['role:admin', '2fa'])->group(function () {
        Route::get('/sellers', [AdminController::class, 'sellers'])->name('sellers');
        Route::post('/sellers/{seller}/approve', [AdminController::class, 'approveSeller'])->name('sellers.approve');
        Route::post('/sellers/{seller}/reject', [AdminController::class, 'rejectSeller'])->name('sellers.reject');
        Route::get('/products', [AdminController::class, 'products'])->name('products');
        Route::post('/products/{product}/approve', [AdminController::class, 'approveProduct'])->name('products.approve');
        Route::post('/products/{product}/reject', [AdminController::class, 'rejectProduct'])->name('products.reject');
        Route::get('/reviews', [AdminController::class, 'reviews'])->name('reviews');
        Route::post('/reviews/{review}/remove', [AdminController::class, 'removeReview'])->name('reviews.remove');
        Route::get('/sponsored-campaigns', [AdminController::class, 'sponsoredCampaigns'])->name('sponsored-campaigns');
        Route::post('/sponsored-campaigns/{campaign}/approve', [AdminController::class, 'approveSponsoredCampaign'])->name('sponsored-campaigns.approve');
        Route::post('/sponsored-campaigns/{campaign}/reject', [AdminController::class, 'rejectSponsoredCampaign'])->name('sponsored-campaigns.reject');
        Route::get('/audit-log', [AdminController::class, 'auditLog'])->name('audit-log');
        Route::get('/ai-monitoring', [AdminController::class, 'aiMonitoring'])->name('ai-monitoring');
    });
});

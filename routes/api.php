<?php

use App\Http\Controllers\Api\V1\Admin\BrandModerationController;
use App\Http\Controllers\Api\V1\Admin\KycReviewController;
use App\Http\Controllers\Api\V1\Admin\ProductModerationController;
use App\Http\Controllers\Api\V1\Admin\RoleAssignmentController;
use App\Http\Controllers\Api\V1\Ai\ConversationController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\KycDocumentController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductSearchController;
use App\Http\Controllers\Api\V1\Seller\BrandController as SellerBrandController;
use App\Http\Controllers\Api\V1\Seller\DeliveryRateCardController;
use App\Http\Controllers\Api\V1\Seller\OnboardingController as SellerOnboardingController;
use App\Http\Controllers\Api\V1\Seller\ProductController as SellerProductController;
use App\Http\Controllers\Api\V1\Seller\ProductImageController as SellerProductImageController;
use App\Http\Controllers\Api\V1\Seller\ShipmentController as SellerShipmentController;
use App\Http\Controllers\Api\V1\Shipper\OnboardingController as ShipperOnboardingController;
use App\Http\Controllers\Api\V1\Shipper\ShipmentController as ShipperShipmentController;
use App\Http\Controllers\Api\V1\Webhooks\PaymentWebhookController;
use App\Http\Controllers\Api\V1\WishlistController;
use Illuminate\Support\Facades\Route;

// TDD §7.1: base path /api/v1; breaking changes ship as /api/v2 with v1
// maintained on a published deprecation timeline.
Route::prefix('v1')->name('api.v1.')->group(function () {
    // Mobile/third-party token auth — see App\Http\Controllers\Api\V1\
    // AuthController's own class doc for why this wraps Fortify's own
    // CreateNewUser/ResetUserPassword actions rather than duplicating them.
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])->name('register');
        Route::post('/login', [AuthController::class, 'login'])->name('login');
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('/user', [AuthController::class, 'user'])->name('user');
        });
    });

    // Public catalogue reads (TDD §7.3).
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/products', [ProductSearchController::class, 'index'])->name('products.index');
    Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');

    // TDD §8.3: the signed download link itself carries its own
    // authorization (time-limited, single-document) — it is deliberately
    // outside auth:sanctum so a seller/admin can open it directly.
    Route::get('/kyc-documents/{document}/download', [KycDocumentController::class, 'download'])
        ->middleware('signed')
        ->name('kyc-documents.download');

    // TDD §7.4/§8.3: HMAC/signature-verified inside the controller before
    // any payload is trusted. Sanctum's stateful-API middleware (which
    // would otherwise apply CSRF) only activates for requests whose
    // origin matches a configured stateful domain — a webhook POST from
    // Pesepay/Paynow's servers never does, so it reaches this route
    // without a CSRF check, as a webhook must.
    Route::post('/webhooks/{provider}', [PaymentWebhookController::class, 'handle'])
        ->whereIn('provider', ['pesepay', 'paynow'])
        ->name('webhooks.handle');

    // TDD §5.9: guest checkout is fully supported — cart/checkout routes
    // are deliberately outside auth:sanctum. $request->user() still
    // resolves for a session-authenticated buyer; everyone else is
    // identified by the PHP session ID (App\Services\CartService).
    //
    // Sanctum's statefulApi() middleware only starts a session when the
    // request's Origin/Referer matches a configured stateful domain — it
    // never does for a same-origin fetch() call that omits those headers
    // (as most first-party AJAX does), which would leave $request->
    // session() unbound. The 'guest-session' group starts a real session
    // unconditionally instead. It deliberately excludes CSRF: these are
    // JSON endpoints, not a form post, and a guest cart's worst-case
    // cross-site risk (an attacker adding an item to someone else's
    // anonymous cart) is far below what CSRF exists to prevent —
    // upgrading to a CSRF-protected flow, or a header-based scheme
    // instead of a cookie session, is a Run 1.7 dashboard-security
    // follow-up if a stricter posture turns out to be warranted.
    Route::middleware('guest-session')->group(function () {
        Route::prefix('carts')->name('carts.')->group(function () {
            Route::get('/', [CartController::class, 'show'])->name('show');
            Route::post('/items', [CartController::class, 'storeItem'])->name('items.store');
            Route::patch('/items/{item}', [CartController::class, 'updateItem'])->name('items.update');
            Route::delete('/items/{item}', [CartController::class, 'destroyItem'])->name('items.destroy');
        });

        Route::prefix('checkout')->name('checkout.')->group(function () {
            Route::post('/session', [CheckoutController::class, 'store'])->name('session.store');
            Route::get('/session/{checkoutSession}', [CheckoutController::class, 'show'])->name('session.show');
            Route::patch('/session/{checkoutSession}/address', [CheckoutController::class, 'setAddress'])->name('session.address');
            Route::patch('/session/{checkoutSession}/delivery', [CheckoutController::class, 'setDelivery'])->name('session.delivery');
            Route::post('/session/{checkoutSession}/payment', [CheckoutController::class, 'initiatePayment'])->name('session.payment');
        });

        // TDD §5.9/§7.3: the assistant is available to guests too (same
        // session-identified pattern as the guest cart above), not
        // gated behind auth:sanctum.
        //
        // Audit finding (P2, cost-abuse): every message/confirm call is
        // a real paid LlmProvider call (messages.store also triggers
        // EmbeddingProvider retrieval) with no limit anywhere, reachable
        // by a guest. The actual fix lives inside App\Services\Ai\
        // AssistantService::sendMessage() itself, not as route
        // middleware here — that's the one choke point both this API
        // route and the dashboard's Livewire AiAssistant component
        // funnel through, so a route-only throttle would have left the
        // Livewire path, which most buyers actually use, unprotected.
        Route::prefix('ai/conversations')->name('ai.conversations.')->group(function () {
            Route::post('/', [ConversationController::class, 'store'])->name('store');
            Route::post('/{conversation}/messages', [ConversationController::class, 'storeMessage'])->name('messages.store');
            Route::post('/{conversation}/messages/{message}/confirm', [ConversationController::class, 'confirm'])->name('messages.confirm');
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');

        Route::prefix('wishlist')->name('wishlist.')->group(function () {
            Route::get('/', [WishlistController::class, 'index'])->name('index');
            Route::post('/{product}', [WishlistController::class, 'toggle'])->name('toggle');
        });

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

                // TDD §3.2 module 7 / §5.8: the image pipeline (App\
                // Services\ProductImageService) — deferred whole since
                // Run 1.2, added in Run 1.15.
                Route::post('/products/{product}/images', [SellerProductImageController::class, 'store'])->name('products.images.store');
                Route::delete('/products/{product}/images/{image}', [SellerProductImageController::class, 'destroy'])->name('products.images.destroy');
                Route::post('/products/{product}/images/{image}/primary', [SellerProductImageController::class, 'makePrimary'])->name('products.images.primary');
                Route::post('/products/{product}/images/reorder', [SellerProductImageController::class, 'reorder'])->name('products.images.reorder');

                // TDD §3.4 modules 22/26: rate cards double as "which
                // zones/methods this seller serves" (no separate opt-in
                // list), and shipment assignment on the seller's own
                // order_groups (OrderGroup::scopeToSeller keeps this
                // 404, not 403, on another seller's order_group, per
                // §8.5).
                Route::get('/delivery-rate-cards', [DeliveryRateCardController::class, 'index'])->name('delivery-rate-cards.index');
                Route::post('/delivery-rate-cards', [DeliveryRateCardController::class, 'store'])->name('delivery-rate-cards.store');
                Route::post('/order-groups/{orderGroup}/shipment', [SellerShipmentController::class, 'store'])->name('order-groups.shipment.store');
            });
        });

        Route::prefix('shipper')->name('shipper.')->group(function () {
            // TDD §3.4 module 23: registration is self-service, matching
            // the seller onboarding pattern; every other action
            // authorizes per-shipment via ShipperPolicy/
            // OrderGroupShipmentPolicy.
            Route::post('/register', [ShipperOnboardingController::class, 'register'])->name('register');
            Route::get('/shipments', [ShipperShipmentController::class, 'index'])->name('shipments.index');
            Route::post('/shipments/{shipment}/claim', [ShipperShipmentController::class, 'claim'])->name('shipments.claim');
            Route::post('/shipments/{shipment}/events', [ShipperShipmentController::class, 'storeEvent'])->name('shipments.events.store');
        });
    });
});

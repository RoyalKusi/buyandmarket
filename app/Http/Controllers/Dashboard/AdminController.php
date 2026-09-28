<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Seller;
use App\Services\KycReviewService;
use App\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * TDD §3.6 module 27-30, §4.4 / Design System §6.11: purpose-built
 * triage worklists, not a generic post-type list table — behind the
 * 'role:admin' middleware (routes/web.php); each privileged action below
 * still goes through its own Policy via the Gate::before admin bypass.
 */
class AdminController extends Controller
{
    public function sellers(): View
    {
        return view('dashboard.admin.sellers', [
            'sellers' => Seller::where('status', 'under_review')->with('kycDocuments', 'user')->get(),
        ]);
    }

    public function approveSeller(Request $request, Seller $seller, KycReviewService $kycReviewService): RedirectResponse
    {
        $this->authorize('review', $seller);

        $kycReviewService->approve($seller, $request->user());

        return back()->with('status', 'Seller approved and now active.');
    }

    public function rejectSeller(Request $request, Seller $seller, KycReviewService $kycReviewService): RedirectResponse
    {
        $this->authorize('review', $seller);

        $data = $request->validate([
            'reason_code' => ['required', 'string', 'max:100'],
            'note' => ['nullable', 'string'],
        ]);

        $kycReviewService->reject($seller, $request->user(), $data['reason_code'], $data['note'] ?? null);

        return back()->with('status', 'Seller KYC rejected.');
    }

    public function products(): View
    {
        return view('dashboard.admin.products', [
            'products' => Product::where('status', 'pending_review')->with('store.seller')->get(),
        ]);
    }

    public function approveProduct(Request $request, Product $product, ProductService $productService): RedirectResponse
    {
        $this->authorize('approve', $product);

        $productService->approve($product, $request->user());

        return back()->with('status', 'Product approved and published.');
    }

    public function rejectProduct(Request $request, Product $product, ProductService $productService): RedirectResponse
    {
        $this->authorize('reject', $product);

        $data = $request->validate([
            'reason_code' => ['required', 'string', 'max:100'],
            'note' => ['nullable', 'string'],
        ]);

        $productService->reject($product, $request->user(), $data['reason_code'], $data['note'] ?? null);

        return back()->with('status', 'Product rejected.');
    }

    public function auditLog(): View
    {
        return view('dashboard.admin.audit-log', [
            'entries' => AuditLog::with('actor')->latest('created_at')->paginate(25),
        ]);
    }
}

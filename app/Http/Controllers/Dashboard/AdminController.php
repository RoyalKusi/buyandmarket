<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\ConversationMessage;
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

    /**
     * TDD §8.9 "immutable, filterable table" — filtering was flagged
     * deferred since Run 1.7. Every filter is optional and additive;
     * an admin with no filters sees exactly the previous unfiltered feed.
     */
    public function auditLog(Request $request): View
    {
        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:100'],
            'subject_type' => ['nullable', 'string', 'max:100'],
            'actor' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $entries = AuditLog::query()
            ->with('actor')
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->when($filters['subject_type'] ?? null, fn ($query, $subjectType) => $query->where('subject_type', $subjectType))
            ->when($filters['actor'] ?? null, fn ($query, $actor) => $query->whereHas(
                'actor',
                fn ($userQuery) => $userQuery->where('name', 'like', "%{$actor}%")->orWhere('email', 'like', "%{$actor}%")
            ))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('dashboard.admin.audit-log', [
            'entries' => $entries,
            'filters' => $filters,
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'subjectTypes' => AuditLog::query()->distinct()->orderBy('subject_type')->pluck('subject_type'),
        ]);
    }

    /**
     * TDD §5.7/§6.11 "AI system monitoring panel": query volume,
     * tool-call success/denial rate, escalation-to-human rate,
     * per-tool latency. Only the signals this run's own schema can
     * compute honestly are shown — no escalate_to_support tool or
     * per-call latency capture exists yet (both flagged in
     * CHANGELOG.md), so those rows are omitted rather than faked.
     */
    public function aiMonitoring(): View
    {
        $toolCalls = AuditLog::where('action', 'like', 'ai.tool.%')->get();

        return view('dashboard.admin.ai-monitoring', [
            'conversationCount' => Conversation::count(),
            'messageCounts' => ConversationMessage::query()->selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role'),
            'toolCallCounts' => $toolCalls->countBy(fn (AuditLog $log) => str($log->action)->after('ai.tool.')),
            'pendingConfirmations' => ConversationMessage::where('requires_confirmation', true)->where('confirmed', false)->count(),
            'confirmedActions' => ConversationMessage::where('requires_confirmation', true)->where('confirmed', true)->count(),
            'suggestionsGenerated' => AuditLog::where('action', 'ai.suggestion.generated')->count(),
            'suggestionsAccepted' => AuditLog::where('action', 'ai.suggestion.accepted')->count(),
            'suggestionsDiscarded' => AuditLog::where('action', 'ai.suggestion.discarded')->count(),
        ]);
    }
}

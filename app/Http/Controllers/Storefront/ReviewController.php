<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * TDD module 33, flagged deferred since Run 1.4's CHANGELOG ("rating row
 * and reviews tab content"). Verified-purchase-only — App\Services\
 * ReviewService checks a real confirmed/completed order before a review
 * can be written at all.
 */
class ReviewController extends Controller
{
    public function store(Request $request, Product $product, ReviewService $reviewService): RedirectResponse
    {
        $this->authorize('create', [Review::class, $product]);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $reviewService->create($product, $request->user(), $data['rating'], $data['title'] ?? null, $data['body']);

        return back()->with('status', 'Thanks — your review is live.');
    }
}

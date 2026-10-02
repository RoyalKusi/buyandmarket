<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use App\Models\Product;
use App\Models\Seller;
use Illuminate\Support\Collection;

/**
 * TDD module 41's own stated purpose for the event stream: seller sales
 * summaries and performance insights (flagged deferred since Run 1.7's
 * CHANGELOG — "both need the analytics event stream"). Aggregates
 * App\Models\AnalyticsEvent rows App\Services\AnalyticsService records
 * on the storefront; never writes, only reads.
 */
class SellerAnalyticsService
{
    private const SUMMARY_DAYS = 30;

    /**
     * @return array{labels: array<int, string>, revenue: array<int, float>, totalRevenue: float}
     */
    public function salesSummary(Seller $seller): array
    {
        $productIds = Product::query()->whereHas('store', fn ($q) => $q->where('seller_id', $seller->id))->pluck('id');

        $events = AnalyticsEvent::where('event_type', 'order_placed')
            ->where('subject_type', Product::class)
            ->whereIn('subject_id', $productIds)
            ->where('created_at', '>=', now()->subDays(self::SUMMARY_DAYS))
            ->get();

        $byDay = $events->groupBy(fn (AnalyticsEvent $event) => $event->created_at->toDateString());

        $labels = [];
        $revenue = [];

        for ($i = self::SUMMARY_DAYS - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $labels[] = $date;
            $revenue[] = (float) $byDay->get($date, collect())->sum(fn (AnalyticsEvent $event) => (float) ($event->metadata['revenue'] ?? 0));
        }

        return [
            'labels' => $labels,
            'revenue' => $revenue,
            'totalRevenue' => round(array_sum($revenue), 2),
        ];
    }

    /**
     * Views/conversion per product (TDD §5.6's "performance insights").
     *
     * @return Collection<int, array{product: Product, views: int, purchases: int, conversionRate: float}>
     */
    public function productPerformance(Seller $seller): Collection
    {
        $products = Product::query()->whereHas('store', fn ($q) => $q->where('seller_id', $seller->id))->get();
        $productIds = $products->pluck('id');

        $viewCounts = AnalyticsEvent::where('event_type', 'product_view')
            ->where('subject_type', Product::class)
            ->whereIn('subject_id', $productIds)
            ->selectRaw('subject_id, count(*) as total')
            ->groupBy('subject_id')
            ->pluck('total', 'subject_id');

        $purchaseCounts = AnalyticsEvent::where('event_type', 'order_placed')
            ->where('subject_type', Product::class)
            ->whereIn('subject_id', $productIds)
            ->selectRaw('subject_id, count(*) as total')
            ->groupBy('subject_id')
            ->pluck('total', 'subject_id');

        return $products->map(function (Product $product) use ($viewCounts, $purchaseCounts) {
            $views = (int) ($viewCounts[$product->id] ?? 0);
            $purchases = (int) ($purchaseCounts[$product->id] ?? 0);

            return [
                'product' => $product,
                'views' => $views,
                'purchases' => $purchases,
                'conversionRate' => $views > 0 ? round(($purchases / $views) * 100, 1) : 0.0,
            ];
        })->sortByDesc('views')->values();
    }
}

<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * TDD module 41: the analytics event stream — a behavioural signal
 * (what buyers did), recorded fire-and-forget on the request thread
 * (no queue worker guaranteed running on this launch topology, same
 * reasoning as the image pipeline and ai:reindex). Never read back
 * synchronously by the page that wrote it — only by
 * App\Services\SellerAnalyticsService's aggregate queries.
 */
class AnalyticsService
{
    public function record(string $eventType, Model $subject, ?User $user = null, array $metadata = []): void
    {
        AnalyticsEvent::create([
            'event_type' => $eventType,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'user_id' => $user?->id,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}

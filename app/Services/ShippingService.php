<?php

namespace App\Services;

use App\Models\DeliveryRateCard;
use App\Models\DeliveryZone;
use App\Models\OrderGroup;
use App\Models\OrderGroupShipment;
use App\Models\Shipper;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * TDD §3.4 modules 22-24: shipment assignment, status transitions and the
 * append-only tracking log that the buyer-facing timeline projects from.
 */
class ShippingService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function rateCardFor(OrderGroup $orderGroup, DeliveryZone $zone, string $method): DeliveryRateCard
    {
        $rateCard = DeliveryRateCard::query()
            ->where('seller_id', $orderGroup->seller_id)
            ->where('zone_id', $zone->id)
            ->where('method', $method)
            ->where('enabled', true)
            ->first();

        if ($rateCard === null) {
            throw ValidationException::withMessages([
                'method' => 'This seller does not deliver to that zone by that method.',
            ]);
        }

        return $rateCard;
    }

    /**
     * TDD §3.4 module 22: seller-selected (own courier, $shipper given) or
     * platform-dispatched (pooled marketplace, $shipper null — any active
     * shipper may later claim() it).
     */
    public function assign(OrderGroup $orderGroup, DeliveryZone $zone, string $method, ?Shipper $shipper = null): OrderGroupShipment
    {
        if ($shipper !== null && ! $shipper->isActive()) {
            throw ValidationException::withMessages(['shipper' => 'This shipper is not active.']);
        }

        return DB::transaction(function () use ($orderGroup, $zone, $method, $shipper) {
            $shipment = OrderGroupShipment::create([
                'order_group_id' => $orderGroup->id,
                'shipper_id' => $shipper?->id,
                'zone_id' => $zone->id,
                'method' => $method,
                'status' => 'assigned',
            ]);

            $this->recordEvent($shipment, 'assigned', actor: null);

            if ($orderGroup->status === 'confirmed') {
                $orderGroup->update(['status' => 'processing']);
            }

            return $shipment;
        });
    }

    /**
     * TDD §3.4 module 23: a pooled, platform-dispatched shipment is open
     * for any active shipper to claim.
     */
    public function claim(OrderGroupShipment $shipment, Shipper $shipper): OrderGroupShipment
    {
        if (! $shipment->isUnclaimed()) {
            throw ValidationException::withMessages(['shipment' => 'This shipment has already been claimed.']);
        }

        if (! $shipper->isActive()) {
            throw ValidationException::withMessages(['shipper' => 'This shipper is not active.']);
        }

        $shipment->update(['shipper_id' => $shipper->id]);

        return $shipment;
    }

    /**
     * @param  array{lat?: float, lng?: float, note?: string, photo?: UploadedFile, signature?: UploadedFile}  $data
     */
    public function recordEvent(
        OrderGroupShipment $shipment,
        string $eventType,
        ?User $actor = null,
        array $data = [],
    ): OrderGroupShipment {
        return DB::transaction(function () use ($shipment, $eventType, $actor, $data) {
            $shipment->events()->create([
                'event_type' => $eventType,
                'actor_id' => $actor?->id,
                'lat' => $data['lat'] ?? null,
                'lng' => $data['lng'] ?? null,
                'note' => $data['note'] ?? null,
                'created_at' => now(),
            ]);

            $update = ['status' => $eventType];

            if ($eventType === 'delivered') {
                $update['delivered_at'] = now();
                $update['proof_photo_path'] = $this->storeCapture($data['photo'] ?? null, 'photo');
                $update['signature_path'] = $this->storeCapture($data['signature'] ?? null, 'signature');
            }

            $shipment->update($update);

            $this->syncOrderGroupStatus($shipment);

            return $shipment->fresh(['events']);
        });
    }

    /**
     * TDD §6.3: order_group status tracks the shipment's own progress —
     * shipped once it's physically moving, delivered (and, since there's
     * no separate buyer-confirmation/return-window step in this run,
     * completed) once the shipper confirms delivery.
     */
    private function syncOrderGroupStatus(OrderGroupShipment $shipment): void
    {
        $orderGroup = $shipment->orderGroup;

        $status = match ($shipment->status) {
            'picked_up', 'in_transit', 'out_for_delivery' => 'shipped',
            'delivered' => 'completed',
            default => null,
        };

        if ($status !== null && $orderGroup->status !== $status) {
            $before = ['status' => $orderGroup->status];
            $orderGroup->update(['status' => $status]);

            $this->auditLogger->log(
                actor: 'system',
                action: 'order_group.'.$status,
                subject: $orderGroup,
                before: $before,
                after: ['status' => $status],
            );
        }
    }

    private function storeCapture(?UploadedFile $file, string $type): ?string
    {
        if ($file === null) {
            return null;
        }

        return $file->store('proof-of-delivery/'.$type, 'shipments');
    }
}

<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * TDD §6.6 "payment failure recovery" implies its mirror: a buyer needs
 * a durable, off-platform confirmation that payment actually succeeded
 * and what was ordered — Production Readiness Report condition #1.
 * Not queued (ShouldQueue) — this launch topology has no queue worker
 * guaranteed running (same reasoning the image pipeline and ai:reindex
 * already document), so a queued mail would simply never send rather
 * than degrade gracefully. App\Services\NotificationMailer sends it
 * synchronously instead, after the triggering transaction commits.
 */
class OrderConfirmed extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Order confirmed — {$this->order->order_number}",
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.orders.confirmed',
            with: [
                'order' => $this->order,
                'orderGroups' => $this->order->orderGroups,
                'recipientName' => $this->order->user?->name,
            ],
        );
    }
}

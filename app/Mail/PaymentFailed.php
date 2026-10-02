<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * TDD §6.6 "payment failure recovery: dedicated state (not a silent
 * redirect)" — the in-app pending/failed page already covers the buyer
 * who is still on the site; this covers the one who closed the tab
 * before retrying, so the failed payment isn't silently abandoned.
 */
class PaymentFailed extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Payment did not go through — {$this->order->order_number}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.orders.payment-failed',
            with: [
                'order' => $this->order,
                'recipientName' => $this->order->user?->name,
            ],
        );
    }
}

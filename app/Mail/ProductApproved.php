<?php

namespace App\Mail;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProductApproved extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Product $product) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Your listing is live — {$this->product->title}");
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.products.approved',
            with: ['product' => $this->product],
        );
    }
}

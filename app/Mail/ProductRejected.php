<?php

namespace App\Mail;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProductRejected extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Product $product,
        public readonly string $reasonCode,
        public readonly ?string $note,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Changes needed — {$this->product->title}");
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.products.rejected',
            with: [
                'product' => $this->product,
                'reasonCode' => $this->reasonCode,
                'note' => $this->note,
            ],
        );
    }
}

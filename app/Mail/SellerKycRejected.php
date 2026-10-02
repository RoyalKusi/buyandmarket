<?php

namespace App\Mail;

use App\Models\Seller;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SellerKycRejected extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Seller $seller,
        public readonly string $reasonCode,
        public readonly ?string $note,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your seller application needs attention');
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.sellers.kyc-rejected',
            with: [
                'seller' => $this->seller,
                'reasonCode' => $this->reasonCode,
                'note' => $this->note,
            ],
        );
    }
}

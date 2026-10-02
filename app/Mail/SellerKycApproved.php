<?php

namespace App\Mail;

use App\Models\Seller;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SellerKycApproved extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Seller $seller) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your seller account is approved');
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.sellers.kyc-approved',
            with: ['seller' => $this->seller],
        );
    }
}

<?php

namespace Database\Factories;

use App\Models\KycDocument;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KycDocument>
 */
class KycDocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'seller_id' => Seller::factory(),
            'type' => 'national_id',
            'file_path' => 'documents/'.fake()->uuid().'.pdf',
            'original_filename' => 'document.pdf',
            'mime_type' => 'application/pdf',
            'status' => 'pending',
            'uploaded_at' => now(),
        ];
    }
}

<?php

namespace Tests\Feature\Seller;

use App\Models\KycDocument;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * TDD §8.3: KYC documents are only ever reachable through a signed,
 * time-limited URL.
 */
class KycDocumentDownloadTest extends TestCase
{
    use RefreshDatabase;

    private function fakeDocument(Seller $seller): KycDocument
    {
        Storage::fake('kyc');
        Storage::disk('kyc')->put('national-id.pdf', 'fake-pdf-contents');

        return KycDocument::factory()->for($seller)->create([
            'type' => 'national_id',
            'file_path' => 'national-id.pdf',
            'original_filename' => 'national-id.pdf',
            'mime_type' => 'application/pdf',
        ]);
    }

    public function test_the_owning_seller_can_generate_a_download_link_and_use_it(): void
    {
        $seller = Seller::factory()->create();
        $document = $this->fakeDocument($seller);

        $signedUrl = $this->actingAs($seller->user)
            ->getJson("/api/v1/kyc-documents/{$document->id}/download-link")
            ->assertOk()
            ->json('data.url');

        $this->assertStringContainsString('signature=', $signedUrl);

        $this->get($signedUrl)->assertOk();
    }

    public function test_an_unsigned_or_tampered_url_is_rejected(): void
    {
        $seller = Seller::factory()->create();
        $document = $this->fakeDocument($seller);

        $this->get("/api/v1/kyc-documents/{$document->id}/download")->assertForbidden();
    }

    public function test_a_different_seller_cannot_generate_a_download_link(): void
    {
        $seller = Seller::factory()->create();
        $document = $this->fakeDocument($seller);
        $otherSeller = Seller::factory()->create();

        $this->actingAs($otherSeller->user)
            ->getJson("/api/v1/kyc-documents/{$document->id}/download-link")
            ->assertForbidden();
    }

    public function test_an_admin_can_generate_a_download_link(): void
    {
        $seller = Seller::factory()->create();
        $document = $this->fakeDocument($seller);
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->getJson("/api/v1/kyc-documents/{$document->id}/download-link")
            ->assertOk();
    }
}

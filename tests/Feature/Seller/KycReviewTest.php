<?php

namespace Tests\Feature\Seller;

use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KycReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejecting_kyc_requires_a_reason_code_and_reopens_the_documents_step(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $seller = Seller::factory()->withStore()->create(['status' => 'under_review']);
        $seller->onboardingSteps()->update(['completed_at' => now()]);

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/sellers/{$seller->id}/kyc-review/reject", [])
            ->assertUnprocessable();

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/sellers/{$seller->id}/kyc-review/reject", [
                'reason_code' => 'document_illegible',
                'note' => 'Please re-upload a clearer photo of your national ID.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.kyc_status', 'rejected');

        $this->assertNull(
            $seller->onboardingSteps()->where('step', 'kyc_documents')->first()->completed_at
        );
        $this->assertDatabaseHas('kyc_reviews', [
            'seller_id' => $seller->id,
            'decision' => 'rejected',
            'reason_code' => 'document_illegible',
        ]);
    }

    public function test_a_seller_cannot_review_their_own_kyc(): void
    {
        $seller = Seller::factory()->withStore()->create(['status' => 'under_review']);

        $this->actingAs($seller->user)
            ->postJson("/api/v1/admin/sellers/{$seller->id}/kyc-review/approve")
            ->assertForbidden();
    }

    public function test_kyc_cannot_be_reviewed_before_the_seller_requests_it(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $seller = Seller::factory()->withStore()->create(['status' => 'pending']);

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/sellers/{$seller->id}/kyc-review/approve")
            ->assertUnprocessable();
    }
}

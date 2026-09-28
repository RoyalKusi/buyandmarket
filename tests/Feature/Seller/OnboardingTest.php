<?php

namespace Tests\Feature\Seller;

use App\Models\Category;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * TDD §3.1 module 4 exit criterion (§14 Run 1.3): "A seller can go from
 * registration to active status end-to-end."
 */
class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('kyc');
    }

    public function test_a_buyer_can_register_as_a_seller_and_is_assigned_the_seller_role(): void
    {
        $user = User::factory()->withRole('buyer')->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/seller/register', ['business_name' => 'Tino\'s Electronics']);

        $response->assertCreated();
        $this->assertTrue($user->fresh()->hasRole('seller'));
        $this->assertDatabaseHas('sellers', ['user_id' => $user->id, 'status' => 'pending']);

        // TDD §3.1 module 4: "resumable, each step's completion timestamped" —
        // business_info is satisfied by registration itself.
        $this->assertDatabaseHas('seller_onboarding_steps', [
            'seller_id' => $user->fresh()->seller->id,
            'step' => 'business_info',
        ]);
        $this->assertNotNull(
            $user->fresh()->seller->onboardingSteps()->where('step', 'business_info')->first()->completed_at
        );
    }

    public function test_a_user_cannot_register_as_a_seller_twice(): void
    {
        $seller = Seller::factory()->create();

        $this->actingAs($seller->user)
            ->postJson('/api/v1/seller/register', ['business_name' => 'Again'])
            ->assertForbidden();
    }

    public function test_the_full_onboarding_flow_takes_a_seller_from_registration_to_active(): void
    {
        $user = User::factory()->withRole('buyer')->create();

        $seller = Seller::where('id', $this->actingAs($user)
            ->postJson('/api/v1/seller/register', ['business_name' => 'Tino\'s Electronics'])
            ->assertCreated()
            ->json('data.id'))->firstOrFail();

        // SellerPolicy::register() accessed (and cached, as null)
        // $user->seller before the Seller row existed; the rest of this
        // flow needs a fresh User instance so that relation re-resolves.
        $user = $user->fresh();

        $this->actingAs($user)
            ->postJson("/api/v1/seller/{$seller->id}/kyc-documents", [
                'national_id' => UploadedFile::fake()->create('id.pdf', 100, 'application/pdf'),
                'proof_of_address' => UploadedFile::fake()->create('address.pdf', 100, 'application/pdf'),
            ])
            ->assertOk();

        $this->actingAs($user)
            ->postJson("/api/v1/seller/{$seller->id}/payout-details", [
                'bank_name' => 'CBZ Bank',
                'account_name' => 'Tino Moyo',
                'account_number' => '0123456789',
            ])
            ->assertOk();

        $this->actingAs($user)
            ->postJson("/api/v1/seller/{$seller->id}/store", [
                'name' => "Tino's Electronics",
                'slug' => 'tinos-electronics',
            ])
            ->assertCreated();

        // First product step, per the stepper, happens before admin review.
        $category = Category::factory()->create();
        $this->actingAs($user)
            ->postJson('/api/v1/seller/products', [
                'category_id' => $category->id,
                'title' => 'Bluetooth Speaker',
                'base_price' => '19.99',
                'variants' => [['sku' => 'SKU-0001', 'stock_quantity' => 3]],
            ])
            ->assertCreated();

        $this->actingAs($user)
            ->postJson("/api/v1/seller/{$seller->id}/submit-for-review")
            ->assertOk()
            ->assertJsonPath('data.status', 'under_review');

        $admin = User::factory()->withRole('admin')->create();
        $this->actingAs($admin)
            ->postJson("/api/v1/admin/sellers/{$seller->id}/kyc-review/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.kyc_status', 'approved');

        $this->assertTrue($seller->fresh()->isActive());
        $this->assertDatabaseHas('kyc_reviews', [
            'seller_id' => $seller->id,
            'reviewer_id' => $admin->id,
            'decision' => 'approved',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'seller.kyc_approved',
            'subject_id' => $seller->id,
        ]);
    }

    public function test_submitting_for_review_before_all_steps_are_complete_is_rejected(): void
    {
        $seller = Seller::factory()->create();

        $this->actingAs($seller->user)
            ->postJson("/api/v1/seller/{$seller->id}/submit-for-review")
            ->assertUnprocessable();
    }

    public function test_a_seller_cannot_manage_another_sellers_onboarding(): void
    {
        $seller = Seller::factory()->create();
        $otherUser = User::factory()->withRole('seller')->create();

        $this->actingAs($otherUser)
            ->postJson("/api/v1/seller/{$seller->id}/store", ['name' => 'Hijack', 'slug' => 'hijack'])
            ->assertForbidden();
    }
}

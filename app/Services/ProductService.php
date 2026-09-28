<?php

namespace App\Services;

use App\Models\Category;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * TDD §3.2 modules 7-8, 13: product + variant creation, the
 * draft -> pending_review -> published -> archived lifecycle (module 7),
 * and price-change logging (module 13).
 */
class ProductService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly AuditLogger $auditLogger,
        private readonly SellerOnboardingService $sellerOnboardingService,
    ) {}

    /**
     * @param  array{category_id: int, brand_id?: int|null, title: string, description?: string|null, base_price: string}  $data
     * @param  array<int, array{sku: string, price_override?: string|null, stock_quantity: int, attribute_value_ids?: array<int>}>  $variants
     */
    public function create(Seller $seller, array $data, array $variants): Product
    {
        if (! $seller->isInGoodStanding()) {
            throw ValidationException::withMessages([
                'seller' => 'Suspended or terminated sellers may not create products.',
            ]);
        }

        if ($variants === []) {
            throw ValidationException::withMessages([
                'variants' => 'A product must have at least one variant.',
            ]);
        }

        $category = Category::findOrFail($data['category_id']);

        // TDD §3.2 module 10: "a product belongs to exactly one leaf category."
        if (! $category->isLeaf()) {
            throw ValidationException::withMessages([
                'category_id' => 'Products can only be assigned to a leaf category.',
            ]);
        }

        return DB::transaction(function () use ($seller, $data, $variants) {
            $product = $seller->store->products()->create([
                'category_id' => $data['category_id'],
                'brand_id' => $data['brand_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'base_price' => $data['base_price'],
                'status' => 'draft',
            ]);

            $this->auditLogger->log(
                actor: $seller->user,
                action: 'product.price_set',
                subject: $product,
                before: null,
                after: ['price' => $data['base_price']],
            );

            PriceHistory::create([
                'product_id' => $product->id,
                'old_price' => null,
                'new_price' => $data['base_price'],
                'actor_id' => $seller->user_id,
                'created_at' => now(),
            ]);

            foreach ($variants as $variantData) {
                $variant = $product->variants()->create([
                    'sku' => $variantData['sku'],
                    'price_override' => $variantData['price_override'] ?? null,
                    'stock_quantity' => 0,
                ]);

                if (! empty($variantData['attribute_value_ids'])) {
                    $variant->attributeValues()->sync($variantData['attribute_value_ids']);
                }

                if (($variantData['stock_quantity'] ?? 0) > 0) {
                    $this->inventoryService->adjustStock(
                        $variant,
                        $variantData['stock_quantity'],
                        'initial_stock',
                    );
                }
            }

            // TDD §3.1 module 4: the onboarding stepper's "first product"
            // step. Idempotent — a no-op once already complete.
            $this->sellerOnboardingService->markFirstProductStepComplete($seller);

            return $product->fresh(['variants']);
        });
    }

    public function submitForReview(Product $product): Product
    {
        $this->assertStatus($product, 'draft');
        $product->update(['status' => 'pending_review']);

        return $product;
    }

    public function approve(Product $product, User $admin): Product
    {
        $this->assertStatus($product, 'pending_review');

        // TDD §3.1 module 2: "only active sellers can list products" —
        // this is where that rule actually bites (see ProductPolicy::
        // create() for why it isn't enforced at draft-creation time).
        if (! $product->store->seller->isActive()) {
            throw ValidationException::withMessages([
                'seller' => 'Only an active seller\'s products may be published.',
            ]);
        }

        return DB::transaction(function () use ($product, $admin) {
            $before = $product->only(['status']);
            $product->update(['status' => 'published']);

            $this->auditLogger->log(
                actor: $admin,
                action: 'product.approved',
                subject: $product,
                before: $before,
                after: $product->only(['status']),
            );

            return $product;
        });
    }

    /**
     * TDD §4.2: "Admin approval carries a mandatory reason code + free-text
     * note, surfaced in the seller dashboard with a direct 'fix and
     * resubmit' path" — rejection returns the product to draft so the
     * seller can edit and resubmit.
     */
    public function reject(Product $product, User $admin, string $reasonCode, ?string $note = null): Product
    {
        $this->assertStatus($product, 'pending_review');

        return DB::transaction(function () use ($product, $admin, $reasonCode, $note) {
            $before = $product->only(['status']);
            $product->update(['status' => 'draft']);

            $this->auditLogger->log(
                actor: $admin,
                action: 'product.rejected',
                subject: $product,
                before: $before,
                after: [...$product->only(['status']), 'reason_code' => $reasonCode, 'note' => $note],
            );

            return $product;
        });
    }

    public function archive(Product $product, User $actor): Product
    {
        $this->assertStatus($product, 'published');
        $product->update(['status' => 'archived']);

        return $product;
    }

    public function updatePrice(Product $product, string $newPrice, User $actor): Product
    {
        return DB::transaction(function () use ($product, $newPrice, $actor) {
            $oldPrice = $product->base_price;
            $product->update(['base_price' => $newPrice]);

            PriceHistory::create([
                'product_id' => $product->id,
                'old_price' => $oldPrice,
                'new_price' => $newPrice,
                'actor_id' => $actor->id,
                'created_at' => now(),
            ]);

            $this->auditLogger->log(
                actor: $actor,
                action: 'product.price_updated',
                subject: $product,
                before: ['price' => (string) $oldPrice],
                after: ['price' => $newPrice],
            );

            return $product;
        });
    }

    private function assertStatus(Product $product, string $expected): void
    {
        if ($product->status !== $expected) {
            throw ValidationException::withMessages([
                'status' => "Product must be in '{$expected}' status for this transition (currently '{$product->status}').",
            ]);
        }
    }
}

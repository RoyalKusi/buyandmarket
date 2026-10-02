<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * TDD §3.2 module 11: seller-suggested brands enter as pending and are
 * admin-approved before appearing in filters, preventing catalogue
 * pollution from free-text brand entry.
 */
class BrandService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function suggest(Seller $seller, string $name, string $slug): Brand
    {
        return Brand::create([
            'name' => $name,
            'slug' => $slug,
            'status' => 'pending',
            'suggested_by_seller_id' => $seller->id,
        ]);
    }

    public function approve(Brand $brand, User $admin): Brand
    {
        return DB::transaction(function () use ($brand, $admin) {
            $before = $brand->only(['status']);
            $brand->update(['status' => 'approved']);

            $this->auditLogger->log(
                actor: $admin,
                action: 'brand.approved',
                subject: $brand,
                before: $before,
                after: $brand->only(['status']),
            );

            return $brand;
        });
    }

    public function reject(Brand $brand, User $admin): Brand
    {
        return DB::transaction(function () use ($brand, $admin) {
            $before = $brand->only(['status']);
            $brand->update(['status' => 'rejected']);

            $this->auditLogger->log(
                actor: $admin,
                action: 'brand.rejected',
                subject: $brand,
                before: $before,
                after: $brand->only(['status']),
            );

            return $brand;
        });
    }
}

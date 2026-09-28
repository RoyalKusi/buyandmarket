<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Grants a permission to one of the fixed platform roles (buyer/seller/
 * shipper/admin). Sub-admin grants live in admin_role_permissions instead.
 */
class RolePermission extends Model
{
    protected $fillable = [
        'role',
        'permission_id',
    ];

    /**
     * @return BelongsTo<Permission, $this>
     */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }
}

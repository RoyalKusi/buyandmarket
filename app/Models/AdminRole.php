<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A named, least-privilege sub-admin permission set (TDD §3.6 module 31),
 * e.g. "Catalogue moderator", "Finance officer".
 */
class AdminRole extends Model
{
    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'admin_role_permissions');
    }
}

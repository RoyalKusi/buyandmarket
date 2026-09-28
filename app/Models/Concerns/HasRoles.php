<?php

namespace App\Models\Concerns;

use App\Models\AdminRole;
use App\Models\RoleAssignment;
use App\Models\RolePermission;
use Illuminate\Database\Eloquent\Model;

/**
 * TDD §8.1 RBAC model. A user can hold multiple roles simultaneously
 * (role_assignments) — matching real cases like a seller who also buys.
 * Fixed-role permissions come from role_permissions; a sub_admin's
 * permissions come from the admin_roles row its assignment is scoped to.
 *
 * @mixin Model
 */
trait HasRoles
{
    public function hasRole(string $role): bool
    {
        return $this->roleAssignments()->where('role', $role)->exists();
    }

    public function hasAnyRole(string ...$roles): bool
    {
        return $this->roleAssignments()->whereIn('role', $roles)->exists();
    }

    public function assignRole(string $role, ?int $adminRoleId = null): RoleAssignment
    {
        return $this->roleAssignments()->firstOrCreate(
            ['role' => $role],
            ['scope' => $adminRoleId]
        );
    }

    /**
     * True when this user holds a role granted the named permission — either
     * directly (role_permissions) or, for sub_admin, via the admin_roles set
     * their assignment is scoped to.
     */
    public function hasPermission(string $permission): bool
    {
        $roles = $this->roleAssignments()->pluck('role');

        if ($roles->isEmpty()) {
            return false;
        }

        $fixedRoles = $roles->reject(fn ($role) => $role === 'sub_admin')->values();

        if ($fixedRoles->isNotEmpty()) {
            $granted = RolePermission::query()
                ->whereIn('role', $fixedRoles)
                ->whereHas('permission', fn ($q) => $q->where('name', $permission))
                ->exists();

            if ($granted) {
                return true;
            }
        }

        $adminRoleIds = $this->roleAssignments()
            ->where('role', 'sub_admin')
            ->whereNotNull('scope')
            ->pluck('scope');

        if ($adminRoleIds->isEmpty()) {
            return false;
        }

        return AdminRole::query()
            ->whereIn('id', $adminRoleIds)
            ->whereHas('permissions', fn ($q) => $q->where('name', $permission))
            ->exists();
    }
}

<?php

namespace App\Policies;

use App\Models\User;

/**
 * TDD §8.1: assigning a role is itself a privileged action (§8.9 — audit
 * logged). Only a platform admin may grant or revoke roles; a sub_admin's
 * own least-privilege scope never includes granting roles to others.
 */
class RoleAssignmentPolicy
{
    public function assign(User $actor, User $target): bool
    {
        return $actor->hasRole('admin');
    }
}

<?php

namespace Tests\Unit\Models;

use App\Models\AdminRole;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_hold_multiple_roles_simultaneously(): void
    {
        $user = User::factory()->create();

        $user->assignRole('buyer');
        $user->assignRole('seller');

        $this->assertTrue($user->hasRole('buyer'));
        $this->assertTrue($user->hasRole('seller'));
        $this->assertFalse($user->hasRole('shipper'));
        $this->assertTrue($user->hasAnyRole('shipper', 'seller'));
    }

    public function test_a_fixed_role_inherits_permissions_granted_via_role_permissions(): void
    {
        $permission = Permission::create(['name' => 'catalogue.manage']);
        RolePermission::create(['role' => 'admin', 'permission_id' => $permission->id]);

        $admin = User::factory()->withRole('admin')->create();
        $buyer = User::factory()->withRole('buyer')->create();

        $this->assertTrue($admin->hasPermission('catalogue.manage'));
        $this->assertFalse($buyer->hasPermission('catalogue.manage'));
    }

    public function test_a_sub_admin_inherits_permissions_from_their_scoped_admin_role(): void
    {
        $permission = Permission::create(['name' => 'catalogue.moderate']);
        $adminRole = AdminRole::create(['name' => 'Catalogue moderator']);
        $adminRole->permissions()->attach($permission);

        $subAdmin = User::factory()->create();
        $subAdmin->assignRole('sub_admin', $adminRole->id);

        $otherSubAdmin = User::factory()->create();
        $otherRole = AdminRole::create(['name' => 'Finance officer']);
        $otherSubAdmin->assignRole('sub_admin', $otherRole->id);

        $this->assertTrue($subAdmin->hasPermission('catalogue.moderate'));
        $this->assertFalse($otherSubAdmin->hasPermission('catalogue.moderate'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * TDD §11: an authorization-matrix test for every new Policy-protected
 * route — "can role X reach resource Y."
 */
class RoleAssignmentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public static function nonAdminRoles(): array
    {
        return [
            'buyer' => ['buyer'],
            'seller' => ['seller'],
            'shipper' => ['shipper'],
        ];
    }

    public function test_an_admin_can_assign_a_role_and_it_is_audit_logged(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $target = User::factory()->create();

        $response = $this->actingAs($admin)
            ->postJson("/api/v1/admin/users/{$target->id}/roles", ['role' => 'seller']);

        $response->assertCreated();
        $this->assertTrue($target->fresh()->hasRole('seller'));

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'actor_type' => 'user',
            'action' => 'role_assignment.created',
            'subject_type' => User::class,
            'subject_id' => $target->id,
        ]);
        $this->assertSame(1, AuditLog::count());
    }

    #[DataProvider('nonAdminRoles')]
    public function test_a_non_admin_cannot_assign_a_role(string $role): void
    {
        $actor = User::factory()->withRole($role)->create();
        $target = User::factory()->create();

        $response = $this->actingAs($actor)
            ->postJson("/api/v1/admin/users/{$target->id}/roles", ['role' => 'seller']);

        $response->assertForbidden();
        $this->assertFalse($target->fresh()->hasRole('seller'));
        $this->assertSame(0, AuditLog::count());
    }

    public function test_a_guest_cannot_assign_a_role(): void
    {
        $target = User::factory()->create();

        $response = $this->postJson("/api/v1/admin/users/{$target->id}/roles", ['role' => 'seller']);

        $response->assertUnauthorized();
    }
}
